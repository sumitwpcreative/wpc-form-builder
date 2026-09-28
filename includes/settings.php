<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Global settings, merged with defaults.
function wpcfb_get_settings(){
    $defaults = array(
        'notify_to'      => '',
        'notify_subject' => 'New submission: {form_title}',
        'from_name'      => '',
        'from_email'     => '',
        'rate_limit'           => 10,
        'turnstile_site_key'   => '',
        'turnstile_secret_key' => '',
    );
    $settings = get_option( 'wpcfb_settings', array() );
    return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
}

// Split a comma/newline separated list into valid emails. Invalid entries are returned via $invalid.
function wpcfb_parse_email_list( $raw, &$invalid = array() ){
    $valid   = array();
    $invalid = array();
    foreach ( preg_split( '/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY ) as $email ) {
        $clean = sanitize_email( $email );
        if ( $clean && is_email( $clean ) ) {
            $valid[] = $clean;
        } else {
            $invalid[] = $email;
        }
    }
    return array_values( array_unique( $valid ) );
}

// Strip line breaks so user-entered values can never inject mail headers.
function wpcfb_sanitize_header_text( $text ){
    return trim( sanitize_text_field( str_replace( array( "\r", "\n" ), ' ', (string) $text ) ) );
}

// Settings Page
function wpcfb_settings_menu(){
    add_submenu_page(
        'edit.php?post_type=wpcfb_form',
        __( 'WPC Forms Settings', 'wpc-form-builder' ),
        __( 'Settings', 'wpc-form-builder' ),
        'manage_options',
        'wpcfb-settings',
        'wpcfb_settings_page_callback'
    );
}
add_action( 'admin_menu', 'wpcfb_settings_menu' );

function wpcfb_register_settings(){
    register_setting( 'wpcfb_settings', 'wpcfb_settings', array(
        'type'              => 'array',
        'sanitize_callback' => 'wpcfb_sanitize_settings',
        'default'           => array(),
    ) );
}
add_action( 'admin_init', 'wpcfb_register_settings' );

// add_settings_error() only exists in wp-admin; the option can also be updated from WP-CLI, cron or other plugins.
function wpcfb_settings_error( $setting, $code, $message ){
    if ( function_exists( 'add_settings_error' ) ) {
        add_settings_error( $setting, $code, $message );
    }
}

function wpcfb_sanitize_settings( $input ){
    $input = is_array( $input ) ? $input : array();

    $invalid   = array();
    $notify_to = wpcfb_parse_email_list( $input['notify_to'] ?? '', $invalid );
    if ( $invalid ) {
        wpcfb_settings_error(
            'wpcfb_settings',
            'wpcfb_invalid_notify_to',
            /* translators: %s: list of invalid email addresses */
            sprintf( __( 'These addresses were not valid and have been removed: %s', 'wpc-form-builder' ), implode( ', ', array_map( 'sanitize_text_field', $invalid ) ) )
        );
    }

    $from_email = sanitize_email( $input['from_email'] ?? '' );
    if ( '' !== trim( (string) ( $input['from_email'] ?? '' ) ) && ! is_email( $from_email ) ) {
        wpcfb_settings_error( 'wpcfb_settings', 'wpcfb_invalid_from_email', __( 'The From email was not valid and has been cleared.', 'wpc-form-builder' ) );
        $from_email = '';
    }

    $subject = wpcfb_sanitize_header_text( $input['notify_subject'] ?? '' );

    return array(
        'notify_to'      => implode( ', ', $notify_to ),
        'notify_subject' => '' !== $subject ? $subject : 'New submission: {form_title}',
        'from_name'      => wpcfb_sanitize_header_text( $input['from_name'] ?? '' ),
        'from_email'     => $from_email,
        'rate_limit'           => max( 0, min( 1000, (int) ( $input['rate_limit'] ?? 10 ) ) ),
        'turnstile_site_key'   => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $input['turnstile_site_key'] ?? '' ) ),
        'turnstile_secret_key' => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $input['turnstile_secret_key'] ?? '' ) ),
    );
}

function wpcfb_settings_page_callback(){
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $settings = wpcfb_get_settings();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'WPC Forms Settings', 'wpc-form-builder' ); ?></h1>
        <?php settings_errors(); // No filter, so the core "Settings saved." notice shows too. ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'wpcfb_settings' ); ?>
            <h2><?php esc_html_e( 'Email Notifications', 'wpc-form-builder' ); ?></h2>
            <p><?php esc_html_e( 'These apply to every form. A form can override the recipients and subject in its own Settings tab.', 'wpc-form-builder' ); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="wpcfb-notify-to"><?php esc_html_e( 'Send notifications to', 'wpc-form-builder' ); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="wpcfb-notify-to" name="wpcfb_settings[notify_to]" value="<?php echo esc_attr( $settings['notify_to'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                        <p class="description">
                            <?php
                            /* translators: %s: site admin email */
                            printf( esc_html__( 'Separate multiple addresses with commas. Leave blank to use the site admin email (%s).', 'wpc-form-builder' ), esc_html( get_option( 'admin_email' ) ) );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpcfb-notify-subject"><?php esc_html_e( 'Subject', 'wpc-form-builder' ); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="wpcfb-notify-subject" name="wpcfb_settings[notify_subject]" value="<?php echo esc_attr( $settings['notify_subject'] ); ?>">
                        <p class="description"><?php esc_html_e( 'Placeholders: {form_title}, {site_name}', 'wpc-form-builder' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpcfb-from-name"><?php esc_html_e( 'From name', 'wpc-form-builder' ); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="wpcfb-from-name" name="wpcfb_settings[from_name]" value="<?php echo esc_attr( $settings['from_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpcfb-from-email"><?php esc_html_e( 'From email', 'wpc-form-builder' ); ?></label></th>
                    <td>
                        <input type="email" class="regular-text" id="wpcfb-from-email" name="wpcfb_settings[from_email]" value="<?php echo esc_attr( $settings['from_email'] ); ?>">
                        <p class="description"><?php esc_html_e( 'Leave blank to use your SMTP plugin or WordPress default. If set, use an address on your own domain or emails may land in spam.', 'wpc-form-builder' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Spam Protection', 'wpc-form-builder' ); ?></h2>
            <p><?php esc_html_e( 'A hidden honeypot field and a minimum fill time are always on. Add Cloudflare Turnstile for stronger protection.', 'wpc-form-builder' ); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="wpcfb-rate-limit"><?php esc_html_e( 'Submissions per hour', 'wpc-form-builder' ); ?></label></th>
                    <td>
                        <input type="number" class="small-text" min="0" max="1000" id="wpcfb-rate-limit" name="wpcfb_settings[rate_limit]" value="<?php echo esc_attr( $settings['rate_limit'] ); ?>">
                        <p class="description"><?php esc_html_e( 'Maximum submissions from one IP address per hour, across all forms. 0 turns the limit off.', 'wpc-form-builder' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpcfb-turnstile-site-key"><?php esc_html_e( 'Turnstile site key', 'wpc-form-builder' ); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="wpcfb-turnstile-site-key" name="wpcfb_settings[turnstile_site_key]" value="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpcfb-turnstile-secret-key"><?php esc_html_e( 'Turnstile secret key', 'wpc-form-builder' ); ?></label></th>
                    <td>
                        <input type="password" class="regular-text" id="wpcfb-turnstile-secret-key" name="wpcfb_settings[turnstile_secret_key]" value="<?php echo esc_attr( $settings['turnstile_secret_key'] ); ?>" autocomplete="new-password">
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: Cloudflare dashboard URL */
                                wp_kses( __( 'Get free keys from the <a href="%s" target="_blank" rel="noopener">Cloudflare dashboard</a>. Turnstile is active on all forms once both keys are set.', 'wpc-form-builder' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ),
                                esc_url( 'https://dash.cloudflare.com/?to=/:account/turnstile' )
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
