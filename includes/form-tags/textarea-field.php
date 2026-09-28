<?php
if ( ! defined( 'ABSPATH' )){
    exit;
}

add_filter('wpcfb_field_types', 'wpcfb_register_textarea_field');
function wpcfb_register_textarea_field($types){
    $types['textarea']  = array(
        'label' => 'Textarea',
        'render'    => 'wpcfb_render_textarea_field',
        'order'     => 40,
    );
    return $types;
}

function wpcfb_render_textarea_field($field){
    printf(
        '<p class="wpcfb-field"><label>%1$s%2$s<br><textarea name="%3$s" placeholder="%4$s" %5$s>%6$s</textarea></label></p>',
        esc_html( $field['label'] ),
        ! empty( $field['required'] ) ? ' <span class="required">*</span>' : '',
        esc_attr( $field['name'] ),
        esc_attr( $field['placeholder'] ?? '' ),
        ! empty( $field['required'] ) ? 'required' : '',
        esc_textarea( $field['value'] ?? '')
    );
}

