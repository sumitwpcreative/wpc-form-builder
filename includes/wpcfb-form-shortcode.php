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

    $fields    = get_post_meta( $form->ID, '_wpcfb_fields', true );
    $types     = wpcfb_get_field_types();
    $formStyle = wpcfb_get_form_style( $form->ID );

    ob_start();
    // The nonce is fetched over AJAX on submit (see public/script.js) so cached pages keep working.
    echo '<form class="wpcfb-form" method="post" novalidate>';
    echo '<input type="hidden" class="wpcfb-form-id" name="wpcfb_form_id" value="' . esc_attr( $form->ID ) . '">';
    foreach ( (array) $fields as $field ) {
        if ( isset( $field['type'], $types[ $field['type'] ]['render'] ) ) {
            call_user_func( $types[ $field['type'] ]['render'], $field );
        }
    }
    wpcfb_render_honeypot();
    wpcfb_render_turnstile();
    printf(
        '<button type="submit" style="background-color:%1$s; border-color:%1$s; color:%2$s">%3$s</button>',
        esc_attr( $formStyle['btn_color'] ),
        esc_attr( $formStyle['btn_text_color'] ),
        esc_html( wpcfb_get_button_label( $form->ID ) )
    );
    echo '<div class="wpcfb-message" role="status" aria-live="polite"></div>';
    echo '</form>';
    return ob_get_clean();
}
