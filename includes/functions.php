<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Register Post Type
function wpcfb_custom_post_type(){
    $wpcfbFormsArgs = array(
        'labels'        => array(
            'name'  => __('WPC Forms'),
            'all_items' => __('All Forms', 'wpc-form-builder'),
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
    register_post_type( 'wpcfb_form', $wpcfbFormsArgs );

    $wpcfbFormsSubmission = array(
        'labels'    => array(
            'name'          => __('WPC Submissions', 'wpc-form-builder'),
            'singular_name' => __('Submission', 'wpc-form-builder'),
            'menu_name'     => __('Submissions', 'wpc-form-builder'),
            'edit_item'     => __('View Submission', 'wpc-form-builder'),
            'not_found'     => __('No submissions found', 'wpc-form-builder'),
            'not_found_in_trash' => __('No submissions found in trash', 'wpc-form-builder'),
        ),
        'description'   => 'WPC Form Submissions',
        'public'    => false,
        'show_ui'   => true,
        'show_in_menu'=> 'edit.php?post_type=wpcfb_form',
        'exclude_from_search'=> true,
        // Read-only: no title/editor boxes, and entries only come from the front end.
        'supports'  => false,
        'capabilities' => array( 'create_posts' => 'do_not_allow' ),
        'map_meta_cap' => true,
    );
    register_post_type( 'wpcfb_submission', $wpcfbFormsSubmission );
}
add_action('init','wpcfb_custom_post_type');

// Get form style with defaults. Handles the legacy format: array( array( 'btnColor' => ..., 'btnTextColor' => ... ) )
function wpcfb_get_form_style( $post_id ){
    $defaults = array(
        'btn_color'      => '#2271b1',
        'btn_text_color' => '#ffffff',
    );

    $style = get_post_meta( $post_id, '_wpcfb_style', true );
    if ( ! is_array( $style ) ) {
        return $defaults;
    }

    if ( isset( $style[0] ) && is_array( $style[0] ) ) {
        $style = array(
            'btn_color'      => $style[0]['btnColor'] ?? '',
            'btn_text_color' => $style[0]['btnTextColor'] ?? '',
        );
    }

    foreach ( $defaults as $key => $default ) {
        $color = sanitize_hex_color( $style[ $key ] ?? '' );
        $defaults[ $key ] = $color ? $color : $default;
    }
    return $defaults;
}

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
    $form_styles = wpcfb_get_form_style( get_the_ID() );
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
                <div class="wpcfb-tab-panel-header">
                    <h2>Form Builder</h2>
                    <p>Edit the form template here.</p>
                </div>
                <div class="wpcfb-tab-panel-body">
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
                
            </div>

            <div class="wpcfb-tab-panel" id="wpcfb-tab-settings">
                <div class="wpcfb-tab-panel-header">
                    <h2>Settings</h2>
                    <p>Edit the form settings here.</p>
                </div>
                <div class="wpcfb-tab-panel-body">
                    <label for="wpcfb-btn-color">
                        Button Color
                        <input type="color" name="wpcfb-btn-color" id="wpcfb-btn-color" value="<?php echo esc_attr( $form_styles['btn_color'] ); ?>">
                    </label>
                    <br />
                    <label for="wpcfb-btn-text-color">
                        Button Text Color
                        <input type="color" name="wpcfb-btn-text-color" id="wpcfb-btn-text-color" value="<?php echo esc_attr( $form_styles['btn_text_color'] ); ?>">
                    </label>
                    
                </div>
            </div>
        </div>
    <?php
}

// function wpcfb_form_preview_callback(){
//     $post_id = get_the_ID(  );
//     // $atts   = shortcode_atts( array( 'id' => 0 ), $atts );
//     $fields = get_post_meta( $post_id, '_wpcfb_fields', true );
//     $types  = wpcfb_get_field_types();

//     ob_start();
//     echo '<form class="wpcfb-form" method="post">';
//     foreach ( (array) $fields as $field ) {
//         if ( isset( $field['type'], $types[ $field['type'] ]['render'] ) ) {
//             call_user_func( $types[ $field['type'] ]['render'], $field );
//         }
//     }
//     echo '<button type="button">Submit</button>';
//     echo '</form>';
//     echo ob_get_clean();
// }

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

    if ( ! isset( $_POST['wpcfb_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpcfb_meta_box_nonce'] ) ), 'wpcfb_save_meta_box' ) ) {
        return;
    }

    if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['wpcfb-fields-json'] ) ){
        $decoded = json_decode( wp_unslash( $_POST['wpcfb-fields-json'] ), true );
        $fields = array();
        $used_names = array();

        if ( is_array( $decoded ) ){
            foreach ( $decoded as $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }

                $name = sanitize_key( $field['name'] ?? '' );
                if ( '' === $name ) {
                    continue;
                }

                // Field names are submission keys, so they must be unique.
                $unique_name = $name;
                $suffix = 2;
                while ( in_array( $unique_name, $used_names, true ) ) {
                    $unique_name = $name . '_' . $suffix++;
                }
                $used_names[] = $unique_name;

                $fields[] = array(
                    'id'    => sanitize_text_field( $field['id'] ?? '' ),
                    'type'    => sanitize_key( $field['type'] ?? '' ),
                    'label'    => sanitize_text_field( $field['label'] ?? '' ),
                    'name'    => $unique_name,
                    'required'    => ! empty( $field['required'] ),
                    'placeholder'    => sanitize_text_field( $field['placeholder'] ?? '' ),
                );
            }
        }
        update_post_meta( $post_id, '_wpcfb_fields', wp_slash( $fields ) );
    }

    if ( isset( $_POST['wpcfb-btn-color'] ) ){
        $formstyles = array(
            'btn_color'      => sanitize_hex_color( wp_unslash( $_POST['wpcfb-btn-color'] ) ),
            'btn_text_color' => sanitize_hex_color( wp_unslash( $_POST['wpcfb-btn-text-color'] ?? '' ) ),
        );
        update_post_meta( $post_id, '_wpcfb_style', $formstyles );
    }
}

// Get a published form by ID, or null.
function wpcfb_get_form( $form_id ){
    $form = get_post( absint( $form_id ) );
    if ( ! $form || 'wpcfb_form' !== $form->post_type || 'publish' !== $form->post_status ) {
        return null;
    }
    return $form;
}

// Sanitise a submitted value based on its field type.
function wpcfb_sanitize_field_value( $value, $field ){
    if ( is_array( $value ) ) {
        $value = implode( ', ', array_map( 'strval', $value ) );
    }
    $value = (string) $value;

    switch ( $field['type'] ) {
        case 'email':
            $clean = sanitize_email( $value );
            break;
        case 'textarea':
            $clean = sanitize_textarea_field( $value );
            break;
        default:
            $clean = sanitize_text_field( $value );
    }

    return apply_filters( 'wpcfb_sanitize_field_value', $clean, $value, $field );
}

// Fresh nonce for the front end. Forms are page-cached, so a nonce printed into the HTML goes stale.
add_action( 'wp_ajax_wpcfb_get_nonce', 'wpcfb_get_nonce' );
add_action( 'wp_ajax_nopriv_wpcfb_get_nonce', 'wpcfb_get_nonce' );
function wpcfb_get_nonce(){
    wp_send_json_success( array( 'nonce' => wp_create_nonce( 'wpcfb_form_submit' ) ) );
}

// Handle Form Submission
add_action('wp_ajax_wpcfb_submit_form', 'wpcfb_handle_submission');
add_action('wp_ajax_nopriv_wpcfb_submit_form', 'wpcfb_handle_submission');

function wpcfb_handle_submission(){
    if ( ! check_ajax_referer( 'wpcfb_form_submit', 'wpcfb_form_submit_nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'wpc-form-builder' ) ), 403 );
    }

    $form_data = isset( $_POST['formData'] ) && is_array( $_POST['formData'] ) ? wp_unslash( $_POST['formData'] ) : array();

    $form = wpcfb_get_form( $form_data['wpcfb_form_id'] ?? 0 );
    if ( ! $form ) {
        wp_send_json_error( array( 'message' => __( 'This form is no longer available.', 'wpc-form-builder' ) ), 404 );
    }

    $fields = get_post_meta( $form->ID, '_wpcfb_fields', true );
    if ( ! is_array( $fields ) || empty( $fields ) ) {
        wp_send_json_error( array( 'message' => __( 'This form has no fields.', 'wpc-form-builder' ) ), 400 );
    }

    $values = array();
    $errors = array();
    $submission_title = '';

    foreach ( $fields as $field ) {
        if ( empty( $field['name'] ) || empty( $field['type'] ) ) {
            continue;
        }

        $name  = $field['name'];
        $label = ! empty( $field['label'] ) ? $field['label'] : $name;
        $raw   = $form_data[ $name ] ?? '';
        $value = wpcfb_sanitize_field_value( $raw, $field );

        // Validate email against the raw input: sanitize_email() blanks invalid addresses.
        if ( 'email' === $field['type'] && is_string( $raw ) && '' !== trim( $raw ) && ! is_email( trim( $raw ) ) ) {
            /* translators: %s: field label */
            $errors[ $name ] = sprintf( __( '%s must be a valid email address.', 'wpc-form-builder' ), $label );
            continue;
        }

        if ( ! empty( $field['required'] ) && '' === trim( $value ) ) {
            /* translators: %s: field label */
            $errors[ $name ] = sprintf( __( '%s is required.', 'wpc-form-builder' ), $label );
            continue;
        }

        if ( 'email' === $field['type'] && '' === $submission_title && '' !== $value ) {
            $submission_title = $value;
        }

        $values[ $name ] = $value;
    }

    if ( ! empty( $errors ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Please fix the highlighted fields.', 'wpc-form-builder' ),
                'errors'  => $errors,
            ),
            422
        );
    }

    if ( '' === $submission_title ) {
        $submission_title = $form->post_title . ' - ' . current_time( 'Y-m-d H:i' );
    }

    // wp_insert_post() unslashes its input, so slash it first. JSON_HEX_* keeps kses from touching the payload.
    $submission_id = wp_insert_post(
        wp_slash(
            array(
                'post_title'   => $submission_title,
                'post_content' => wp_json_encode( $values, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ),
                'post_status'  => 'publish',
                'post_type'    => 'wpcfb_submission',
                'meta_input'   => array(
                    '_wpcfb_form_id' => $form->ID,
                    '_wpcfb_data'    => $values,
                ),
            )
        ),
        true
    );

    if ( is_wp_error( $submission_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Your message could not be saved. Please try again.', 'wpc-form-builder' ) ), 500 );
    }

    do_action( 'wpcfb_submission_saved', $submission_id, $form->ID, $values );

    wp_send_json_success( array( 'message' => __( 'Thank you. Your message has been sent.', 'wpc-form-builder' ) ) );
}
