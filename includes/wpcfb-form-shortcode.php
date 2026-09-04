<?php
// Form Shortcode & Callback Function
add_shortcode( 'wpcfb_form', 'wpcfb_form_shortcode_callback' );
function wpcfb_form_shortcode_callback( $atts ) {
    $atts   = shortcode_atts( array( 'id' => 0 ), $atts );
    $fields = get_post_meta( $atts['id'], '_wpcfb_fields', true );
    $types  = wpcfb_get_field_types();

    ob_start();
    echo '<form class="wpcfb-form" method="post">';
    foreach ( (array) $fields as $field ) {
        if ( isset( $field['type'], $types[ $field['type'] ]['render'] ) ) {
            call_user_func( $types[ $field['type'] ]['render'], $field );
        }
    }
    echo '</form>';
    return ob_get_clean();
}
