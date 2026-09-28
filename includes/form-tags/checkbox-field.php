<?php
if ( ! defined( 'ABSPATH' )){
    exit;
}

add_filter('wpcfb_field_types', 'wpcfb_register_checkbox_field');
function wpcfb_register_checkbox_field($types){
    $types['checkbox']  = array(
        'label'     => __( 'Checkboxes', 'wpc-form-builder' ),
        'render'    => 'wpcfb_render_checkbox_field',
        'order'     => 70,
        'supports'  => array( 'options' ),
    );
    return $types;
}

// Several choices allowed. "Required" means at least one ticked (checked on the server), so no HTML
// required attribute: on a group that would force every box. One option = a consent checkbox.
function wpcfb_render_checkbox_field($field){
    wpcfb_render_choice_group( $field, 'checkbox' );
}
