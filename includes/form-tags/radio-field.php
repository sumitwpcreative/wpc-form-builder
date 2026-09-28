<?php
if ( ! defined( 'ABSPATH' )){
    exit;
}

add_filter('wpcfb_field_types', 'wpcfb_register_radio_field');
function wpcfb_register_radio_field($types){
    $types['radio']  = array(
        'label'     => __( 'Radio Buttons', 'wpc-form-builder' ),
        'render'    => 'wpcfb_render_radio_field',
        'order'     => 60,
        'supports'  => array( 'options' ),
    );
    return $types;
}

function wpcfb_render_radio_field($field){
    wpcfb_render_choice_group( $field, 'radio' );
}
