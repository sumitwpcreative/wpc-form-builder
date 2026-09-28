<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Spam protection, cheapest check first: honeypot, time trap, rate limit, then Cloudflare Turnstile (optional).

define( 'WPCFB_HONEYPOT_FIELD', 'wpcfb_hp_website' );

// Minimum seconds between the form being started and submitted.
function wpcfb_min_submit_seconds(){
    return (int) apply_filters( 'wpcfb_min_submit_seconds', 3 );
}

// Signed "form started" token. Issued over AJAX (not printed in the page) so cached pages keep working.
function wpcfb_create_timing_token(){
    $time = time();
    return $time . '.' . hash_hmac( 'sha256', (string) $time, wp_salt( 'nonce' ) );
}

// True, or an error code: 'missing', 'invalid', 'too_fast', 'expired'.
function wpcfb_check_timing_token( $token ){
    $parts = explode( '.', (string) $token );
    if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
        return '' === (string) $token ? 'missing' : 'invalid';
    }

    if ( ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ) ), $parts[1] ) ) {
        return 'invalid';
    }

    $age = time() - (int) $parts[0];
    if ( $age < wpcfb_min_submit_seconds() ) {
        return 'too_fast';
    }
    if ( $age > DAY_IN_SECONDS ) {
        return 'expired';
    }
    return true;
}

// Client IP. REMOTE_ADDR only by default: forwarded headers are trivially spoofed.
// Behind Cloudflare, use: add_filter( 'wpcfb_client_ip', fn() => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '' );
function wpcfb_get_client_ip(){
    $ip = apply_filters( 'wpcfb_client_ip', $_SERVER['REMOTE_ADDR'] ?? '' );
    return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

// IPs are hashed so no raw addresses sit in the options table.
function wpcfb_rate_limit_key(){
    $ip = wpcfb_get_client_ip();
    return $ip ? 'wpcfb_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 ) : '';
}

function wpcfb_is_rate_limited(){
    $limit = (int) wpcfb_get_settings()['rate_limit'];
    $key   = wpcfb_rate_limit_key();
    if ( $limit <= 0 || ! $key ) {
        return false;
    }

    $hits = get_transient( $key );
    return is_array( $hits ) && count( wpcfb_recent_hits( $hits ) ) >= $limit;
}

// Record a saved submission against this IP (sliding one-hour window).
function wpcfb_record_rate_limit_hit(){
    $key = wpcfb_rate_limit_key();
    if ( ! $key || (int) wpcfb_get_settings()['rate_limit'] <= 0 ) {
        return;
    }

    $hits   = wpcfb_recent_hits( get_transient( $key ) );
    $hits[] = time();
    set_transient( $key, $hits, HOUR_IN_SECONDS );
}

function wpcfb_recent_hits( $hits ){
    $cutoff = time() - HOUR_IN_SECONDS;
    return array_values( array_filter( (array) $hits, function( $t ) use ( $cutoff ) {
        return (int) $t > $cutoff;
    } ) );
}

function wpcfb_turnstile_enabled(){
    $settings = wpcfb_get_settings();
    return '' !== $settings['turnstile_site_key'] && '' !== $settings['turnstile_secret_key'];
}

// Server-side Turnstile check. Fails closed: if Cloudflare cannot be reached, the submission is refused.
function wpcfb_verify_turnstile( $token ){
    if ( '' === (string) $token ) {
        return false;
    }

    $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
        'timeout' => 10,
        'body'    => array(
            'secret'   => wpcfb_get_settings()['turnstile_secret_key'],
            'response' => $token,
            'remoteip' => wpcfb_get_client_ip(),
        ),
    ) );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    return ! empty( $body['success'] );
}

// Run every check. Returns null when clean, or array( 'code', 'message', 'status', 'silent' ).
// 'silent' means reply with a fake success so bots learn nothing.
function wpcfb_spam_check( $form_data, $form ){
    $result = null;

    if ( '' !== trim( (string) ( $form_data[ WPCFB_HONEYPOT_FIELD ] ?? '' ) ) ) {
        $result = array( 'code' => 'honeypot', 'silent' => true );
    }

    if ( ! $result ) {
        $timing = wpcfb_check_timing_token( $form_data['wpcfb_started'] ?? '' );
        if ( true !== $timing ) {
            $result = array(
                'code'    => 'timing_' . $timing,
                'status'  => 400,
                'message' => 'too_fast' === $timing
                    ? __( 'That was quick! Please wait a moment and try again.', 'wpc-form-builder' )
                    : __( 'Your session has expired. Please refresh the page and try again.', 'wpc-form-builder' ),
            );
        }
    }

    if ( ! $result && wpcfb_is_rate_limited() ) {
        $result = array(
            'code'    => 'rate_limited',
            'status'  => 429,
            'message' => __( 'Too many submissions from your connection. Please try again later.', 'wpc-form-builder' ),
        );
    }

    if ( ! $result && wpcfb_turnstile_enabled() && ! wpcfb_verify_turnstile( $form_data['cf-turnstile-response'] ?? '' ) ) {
        $result = array(
            'code'    => 'turnstile',
            'status'  => 400,
            'message' => __( 'Please complete the verification and try again.', 'wpc-form-builder' ),
        );
    }

    if ( $result ) {
        do_action( 'wpcfb_spam_blocked', $result['code'], $form->ID );
    }
    return $result;
}

// Hidden trap field: off-screen (not display:none, which some bots skip), out of tab order and hidden from screen readers.
function wpcfb_render_honeypot(){
    printf(
        '<div class="wpcfb-hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"><label>%s<input type="text" name="%s" value="" tabindex="-1" autocomplete="off"></label></div>',
        esc_html__( 'Leave this field empty', 'wpc-form-builder' ),
        esc_attr( WPCFB_HONEYPOT_FIELD )
    );
}

function wpcfb_render_turnstile(){
    if ( ! wpcfb_turnstile_enabled() ) {
        return;
    }

    // Cloudflare requires the script to load from their domain, unversioned.
    wp_enqueue_script( 'wpcfb-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, array( 'strategy' => 'defer', 'in_footer' => true ) );
    printf( '<div class="cf-turnstile wpcfb-turnstile" data-sitekey="%s"></div>', esc_attr( wpcfb_get_settings()['turnstile_site_key'] ) );
}
