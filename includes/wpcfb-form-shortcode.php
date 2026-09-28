<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Form Shortcode & Callback Function
add_shortcode( 'wpcfb_form', 'wpcfb_form_shortcode_callback' );
function wpcfb_form_shortcode_callback( $atts ) {
    $atts = shortcode_atts( array( 'id' => 0 ), $atts );
    $form = wpcfb_get_form( $atts['id'] );
    if ( ! $form ) {
        return '';
    }

    $fields = get_post_meta( $form->ID, '_wpcfb_fields', true );

    ob_start();
    // The nonce is fetched over AJAX on submit (see public/script.js) so cached pages keep working.
    echo '<form class="wpcfb-form" method="post" novalidate>';
    echo '<input type="hidden" class="wpcfb-form-id" name="wpcfb_form_id" value="' . esc_attr( $form->ID ) . '">';
    echo wpcfb_render_form_fields( $fields ); // Escaped by each field renderer.
    wpcfb_render_honeypot();
    wpcfb_render_turnstile();
    wpcfb_render_submit_button( wpcfb_get_button_label( $form->ID ), wpcfb_get_form_style( $form->ID ) );
    echo '<div class="wpcfb-message" role="status" aria-live="polite"></div>';
    echo '</form>';
    return ob_get_clean();
}

// $type 'button' is for the admin preview, which sits inside the post edit form: a submit button there would save the post.
function wpcfb_render_submit_button( $label, $style, $type = 'submit' ){
    printf(
        '<button type="%4$s" class="wpcfb-submit" style="background-color:%1$s; border-color:%1$s; color:%2$s">%3$s</button>',
        esc_attr( $style['btn_color'] ),
        esc_attr( $style['btn_text_color'] ),
        esc_html( $label ),
        'button' === $type ? 'button' : 'submit'
    );
}
