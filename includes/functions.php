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
            'edit_item' => __('Edit Form', 'wpc-form-builder'),
            'singular_name' => __('Form', 'wpc-form-builder'),
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

function wpcfb_default_success_message(){
    return __( 'Thank you. Your message has been sent.', 'wpc-form-builder' );
}

// Per-form behaviour settings. Blank values fall back: form > global setting > built-in default.
function wpcfb_get_form_settings( $form_id ){
    $saved = get_post_meta( $form_id, '_wpcfb_form_settings', true );
    $saved = is_array( $saved ) ? $saved : array();

    return array(
        'button_label'    => (string) ( $saved['button_label'] ?? '' ),
        'success_message' => (string) ( $saved['success_message'] ?? '' ),
        'redirect_url'    => (string) ( $saved['redirect_url'] ?? '' ),
    );
}

function wpcfb_get_button_label( $form_id ){
    $label = wpcfb_get_form_settings( $form_id )['button_label'];
    return '' !== $label ? $label : __( 'Submit', 'wpc-form-builder' );
}

function wpcfb_get_success_message( $form_id ){
    $message = wpcfb_get_form_settings( $form_id )['success_message'];
    if ( '' === $message ) {
        $message = wpcfb_get_settings()['success_message'];
    }
    return '' !== $message ? $message : wpcfb_default_success_message();
}

// Success response. Also used for silently-blocked spam, so bots see exactly what a person would.
function wpcfb_success_payload( $form ){
    $payload = array(
        'message'    => wpcfb_get_success_message( $form->ID ),
        'form_id'    => $form->ID,
        'form_title' => $form->post_title,
    );

    $redirect = wpcfb_get_form_settings( $form->ID )['redirect_url'];
    if ( '' !== $redirect ) {
        // Relative paths are resolved against the site so "/thank-you" works on staging and live.
        $payload['redirect'] = 0 === strpos( $redirect, '/' ) && 0 !== strpos( $redirect, '//' ) ? home_url( $redirect ) : $redirect;
    }

    return apply_filters( 'wpcfb_success_payload', $payload, $form );
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
                <li class="wpcfb-tab-link" data-tab="preview"><?php esc_html_e( 'Preview', 'wpc-form-builder' ); ?></li>
                <li class="wpcfb-tab-link" data-tab="settings">Settings</li>
            </ul>

            <div class="wpcfb-tab-panel active" id="wpcfb-tab-form">
                <div class="wpcfb-tab-panel-header">
                    <h2>Form Builder</h2>
                    <p><?php esc_html_e( 'Add fields, drag to reorder, and click a field to edit it. Set a width to put fields side by side.', 'wpc-form-builder' ); ?></p>
                </div>
                <div class="wpcfb-tab-panel-body">
                    <div class="wpcfb-palette">
                        <span class="wpcfb-palette-label"><?php esc_html_e( 'Add field:', 'wpc-form-builder' ); ?></span>
                        <?php foreach ( $field_types as $type_key => $type_data ) : ?>
                            <button type="button" class="button wpcfb-add-field" data-type="<?php echo esc_attr($type_key) ?>">
                                + <?php echo esc_html( $type_data['label']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="wpcfb-builder">
                        <ul class="wpcfb-canvas" id="wpcfb-canvas"></ul>
                        <div class="wpcfb-field-settings" id="wpcfb-field-settings" aria-live="polite"></div>
                    </div>
                    <textarea name="wpcfb-fields-json" id="wpcfb-fields-json"><?php echo esc_textarea( wp_json_encode( $existing_fields ) ); ?></textarea>
                </div>
            </div>

            <div class="wpcfb-tab-panel" id="wpcfb-tab-preview">
                <div class="wpcfb-tab-panel-header">
                    <h2><?php esc_html_e( 'Preview', 'wpc-form-builder' ); ?></h2>
                    <p><?php esc_html_e( 'Shows unsaved changes. Your theme styles fonts and inputs on the live site, so it will look slightly different there.', 'wpc-form-builder' ); ?></p>
                </div>
                <div class="wpcfb-tab-panel-body">
                    <div class="wpcfb-preview" id="wpcfb-preview"></div>
                </div>
            </div>

            <div class="wpcfb-tab-panel" id="wpcfb-tab-settings">
                <div class="wpcfb-tab-panel-header">
                    <h2>Settings</h2>
                    <p>Edit the form settings here.</p>
                </div>
                <div class="wpcfb-tab-panel-body">
                    <?php
                    $form_settings  = wpcfb_get_form_settings( get_the_ID() );
                    $global_success = wpcfb_get_settings()['success_message'];
                    ?>
                    <h3 class="wpcfb-settings-heading wpcfb-settings-heading--first"><?php esc_html_e( 'Button', 'wpc-form-builder' ); ?></h3>
                    <p class="wpcfb-setting">
                        <label for="wpcfb-button-label"><?php esc_html_e( 'Button label', 'wpc-form-builder' ); ?></label>
                        <input type="text" class="regular-text" name="wpcfb-button-label" id="wpcfb-button-label" value="<?php echo esc_attr( $form_settings['button_label'] ); ?>" placeholder="<?php esc_attr_e( 'Submit', 'wpc-form-builder' ); ?>" maxlength="60">
                        <span class="description"><?php esc_html_e( 'Say what happens next, e.g. "Get my free quote" or "Book a call".', 'wpc-form-builder' ); ?></span>
                    </p>
                    <label for="wpcfb-btn-color">
                        Button Color
                        <input type="color" name="wpcfb-btn-color" id="wpcfb-btn-color" value="<?php echo esc_attr( $form_styles['btn_color'] ); ?>">
                    </label>
                    <br />
                    <label for="wpcfb-btn-text-color">
                        Button Text Color
                        <input type="color" name="wpcfb-btn-text-color" id="wpcfb-btn-text-color" value="<?php echo esc_attr( $form_styles['btn_text_color'] ); ?>">
                    </label>

                    <h3 class="wpcfb-settings-heading"><?php esc_html_e( 'After Submit', 'wpc-form-builder' ); ?></h3>
                    <p class="wpcfb-setting">
                        <label for="wpcfb-success-message"><?php esc_html_e( 'Success message', 'wpc-form-builder' ); ?></label>
                        <textarea class="large-text" rows="3" name="wpcfb-success-message" id="wpcfb-success-message" placeholder="<?php echo esc_attr( '' !== $global_success ? $global_success : wpcfb_default_success_message() ); ?>"><?php echo esc_textarea( $form_settings['success_message'] ); ?></textarea>
                        <span class="description">
                            <?php
                            /* translators: %s: link to the global settings page */
                            printf( wp_kses( __( 'Leave blank to use the <a href="%s">global message</a>.', 'wpc-form-builder' ), array( 'a' => array( 'href' => array() ) ) ), esc_url( admin_url( 'edit.php?post_type=wpcfb_form&page=wpcfb-settings' ) ) );
                            ?>
                        </span>
                    </p>
                    <p class="wpcfb-setting">
                        <label for="wpcfb-redirect-url"><?php esc_html_e( 'Redirect to', 'wpc-form-builder' ); ?></label>
                        <input type="text" class="regular-text" name="wpcfb-redirect-url" id="wpcfb-redirect-url" value="<?php echo esc_attr( $form_settings['redirect_url'] ); ?>" placeholder="/thank-you/">
                        <span class="description"><?php esc_html_e( 'Optional. Send people to a thank-you page instead of showing the message. Use a path like /thank-you/ so it works on staging and live. A thank-you page is the most reliable way to track conversions in GA4 and Google Ads.', 'wpc-form-builder' ); ?></span>
                    </p>

                    <?php
                    $notify          = wpcfb_get_form_notification( get_the_ID() );
                    $global_settings = wpcfb_get_settings();
                    $global_to       = '' !== $global_settings['notify_to'] ? $global_settings['notify_to'] : get_option( 'admin_email' );
                    ?>
                    <h3 class="wpcfb-settings-heading"><?php esc_html_e( 'Email Notifications', 'wpc-form-builder' ); ?></h3>
                    <label for="wpcfb-notify-enabled">
                        <input type="checkbox" name="wpcfb-notify-enabled" id="wpcfb-notify-enabled" value="1" <?php checked( $notify['enabled'] ); ?>>
                        <?php esc_html_e( 'Send an email for each submission', 'wpc-form-builder' ); ?>
                    </label>
                    <p class="wpcfb-setting">
                        <label for="wpcfb-notify-to"><?php esc_html_e( 'Send to', 'wpc-form-builder' ); ?></label>
                        <input type="text" class="regular-text" name="wpcfb-notify-to" id="wpcfb-notify-to" value="<?php echo esc_attr( $notify['to'] ); ?>" placeholder="<?php echo esc_attr( $global_to ); ?>">
                        <span class="description">
                            <?php
                            /* translators: %s: link to the global settings page */
                            printf( wp_kses( __( 'Separate multiple addresses with commas. Leave blank to use the <a href="%s">global setting</a>.', 'wpc-form-builder' ), array( 'a' => array( 'href' => array() ) ) ), esc_url( admin_url( 'edit.php?post_type=wpcfb_form&page=wpcfb-settings' ) ) );
                            ?>
                        </span>
                    </p>
                    <p class="wpcfb-setting">
                        <label for="wpcfb-notify-subject"><?php esc_html_e( 'Subject', 'wpc-form-builder' ); ?></label>
                        <input type="text" class="regular-text" name="wpcfb-notify-subject" id="wpcfb-notify-subject" value="<?php echo esc_attr( $notify['subject'] ); ?>" placeholder="<?php echo esc_attr( $global_settings['notify_subject'] ); ?>">
                        <span class="description"><?php esc_html_e( 'Leave blank to use the global subject. Placeholders: {form_title}, {site_name}', 'wpc-form-builder' ); ?></span>
                    </p>
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
        update_post_meta( $post_id, '_wpcfb_fields', wp_slash( wpcfb_sanitize_fields( $decoded ) ) );
    }

    if ( isset( $_POST['wpcfb-btn-color'] ) ){
        $formstyles = array(
            'btn_color'      => sanitize_hex_color( wp_unslash( $_POST['wpcfb-btn-color'] ) ),
            'btn_text_color' => sanitize_hex_color( wp_unslash( $_POST['wpcfb-btn-text-color'] ?? '' ) ),
        );
        update_post_meta( $post_id, '_wpcfb_style', $formstyles );

        // Notification settings live on the same tab, so save them alongside the styles.
        $invalid = array();
        $notify_to = wpcfb_parse_email_list( wp_unslash( $_POST['wpcfb-notify-to'] ?? '' ), $invalid );
        update_post_meta( $post_id, '_wpcfb_notify', array(
            'enabled' => ! empty( $_POST['wpcfb-notify-enabled'] ),
            'to'      => implode( ', ', $notify_to ),
            'subject' => wpcfb_sanitize_header_text( wp_unslash( $_POST['wpcfb-notify-subject'] ?? '' ) ),
        ) );

        if ( $invalid ) {
            /* translators: %s: list of invalid email addresses */
            wpcfb_add_save_notice( sprintf( __( 'These notification addresses were not valid and have been removed: %s', 'wpc-form-builder' ), implode( ', ', array_map( 'sanitize_text_field', $invalid ) ) ) );
        }

        $raw_redirect = trim( wp_unslash( $_POST['wpcfb-redirect-url'] ?? '' ) );
        $redirect     = wpcfb_sanitize_redirect_url( $raw_redirect );
        if ( '' !== $raw_redirect && '' === $redirect ) {
            /* translators: %s: the rejected URL */
            wpcfb_add_save_notice( sprintf( __( 'The redirect URL "%s" was not valid and has been removed. Use a path like /thank-you/ or a full https:// address.', 'wpc-form-builder' ), sanitize_text_field( $raw_redirect ) ) );
        }

        update_post_meta( $post_id, '_wpcfb_form_settings', wp_slash( array(
            'button_label'    => mb_substr( sanitize_text_field( wp_unslash( $_POST['wpcfb-button-label'] ?? '' ) ), 0, 60 ),
            'success_message' => sanitize_textarea_field( wp_unslash( $_POST['wpcfb-success-message'] ?? '' ) ),
            'redirect_url'    => $redirect,
        ) ) );
    }
}

// A site path ("/thank-you/") or an absolute http(s) URL. Anything else (javascript:, data:, "//evil.com") returns ''.
function wpcfb_sanitize_redirect_url( $url ){
    $url = trim( (string) $url );
    if ( '' === $url ) {
        return '';
    }

    if ( 0 === strpos( $url, '/' ) ) {
        // Check the cleaned value: esc_url_raw() strips characters, so "/\t/evil.com" becomes "//evil.com".
        // Browsers treat "//" and "/\" as another host.
        $clean = esc_url_raw( $url );
        return preg_match( '#^/(?![/\\\\])#', $clean ) ? $clean : '';
    }

    // Require an explicit scheme and host: esc_url_raw() passes protocol-relative "//host" through untouched.
    $parts = wp_parse_url( $url );
    if ( empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) || empty( $parts['host'] ) ) {
        return '';
    }
    return esc_url_raw( $url, array( 'http', 'https' ) );
}

// Queue a warning to show on the next admin page load (the save redirects before notices render).
function wpcfb_add_save_notice( $message ){
    $key      = 'wpcfb_save_notices_' . get_current_user_id();
    $notices  = (array) get_transient( $key );
    $notices[] = $message;
    set_transient( $key, array_filter( $notices ), 60 );
}

function wpcfb_render_save_notices(){
    $key     = 'wpcfb_save_notices_' . get_current_user_id();
    $notices = get_transient( $key );
    if ( ! $notices ) {
        return;
    }
    delete_transient( $key );
    foreach ( (array) $notices as $message ) {
        printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( $message ) );
    }
}
add_action( 'admin_notices', 'wpcfb_render_save_notices' );

// Builder preview: renders the unsaved builder state with the same code as the live form.
add_action( 'wp_ajax_wpcfb_preview', 'wpcfb_preview_callback' );
function wpcfb_preview_callback(){
    check_ajax_referer( 'wpcfb_preview', 'nonce' );

    $form_id = absint( $_POST['form_id'] ?? 0 );
    if ( ! $form_id || ! current_user_can( 'edit_post', $form_id ) ) {
        wp_send_json_error( array( 'message' => __( 'You are not allowed to preview this form.', 'wpc-form-builder' ) ), 403 );
    }

    $fields = wpcfb_sanitize_fields( json_decode( wp_unslash( $_POST['fields'] ?? '[]' ), true ) );
    $label  = sanitize_text_field( wp_unslash( $_POST['button_label'] ?? '' ) );
    $style  = array(
        'btn_color'      => sanitize_hex_color( wp_unslash( $_POST['btn_color'] ?? '' ) ) ?: '#2271b1',
        'btn_text_color' => sanitize_hex_color( wp_unslash( $_POST['btn_text_color'] ?? '' ) ) ?: '#ffffff',
    );

    // A div, not a form: the preview is injected inside the post edit <form>, and browsers drop nested forms.
    ob_start();
    echo '<div class="wpcfb-form wpcfb-form--preview">';
    if ( empty( $fields ) ) {
        echo '<p class="description">' . esc_html__( 'Add fields on the Form tab to see them here.', 'wpc-form-builder' ) . '</p>';
    }
    echo wpcfb_render_form_fields( $fields ); // Escaped by each field renderer.
    wpcfb_render_submit_button( '' !== $label ? $label : __( 'Submit', 'wpc-form-builder' ), $style, 'button' );
    echo '</div>';

    wp_send_json_success( array( 'html' => ob_get_clean() ) );
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
    // Checkbox groups send an array. Drop anything nested (a tampered request), then join the ticked options.
    if ( is_array( $value ) ) {
        $value = implode( ', ', array_map( 'sanitize_text_field', array_filter( $value, 'is_scalar' ) ) );
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

// Fresh nonce + "form started" token for the front end. Forms are page-cached, so anything printed into the HTML goes stale.
add_action( 'wp_ajax_wpcfb_get_nonce', 'wpcfb_get_nonce' );
add_action( 'wp_ajax_nopriv_wpcfb_get_nonce', 'wpcfb_get_nonce' );
function wpcfb_get_nonce(){
    wp_send_json_success( array(
        'nonce'   => wp_create_nonce( 'wpcfb_form_submit' ),
        'started' => wpcfb_create_timing_token(),
        'min_ms'  => wpcfb_min_submit_seconds() * 1000,
    ) );
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

    $spam = wpcfb_spam_check( $form_data, $form );
    if ( $spam ) {
        if ( ! empty( $spam['silent'] ) ) {
            wp_send_json_success( wpcfb_success_payload( $form ) );
        }
        wp_send_json_error( array( 'message' => $spam['message'], 'code' => $spam['code'] ), $spam['status'] );
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

        // Format checks (email, phone, allowed options) run on the raw input: sanitising would blank or alter bad values.
        $format_error = wpcfb_validate_field_value( $raw, $field );
        if ( '' !== $format_error ) {
            $errors[ $name ] = $format_error;
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
                    '_wpcfb_page_url' => esc_url_raw( (string) wp_get_raw_referer() ),
                ),
            )
        ),
        true
    );

    if ( is_wp_error( $submission_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Your message could not be saved. Please try again.', 'wpc-form-builder' ) ), 500 );
    }

    wpcfb_record_rate_limit_hit();

    do_action( 'wpcfb_submission_saved', $submission_id, $form->ID, $values );

    wp_send_json_success( wpcfb_success_payload( $form ) );
}
