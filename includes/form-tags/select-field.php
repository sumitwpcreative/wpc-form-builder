<?php
if ( ! defined( 'ABSPATH' )){
    exit;
}

add_filter('wpcfb_field_types', 'wpcfb_register_select_field');
function wpcfb_register_select_field($types){
    $types['select']  = array(
        'label'     => __( 'Dropdown', 'wpc-form-builder' ),
        'render'    => 'wpcfb_render_select_field',
        'order'     => 50,
        'supports'  => array( 'placeholder', 'options' ),
    );
    return $types;
}

// The placeholder becomes an empty first option, so "required" means a real choice was made.
function wpcfb_render_select_field($field){
    $prompt  = ! empty( $field['placeholder'] ) ? $field['placeholder'] : __( 'Please select', 'wpc-form-builder' );
    $options = '<option value="">' . esc_html( $prompt ) . '</option>';
    foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
        $options .= '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
    }

    printf(
        '<p class="wpcfb-field"><label>%1$s<br><select name="%2$s" %3$s>%4$s</select></label></p>',
        wpcfb_field_label_html( $field ),
        esc_attr( $field['name'] ),
        ! empty( $field['required'] ) ? 'required' : '',
        $options // Escaped above.
    );
}
