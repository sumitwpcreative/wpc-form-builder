<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Per-form notification settings. Forms saved before this existed default to enabled.
function wpcfb_get_form_notification( $form_id ){
    $notify = get_post_meta( $form_id, '_wpcfb_notify', true );
    $notify = is_array( $notify ) ? $notify : array();
    return array(
        'enabled' => array_key_exists( 'enabled', $notify ) ? (bool) $notify['enabled'] : true,
        'to'      => (string) ( $notify['to'] ?? '' ),
        'subject' => (string) ( $notify['subject'] ?? '' ),
    );
}

// Recipients: form override, then global setting, then site admin email.
function wpcfb_get_notification_recipients( $form_id ){
    $notify   = wpcfb_get_form_notification( $form_id );
    $settings = wpcfb_get_settings();

    foreach ( array( $notify['to'], $settings['notify_to'], get_option( 'admin_email' ) ) as $candidate ) {
        $emails = wpcfb_parse_email_list( $candidate );
        if ( $emails ) {
            return apply_filters( 'wpcfb_notification_recipients', $emails, $form_id );
        }
    }
    return array();
}

function wpcfb_get_notification_subject( $form ){
    $notify   = wpcfb_get_form_notification( $form->ID );
    $settings = wpcfb_get_settings();
    $template = '' !== $notify['subject'] ? $notify['subject'] : $settings['notify_subject'];

    $subject = strtr( $template, array(
        '{form_title}' => $form->post_title,
        '{site_name}'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
    ) );
    return wpcfb_sanitize_header_text( $subject );
}

function wpcfb_get_notification_body( $form, $submission_id, $values ){
    $labels = array();
    foreach ( (array) get_post_meta( $form->ID, '_wpcfb_fields', true ) as $field ) {
        if ( ! empty( $field['name'] ) ) {
            $labels[ $field['name'] ] = ! empty( $field['label'] ) ? $field['label'] : $field['name'];
        }
    }

    $page_url = get_post_meta( $submission_id, '_wpcfb_page_url', true );
    $view_url = admin_url( 'post.php?post=' . absint( $submission_id ) . '&action=edit' );

    $rows = '';
    foreach ( $values as $name => $value ) {
        $rows .= sprintf(
            '<tr><th style="text-align:left;vertical-align:top;padding:8px 12px;border-bottom:1px solid #e5e5e5;width:180px;">%s</th><td style="padding:8px 12px;border-bottom:1px solid #e5e5e5;">%s</td></tr>',
            esc_html( $labels[ $name ] ?? $name ),
            '' !== $value ? nl2br( esc_html( $value ) ) : '&mdash;'
        );
    }

    ob_start();
    ?>
    <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:14px;color:#1d2327;max-width:640px;">
        <p style="font-size:16px;margin:0 0 16px;">
            <?php
            /* translators: %s: form title */
            printf( esc_html__( 'New submission from %s', 'wpc-form-builder' ), '<strong>' . esc_html( $form->post_title ) . '</strong>' );
            ?>
        </p>
        <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;border:1px solid #e5e5e5;">
            <?php echo $rows; // Escaped above. ?>
        </table>
        <p style="color:#646970;font-size:12px;margin:16px 0 0;">
            <?php if ( $page_url ) : ?>
                <?php esc_html_e( 'Submitted from:', 'wpc-form-builder' ); ?> <a href="<?php echo esc_url( $page_url ); ?>"><?php echo esc_html( $page_url ); ?></a><br>
            <?php endif; ?>
            <a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View this submission in WordPress', 'wpc-form-builder' ); ?></a>
        </p>
    </div>
    <?php
    return apply_filters( 'wpcfb_notification_body', ob_get_clean(), $form, $submission_id, $values );
}

// Send the notification once a submission is saved. Failures are logged on the submission, never shown to the visitor.
function wpcfb_send_notification( $submission_id, $form_id, $values ){
    $form = get_post( $form_id );
    if ( ! $form ) {
        return;
    }

    $notify = wpcfb_get_form_notification( $form_id );
    if ( ! $notify['enabled'] ) {
        update_post_meta( $submission_id, '_wpcfb_mail', array( 'status' => 'disabled', 'time' => time() ) );
        return;
    }

    $to = wpcfb_get_notification_recipients( $form_id );
    if ( ! $to ) {
        update_post_meta( $submission_id, '_wpcfb_mail', array( 'status' => 'failed', 'error' => 'No valid recipient', 'time' => time() ) );
        return;
    }

    $settings = wpcfb_get_settings();
    $headers  = array( 'Content-Type: text/html; charset=UTF-8' );

    // Only set From when configured, so SMTP plugins (WP Mail SMTP, Postmark) stay in control by default.
    if ( $settings['from_email'] ) {
        $from_name = '' !== $settings['from_name'] ? $settings['from_name'] : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $headers[] = sprintf( 'From: %s <%s>', $from_name, $settings['from_email'] );
    }

    $reply_to = wpcfb_get_submission_email( get_post( $submission_id ) );
    if ( $reply_to ) {
        $headers[] = 'Reply-To: ' . $reply_to;
    }

    $mail_error = '';
    $capture_error = function( $error ) use ( &$mail_error ) {
        $mail_error = $error->get_error_message();
    };
    add_action( 'wp_mail_failed', $capture_error );

    $sent = wp_mail(
        $to,
        wpcfb_get_notification_subject( $form ),
        wpcfb_get_notification_body( $form, $submission_id, $values ),
        apply_filters( 'wpcfb_notification_headers', $headers, $form_id, $submission_id )
    );

    remove_action( 'wp_mail_failed', $capture_error );

    update_post_meta( $submission_id, '_wpcfb_mail', array(
        'status' => $sent ? 'sent' : 'failed',
        'to'     => implode( ', ', $to ),
        'error'  => $sent ? '' : ( $mail_error ? $mail_error : 'wp_mail() returned false' ),
        'time'   => time(),
    ) );
}
add_action( 'wpcfb_submission_saved', 'wpcfb_send_notification', 10, 3 );
