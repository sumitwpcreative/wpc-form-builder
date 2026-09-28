<?php
/* 
Plugin Name: WPC Form Builder
Description: Simple Contact Form Plugin.
Version: 1.0.0
Author: Sumit Jha
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define
define( 'WPC_FORM_BUILDER_VERSION', '1.0.0' );
define( 'WPC_FORM_BUILDER_FILE', __FILE__ );
define('WPC_FORM_BUILDER_DIR_URL', plugin_dir_url( __FILE__ ));

// Require
require plugin_dir_path( __FILE__ ) . 'includes/activate_deactivate.php';
require plugin_dir_path( __FILE__ ) . 'includes/enqueue.php';
require plugin_dir_path( __FILE__ ) . 'includes/functions.php';
require plugin_dir_path( __FILE__ ) . 'includes/settings.php';
require plugin_dir_path( __FILE__ ) . 'includes/submissions.php';
require plugin_dir_path( __FILE__ ) . 'includes/notifications.php';
require plugin_dir_path( __FILE__ ) . 'includes/spam.php';
require plugin_dir_path( __FILE__ ) . 'includes/field-types.php';

foreach ( glob( plugin_dir_path( __FILE__ ) . 'includes/form-tags/*.php' ) as $field_type_file ) {
    require $field_type_file;
}

require plugin_dir_path( __FILE__ ) . 'includes/wpcfb-form-shortcode.php';
