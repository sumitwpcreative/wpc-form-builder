<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
// Activation Hook
function wpcfb_activate(){

}
register_activation_hook( WPC_FORM_BUILDER_FILE, 'wpcfb_activate' );

// Deactivation Hook
function wpcfb_deactivate(){

}
register_deactivation_hook( WPC_FORM_BUILDER_FILE, 'wpcfb_deactivate' );
