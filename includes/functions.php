<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Register Post Type
function wpcfb_custom_post_type(){
    $args = array(
            'labels'        => array(
                'name'  => __('WPC Forms'),
                'add_new'=> __('Add New Form'),
                'add_new_item'=> __('Add New Form'),
                'not_found'=> __('No forms found'),
                'not_found_in_trash'=> __('No forms found in trash'),
            ),
            'description'   => 'A custom post type for WPC Forms',
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => true,
            'show_in_admin_bar'=> false,
            'supports'      => array('title')
        );
    register_post_type( 'wpcfb_form', $args );
}
add_action('init','wpcfb_custom_post_type');

// Custom Meta Boxes
function wpcfb_custom_meta_boxes(){
    add_meta_box(
        'wpcfb_form_meta',
        __('WPC Forms'),
        'wpcfb_meta_box_callback',
        'wpcfb_form',
        'normal',
        'high'

    );
}
add_action( 'add_meta_boxes', 'wpcfb_custom_meta_boxes' );

// Metabox Callback Function
function wpcfb_meta_box_callback(){
    wp_nonce_field('wpcfb_save_meta_box', 'wpcfb_meta_box_nonce');
    $field_types = wpcfb_get_field_types();
    $existing_fields = get_post_meta( get_the_ID(), '_wpcfb_fields', true);
    if( ! is_array( $existing_fields ) ) {
        $existing_fields = array();
    }
    ?>
        <div class="wpcfb-tabs-wrapper">
            <code>[wpcfb_form id='<?php echo get_the_ID(); ?>']</code>
            <ul class="wpcfb-tab-nav">
                <li class="wpcfb-tab-link active" data-tab="form">Form</li>
                <li class="wpcfb-tab-link" data-tab="settings">Settings</li>
            </ul>

            <div class="wpcfb-tab-panel active" id="wpcfb-tab-form">
                <h2>Form Builder</h2>
                <p>Edit the form template here.</p>
                <div class="wpcfb-palette">
                    <?php foreach ( $field_types as $type_key => $type_data ) : ?>
                        <button type="button" class="wpcfb-add-field" data-type="<?php echo esc_attr($type_key) ?>">
                            + <?php echo esc_html( $type_data['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <ul class="wpcfb-canvas" id="wpcfb-canvas"></ul>
                <textarea name="wpcfb-fields-json" id="wpcfb-fields-json"><?php echo esc_textarea( wp_json_encode( $existing_fields ) ); ?></textarea>
                
            </div>

            <div class="wpcfb-tab-panel" id="wpcfb-tab-settings">
                <p>Settings</p>
            </div>
        </div>
    <?php
}

// Add shortcode Column in Admin Forms Table
function wpcfb_add_shortcode_column( $columns ){
    $columns['wpcfb_shortcode'] = __( 'Shortcode' );
    return $columns;
}
add_filter( 'manage_wpcfb_form_posts_columns', 'wpcfb_add_shortcode_column' );

function wpcfb_render_shortcode_column( $column, $post_id ){
    if ( 'wpcfb_shortcode' === $column ) {
        echo '<code>[wpcfb_form id=\'' . esc_html( $post_id ) . '\']</code>';
    }
}
add_action( 'manage_wpcfb_form_posts_custom_column', 'wpcfb_render_shortcode_column', 10, 2 );


// Save Meta box
add_action( 'save_post_wpcfb_form', 'wpcfb_save_meta_box' );
function wpcfb_save_meta_box( $post_id ){

    if ( ! isset( $_POST['wpcfb_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['wpcfb_meta_box_nonce'], 'wpcfb_save_meta_box' ) ) {
        return;
    }

    // Temporary for testing
    // $test_fields = array(
    //     array('type' => 'text', 'label' => 'Full Name', 'name' => 'full_name', 'required' => true, 'placeholder' => 'Jane Doe'),
    //     array('type' => 'email', 'label' => 'Email', 'name' => 'email', 'required' => true ),
    //     array('type' => 'textarea', 'label' => 'Message', 'name' => 'message', 'required' => false)
    // );

    // update_post_meta($post_id, '_wpcfb_fields', $test_fields);

    if ( isset( $_POST['wpcfb-fields-json'] ) ){
        $decoded = json_decode( wp_unslash( $_POST['wpcfb-fields-json'] ), true );
        $fields = array();

        if ( is_array( $decoded ) ){
            foreach ( $decoded as $field ) {
                $fields[] = array(
                    'id'    => sanitize_text_field( $field['id'] ?? '' ),
                    'type'    => sanitize_key( $field['type'] ?? '' ),
                    'label'    => sanitize_text_field( $field['label'] ?? '' ),
                    'name'    => sanitize_key( $field['name'] ?? '' ),
                    'required'    => ! empty( $field['required'] ),
                    'placeholder'    => sanitize_text_field( $field['placeholder'] ?? '' ),
                );
            }
        }
        update_post_meta( $post_id, '_wpcfb_fields', $fields);
    }

    // if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
    //     return;
    // }

    // if ( ! current_user_can( 'edit_post', $post_id ) ) {
    //     return;
    // }

    // if ( ! isset( $_POST['wpcfb-form'] ) ) {
    //     return;
    // }

    // remove_action( 'save_post_wpcfb_form', 'wpcfb_save_meta_box' );

    // add_action( 'save_post_wpcfb_form', 'wpcfb_save_meta_box' );
}
