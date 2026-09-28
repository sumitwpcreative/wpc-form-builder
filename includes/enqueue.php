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
