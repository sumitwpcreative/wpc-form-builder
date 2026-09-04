<?php

// Enqueue Admin Style & Scripts
function wpcfb_admin_enqueue_script(){
    wp_enqueue_style('wpcfb_admin_style', WPC_FORM_BUILDER_DIR_URL . 'admin/style.css', array(), '1.0.0', 'all');

    wp_enqueue_script('wpcfb_admin_script', WPC_FORM_BUILDER_DIR_URL . 'admin/script.js', array(), '1.0.0');
}
add_action('admin_enqueue_scripts', 'wpcfb_admin_enqueue_script');