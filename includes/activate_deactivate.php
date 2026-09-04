<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
// Activation Hook
function wpcfb_activate(){

}
register_activation_hook( __FILE__, 'wpcfb_activate' );

// Deactivation Hook
function wpcfb_deactivate(){

}
register_deactivation_hook( __FILE__, 'wpcfb_deactivate' );