<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Asset version: plugin version plus file modified time, so browsers pick up changes without a version bump.
function wpcfb_asset_version( $relative_path ){
    $file = plugin_dir_path( WPC_FORM_BUILDER_FILE ) . $relative_path;
    return file_exists( $file ) ? WPC_FORM_BUILDER_VERSION . '.' . filemtime( $file ) : WPC_FORM_BUILDER_VERSION;
}

// Enqueue Admin Style & Scripts (plugin screens only)
function wpcfb_admin_enqueue_script(){
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->post_type, array( 'wpcfb_form', 'wpcfb_submission' ), true ) ) {
        return;
    }

    wp_enqueue_style('wpcfb_admin_style', WPC_FORM_BUILDER_DIR_URL . 'admin/style.css', array(), wpcfb_asset_version( 'admin/style.css' ), 'all');

    wp_enqueue_script('wpcfb_admin_script', WPC_FORM_BUILDER_DIR_URL . 'admin/script.js', array('jquery', 'jquery-ui-sortable'), wpcfb_asset_version( 'admin/script.js' ));

    if ( 'wpcfb_form' === $screen->post_type ) {
        // Same stylesheet as the live form, so the preview grid matches.
        wp_enqueue_style('wpcfb_style', WPC_FORM_BUILDER_DIR_URL . 'public/style.css', array(), wpcfb_asset_version( 'public/style.css' ), 'all');

        $widths = array();
        foreach ( wpcfb_field_widths() as $value => $label ) {
            $widths[] = array( 'value' => (string) $value, 'label' => $label );
        }

        wp_localize_script('wpcfb_admin_script', 'wpcfbAdmin', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'preview_nonce' => wp_create_nonce( 'wpcfb_preview' ),
            'form_id'       => get_the_ID(),
            'widths'        => $widths,
            'types'         => array_map( function( $type ){
                return array( 'label' => $type['label'], 'supports' => array_values( (array) $type['supports'] ) );
            }, wpcfb_get_field_types() ),
            'i18n'          => array(
                'fieldSettings' => __( 'Field Settings', 'wpc-form-builder' ),
                'selectField'   => __( 'Click a field to edit its label, name, placeholder and width.', 'wpc-form-builder' ),
                'emptyCanvas'   => __( 'No fields yet. Use the buttons above to add one.', 'wpc-form-builder' ),
                'label'         => __( 'Label', 'wpc-form-builder' ),
                'name'          => __( 'Field name', 'wpc-form-builder' ),
                'nameHelp'      => __( 'Used as the key in submissions and emails. Lowercase letters, numbers and underscores. Changing it on a live form means older submissions show the old name.', 'wpc-form-builder' ),
                'nameTaken'     => __( 'Another field already uses this name.', 'wpc-form-builder' ),
                'nameEmpty'     => __( 'Field name cannot be empty.', 'wpc-form-builder' ),
                'placeholder'   => __( 'Placeholder', 'wpc-form-builder' ),
                'selectPrompt'  => __( 'First option text', 'wpc-form-builder' ),
                'selectPromptHelp' => __( 'Shown before a choice is made, e.g. "Please select". Leave blank for the default.', 'wpc-form-builder' ),
                'options'       => __( 'Options', 'wpc-form-builder' ),
                'optionsHelp'   => __( 'One per line. This exact text is shown and saved.', 'wpc-form-builder' ),
                'optionsEmpty'  => __( 'Add at least one option, or this field will not show on the form.', 'wpc-form-builder' ),
                'checkboxRequiredHelp' => __( 'Required means at least one box must be ticked. With one option, this works as a consent checkbox.', 'wpc-form-builder' ),
                'defaultOptions' => array( __( 'Option 1', 'wpc-form-builder' ), __( 'Option 2', 'wpc-form-builder' ), __( 'Option 3', 'wpc-form-builder' ) ),
                'newField'      => __( 'New %s', 'wpc-form-builder' ),
                'width'         => __( 'Width', 'wpc-form-builder' ),
                'widthHelp'     => __( 'Fields sit side by side until the row is full. On phones every field is full width.', 'wpc-form-builder' ),
                'required'      => __( 'Required', 'wpc-form-builder' ),
                'remove'        => __( 'Remove field', 'wpc-form-builder' ),
                'drag'          => __( 'Drag to reorder', 'wpc-form-builder' ),
                'loading'       => __( 'Loading preview…', 'wpc-form-builder' ),
                'previewError'  => __( 'Preview could not be loaded. Save the form and try again.', 'wpc-form-builder' ),
            ),
        ));
    }
}
add_action('admin_enqueue_scripts', 'wpcfb_admin_enqueue_script');


// Enqueue Front Style & Scripts
function wpcfb_front_enqueue_script(){
    wp_enqueue_style('wpcfb_style', WPC_FORM_BUILDER_DIR_URL . 'public/style.css', array(), wpcfb_asset_version( 'public/style.css' ), 'all');

    wp_enqueue_script('wpcfb_script', WPC_FORM_BUILDER_DIR_URL . 'public/script.js', array('jquery'), wpcfb_asset_version( 'public/script.js' ));

    wp_localize_script('wpcfb_script', 'wpcfbData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'messages' => array(
            'success' => wpcfb_default_success_message(),
            'error'   => __( 'Something went wrong. Please try again.', 'wpc-form-builder' ),
        ),
    ));

}
add_action('wp_enqueue_scripts', 'wpcfb_front_enqueue_script');
