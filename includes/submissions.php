<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Submission values as array( name => value ). Older entries stored post_content as [ { name: value }, ... ] with no meta.
function wpcfb_get_submission_values( $submission ){
    $values = get_post_meta( $submission->ID, '_wpcfb_data', true );
    if ( is_array( $values ) ) {
        return $values;
    }

    $values  = array();
    $decoded = json_decode( $submission->post_content, true );
    foreach ( (array) $decoded as $key => $item ) {
        if ( is_array( $item ) ) {
            $values = array_merge( $values, $item );
        } else {
            $values[ $key ] = $item;
        }
    }
    return $values;
}

// Read-only Submission Details Meta Box
function wpcfb_submission_meta_boxes(){
    add_meta_box(
        'wpcfb_submission_details',
        __( 'Submission Details', 'wpc-form-builder' ),
        'wpcfb_submission_meta_box_callback',
        'wpcfb_submission',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes_wpcfb_submission', 'wpcfb_submission_meta_boxes' );

function wpcfb_submission_meta_box_callback( $submission ){
    $form_id = (int) get_post_meta( $submission->ID, '_wpcfb_form_id', true );
    $form    = $form_id ? get_post( $form_id ) : null;
    $values  = wpcfb_get_submission_values( $submission );

    // Map field names to their current labels on the form.
    $labels = array();
    foreach ( (array) ( $form_id ? get_post_meta( $form_id, '_wpcfb_fields', true ) : array() ) as $field ) {
        if ( ! empty( $field['name'] ) ) {
            $labels[ $field['name'] ] = ! empty( $field['label'] ) ? $field['label'] : $field['name'];
        }
    }
    ?>
    <table class="widefat striped wpcfb-submission-table">
        <tbody>
            <tr>
                <th scope="row"><?php esc_html_e( 'Form', 'wpc-form-builder' ); ?></th>
                <td>
                    <?php if ( $form ) : ?>
                        <a href="<?php echo esc_url( get_edit_post_link( $form->ID ) ); ?>"><?php echo esc_html( $form->post_title ); ?></a>
                    <?php else : ?>
                        <?php esc_html_e( 'Unknown', 'wpc-form-builder' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Submitted', 'wpc-form-builder' ); ?></th>
                <td><?php echo esc_html( get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $submission ) ); ?></td>
            </tr>
            <?php foreach ( $values as $name => $value ) : ?>
                <tr>
                    <th scope="row"><?php echo esc_html( $labels[ $name ] ?? $name ); ?></th>
                    <td><?php echo nl2br( esc_html( $value ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ( empty( $values ) ) : ?>
                <tr><td colspan="2"><?php esc_html_e( 'No data was submitted.', 'wpc-form-builder' ); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

// First valid email in a submission, or ''.
function wpcfb_get_submission_email( $submission ){
    foreach ( wpcfb_get_submission_values( $submission ) as $value ) {
        if ( is_string( $value ) && is_email( $value ) ) {
            return $value;
        }
    }
    return '';
}

// Replace the Publish/Update box with Trash + Back, since there is nothing to edit.
function wpcfb_submission_actions_meta_box(){
    remove_meta_box( 'submitdiv', 'wpcfb_submission', 'side' );
    add_meta_box(
        'wpcfb_submission_actions',
        __( 'Actions', 'wpc-form-builder' ),
        'wpcfb_submission_actions_meta_box_callback',
        'wpcfb_submission',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes_wpcfb_submission', 'wpcfb_submission_actions_meta_box' );

function wpcfb_submission_actions_meta_box_callback( $submission ){
    ?>
    <p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpcfb_submission' ) ); ?>">&larr; <?php esc_html_e( 'Back to submissions', 'wpc-form-builder' ); ?></a></p>
    <?php if ( current_user_can( 'delete_post', $submission->ID ) ) : ?>
        <p><a class="submitdelete" style="color:#b32d2e" href="<?php echo esc_url( get_delete_post_link( $submission->ID ) ); ?>"><?php esc_html_e( 'Move to Trash', 'wpc-form-builder' ); ?></a></p>
    <?php endif; ?>
    <?php
}

// Submissions list columns
function wpcfb_submission_columns( $columns ){
    return array(
        'cb'          => $columns['cb'],
        'title'       => __( 'Submission', 'wpc-form-builder' ),
        'wpcfb_form'  => __( 'Form', 'wpc-form-builder' ),
        'wpcfb_email' => __( 'Email', 'wpc-form-builder' ),
        'date'        => __( 'Date', 'wpc-form-builder' ),
    );
}
add_filter( 'manage_wpcfb_submission_posts_columns', 'wpcfb_submission_columns' );

function wpcfb_render_submission_column( $column, $post_id ){
    $submission = get_post( $post_id );

    if ( 'wpcfb_form' === $column ) {
        $form_id = (int) get_post_meta( $post_id, '_wpcfb_form_id', true );
        $form    = $form_id ? get_post( $form_id ) : null;
        if ( $form ) {
            printf(
                '<a href="%s">%s</a>',
                esc_url( add_query_arg( array( 'post_type' => 'wpcfb_submission', 'wpcfb_form_id' => $form->ID ), admin_url( 'edit.php' ) ) ),
                esc_html( $form->post_title )
            );
        } else {
            echo '&mdash;';
        }
    }

    if ( 'wpcfb_email' === $column ) {
        $email = wpcfb_get_submission_email( $submission );
        echo $email ? '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>' : '&mdash;';
    }
}
add_action( 'manage_wpcfb_submission_posts_custom_column', 'wpcfb_render_submission_column', 10, 2 );

// Filter submissions by form
function wpcfb_submission_form_filter( $post_type ){
    if ( 'wpcfb_submission' !== $post_type ) {
        return;
    }

    $forms    = get_posts( array( 'post_type' => 'wpcfb_form', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
    $selected = isset( $_GET['wpcfb_form_id'] ) ? absint( $_GET['wpcfb_form_id'] ) : 0;
    ?>
    <label for="wpcfb-form-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by form', 'wpc-form-builder' ); ?></label>
    <select name="wpcfb_form_id" id="wpcfb-form-filter">
        <option value="0"><?php esc_html_e( 'All forms', 'wpc-form-builder' ); ?></option>
        <?php foreach ( $forms as $form ) : ?>
            <option value="<?php echo esc_attr( $form->ID ); ?>" <?php selected( $selected, $form->ID ); ?>><?php echo esc_html( $form->post_title ); ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}
add_action( 'restrict_manage_posts', 'wpcfb_submission_form_filter' );

function wpcfb_filter_submissions_by_form( $query ){
    if ( ! is_admin() || ! $query->is_main_query() || 'wpcfb_submission' !== $query->get( 'post_type' ) || empty( $_GET['wpcfb_form_id'] ) ) {
        return;
    }

    $query->set( 'meta_query', array(
        array(
            'key'   => '_wpcfb_form_id',
            'value' => absint( $_GET['wpcfb_form_id'] ),
        ),
    ) );
}
add_action( 'pre_get_posts', 'wpcfb_filter_submissions_by_form' );

// Row actions: "View" instead of Edit, and no Quick Edit.
function wpcfb_submission_row_actions( $actions, $post ){
    if ( 'wpcfb_submission' !== $post->post_type ) {
        return $actions;
    }

    unset( $actions['edit'], $actions['inline hide-if-no-js'] );
    if ( 'trash' !== $post->post_status ) {
        $actions = array( 'view' => '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'View', 'wpc-form-builder' ) . '</a>' ) + $actions;
    }
    return $actions;
}
add_filter( 'post_row_actions', 'wpcfb_submission_row_actions', 10, 2 );

function wpcfb_submission_bulk_actions( $actions ){
    unset( $actions['edit'] );
    return $actions;
}
add_filter( 'bulk_actions-edit-wpcfb_submission', 'wpcfb_submission_bulk_actions' );

// Submission count on the forms list, linked to the filtered submissions list.
function wpcfb_add_submissions_count_column( $columns ){
    $columns['wpcfb_submissions'] = __( 'Submissions', 'wpc-form-builder' );
    return $columns;
}
add_filter( 'manage_wpcfb_form_posts_columns', 'wpcfb_add_submissions_count_column', 20 );

function wpcfb_render_submissions_count_column( $column, $post_id ){
    if ( 'wpcfb_submissions' !== $column ) {
        return;
    }

    $query = new WP_Query( array(
        'post_type'      => 'wpcfb_submission',
        'post_status'    => 'publish',
        'meta_key'       => '_wpcfb_form_id',
        'meta_value'     => $post_id,
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'no_found_rows'  => false,
    ) );

    printf(
        '<a href="%s">%d</a>',
        esc_url( add_query_arg( array( 'post_type' => 'wpcfb_submission', 'wpcfb_form_id' => $post_id ), admin_url( 'edit.php' ) ) ),
        (int) $query->found_posts
    );
}
add_action( 'manage_wpcfb_form_posts_custom_column', 'wpcfb_render_submissions_count_column', 10, 2 );
