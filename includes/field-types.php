<?php
if( ! defined('ABSPATH')){
    exit;
}

// Registered field types, sorted by 'order' so the builder palette is predictable.
// Each type: label, render callback, order, and 'supports' (placeholder, options) which drives the builder UI.
function wpcfb_get_field_types(){
    $types = apply_filters( 'wpcfb_field_types', array() );
    foreach ( $types as $key => $type ) {
        $types[ $key ] = wp_parse_args( $type, array( 'order' => 100, 'supports' => array( 'placeholder' ) ) );
    }
    uasort( $types, function( $a, $b ){
        return $a['order'] <=> $b['order'];
    } );
    return $types;
}

function wpcfb_field_supports( $type, $feature ){
    $types = wpcfb_get_field_types();
    return isset( $types[ $type ] ) && in_array( $feature, (array) $types[ $type ]['supports'], true );
}

// Types where the visitor picks one option (value is a string) vs several (value is an array).
function wpcfb_is_multi_choice( $type ){
    return 'checkbox' === $type;
}

// Clean an options list: trimmed, non-empty, unique, capped at 100.
function wpcfb_sanitize_options( $options ){
    if ( is_string( $options ) ) {
        $options = preg_split( '/\r\n|\r|\n/', $options );
    }
    $clean = array();
    foreach ( (array) $options as $option ) {
        $option = trim( sanitize_text_field( (string) $option ) );
        if ( '' !== $option && ! in_array( $option, $clean, true ) ) {
            $clean[] = $option;
        }
    }
    return array_slice( $clean, 0, 100 );
}

// Format check for one submitted value. Returns an error message, or '' when valid.
// Runs before sanitising so a bad value is reported, not silently blanked. Required is checked separately.
function wpcfb_validate_field_value( $raw, $field ){
    $label = ! empty( $field['label'] ) ? $field['label'] : $field['name'];
    $type  = $field['type'];

    if ( wpcfb_field_supports( $type, 'options' ) ) {
        $options = (array) ( $field['options'] ?? array() );
        $picked  = wpcfb_is_multi_choice( $type ) ? (array) $raw : array( $raw );
        foreach ( $picked as $value ) {
            if ( ! is_string( $value ) ) {
                /* translators: %s: field label */
                return sprintf( __( '%s has an invalid choice.', 'wpc-form-builder' ), $label );
            }
            $value = trim( sanitize_text_field( $value ) );
            if ( '' !== $value && ! in_array( $value, $options, true ) ) {
                /* translators: %s: field label */
                return sprintf( __( 'Please choose a valid option for %s.', 'wpc-form-builder' ), $label );
            }
        }
        return '';
    }

    if ( ! is_string( $raw ) ) {
        /* translators: %s: field label */
        return sprintf( __( '%s is not valid.', 'wpc-form-builder' ), $label );
    }
    $raw = trim( $raw );
    if ( '' === $raw ) {
        return '';
    }

    if ( 'email' === $type && ! is_email( $raw ) ) {
        /* translators: %s: field label */
        return sprintf( __( '%s must be a valid email address.', 'wpc-form-builder' ), $label );
    }

    if ( 'phone' === $type ) {
        $digits = preg_replace( '/\D/', '', $raw );
        if ( ! preg_match( '/^[0-9 +().\-]+$/', $raw ) || strlen( $digits ) < 6 || strlen( $digits ) > 15 ) {
            /* translators: %s: field label */
            return sprintf( __( '%s must be a valid phone number.', 'wpc-form-builder' ), $label );
        }
    }

    return apply_filters( 'wpcfb_validate_field_value', '', $raw, $field );
}

// Shared markup: label text with the required marker.
function wpcfb_field_label_html( $field ){
    return esc_html( $field['label'] ) . ( ! empty( $field['required'] ) ? ' <span class="required">*</span>' : '' );
}

// Field widths as a percentage of the row. Fields flow left to right and wrap, so 50 + 50 is one row.
function wpcfb_field_widths(){
    return array(
        '100' => __( 'Full width', 'wpc-form-builder' ),
        '75'  => __( '3/4', 'wpc-form-builder' ),
        '66'  => __( '2/3', 'wpc-form-builder' ),
        '50'  => __( '1/2', 'wpc-form-builder' ),
        '33'  => __( '1/3', 'wpc-form-builder' ),
        '25'  => __( '1/4', 'wpc-form-builder' ),
    );
}

// Clean a field list from the builder. Used by save and preview, so both see exactly the same fields.
function wpcfb_sanitize_fields( $decoded ){
    $fields     = array();
    $used_names = array();
    $widths     = wpcfb_field_widths();

    foreach ( (array) $decoded as $field ) {
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

        $width = (string) ( $field['width'] ?? '100' );
        $type  = sanitize_key( $field['type'] ?? '' );

        $clean = array(
            'id'          => sanitize_text_field( $field['id'] ?? '' ),
            'type'        => $type,
            'label'       => sanitize_text_field( $field['label'] ?? '' ),
            'name'        => $unique_name,
            'required'    => ! empty( $field['required'] ),
            'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
            'width'       => isset( $widths[ $width ] ) ? $width : '100',
        );

        if ( wpcfb_field_supports( $type, 'options' ) ) {
            $clean['options'] = wpcfb_sanitize_options( $field['options'] ?? array() );
        }

        $fields[] = $clean;
    }
    return $fields;
}

// Render the field grid. Each field sits in a column sized by its width; columns wrap into rows.
function wpcfb_render_form_fields( $fields ){
    $types  = wpcfb_get_field_types();
    $widths = wpcfb_field_widths();

    ob_start();
    echo '<div class="wpcfb-fields">';
    foreach ( (array) $fields as $field ) {
        if ( ! isset( $field['type'], $types[ $field['type'] ]['render'] ) ) {
            continue;
        }
        // A dropdown/radio/checkbox with no options has nothing to pick, so leave it out.
        if ( in_array( 'options', (array) $types[ $field['type'] ]['supports'], true ) && empty( $field['options'] ) ) {
            continue;
        }
        $width = isset( $field['width'], $widths[ $field['width'] ] ) ? $field['width'] : '100';
        echo '<div class="wpcfb-col wpcfb-col--' . esc_attr( $width ) . '">';
        call_user_func( $types[ $field['type'] ]['render'], $field );
        echo '</div>';
    }
    echo '</div>';
    return ob_get_clean();
}

// Radio and checkbox groups: a fieldset with a legend, so screen readers announce the question with each option.
function wpcfb_render_choice_group( $field, $input_type ){
    $name     = 'checkbox' === $input_type ? $field['name'] . '[]' : $field['name'];
    $required = ! empty( $field['required'] ) && 'radio' === $input_type ? ' required' : '';

    $choices = '';
    foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
        $choices .= sprintf(
            '<label class="wpcfb-choice"><input type="%1$s" name="%2$s" value="%3$s"%4$s> <span>%5$s</span></label>',
            esc_attr( $input_type ),
            esc_attr( $name ),
            esc_attr( $option ),
            $required,
            esc_html( $option )
        );
    }

    printf(
        '<div class="wpcfb-field wpcfb-field--%1$s"><fieldset class="wpcfb-choices"><legend>%2$s</legend>%3$s</fieldset></div>',
        esc_attr( $input_type ),
        wpcfb_field_label_html( $field ),
        $choices // Escaped above.
    );
}
