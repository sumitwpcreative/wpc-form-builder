<?php
if( ! defined('ABSPATH')){
    exit;
}

function wpcfb_get_field_types(){
    return apply_filters( 'wpcfb_field_types', array() );
}