<?php
if ( ! defined( 'ABSPATH' )){
    exit;
}

add_filter('wpcfb_field_types', 'wpcfb_register_email_field');
function wpcfb_register_email_field($types){
    $types['email']  = array(
        'label' => 'Email',
        'render'    => 'wpcfb_render_email_field',
    );
    return $types;
}

function wpcfb_render_email_field($field){
    printf(
        '<p class="wpcfb-field"><label>%1$s%2$s<br><input type="email" name="%3$s" placeholder="%4$s" %5$s></label></p>',
        esc_html( $field['label'] ),
        ! empty( $field['required'] ) ? ' <span class="required">*</span>' : '',
        esc_attr( $field['name'] ),
        esc_attr( $field['placeholder'] ?? '' ),
        ! empty( $field['required'] ) ? 'required' : ''
    );
}