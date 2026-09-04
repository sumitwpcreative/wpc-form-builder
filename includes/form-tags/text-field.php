<?php
if ( ! defined( 'ABSPATH' )){
    exit;
}

add_filter('wpcfb_field_types', 'wpcfb_register_text_field');
function wpcfb_register_text_field($types){
    $types['text']  = array(
        'label' => 'Text',
        'render'    => 'wpcfb_render_text_field',
    );
    return $types;
}

function wpcfb_render_text_field($field){
    printf(
        '<p class="wpcfb-field"><label>%1$s%2$s<br><input type="text" name="%3$s" placeholder="%4$s" %5$s></label></p>',
        esc_html( $field['label'] ),
        ! empty( $field['required'] ) ? ' <span class="required">*</span>' : '',
        esc_attr( $field['name'] ),
        esc_attr( $field['placeholder'] ?? '' ),
        ! empty( $field['required'] ) ? 'required' : ''
    );
}