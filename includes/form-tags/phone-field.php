<?php
if ( ! defined( 'ABSPATH' )){
    exit;
}

add_filter('wpcfb_field_types', 'wpcfb_register_phone_field');
function wpcfb_register_phone_field($types){
    $types['phone']  = array(
        'label'     => __( 'Phone', 'wpc-form-builder' ),
        'render'    => 'wpcfb_render_phone_field',
        'order'     => 30,
        'supports'  => array( 'placeholder' ),
    );
    return $types;
}

// type="tel" brings up the number keypad on phones; autocomplete fills the visitor's saved number.
function wpcfb_render_phone_field($field){
    printf(
        '<p class="wpcfb-field"><label>%1$s<br><input type="tel" name="%2$s" placeholder="%3$s" autocomplete="tel" inputmode="tel" %4$s></label></p>',
        wpcfb_field_label_html( $field ),
        esc_attr( $field['name'] ),
        esc_attr( $field['placeholder'] ?? '' ),
        ! empty( $field['required'] ) ? 'required' : ''
    );
}
