<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Incluir funciones necesarias
require_once plugin_dir_path(__FILE__) . 'contexto-functions.php';

// AJAX para guardar estado del contexto
add_action( 'wp_ajax_aichat_toggle_rag', 'aichat_toggle_rag' );
function aichat_toggle_rag() {
    check_ajax_referer( 'aichat_nonce', 'nonce' );
    $enabled = rest_sanitize_boolean( $_POST['enabled'] );
    update_option( 'aichat_rag_enabled', $enabled );
    wp_send_json_success( [ 'enabled' => $enabled ] );
}

// AJAX para actualizar el contexto activo
add_action( 'wp_ajax_aichat_update_active_context', 'aichat_update_active_context' );
function aichat_update_active_context() {
    check_ajax_referer( 'aichat_nonce', 'nonce' );
    $context_id = absint( $_POST['context_id'] );
    update_option( 'aichat_active_context', $context_id );
    wp_send_json_success();
}

// AJAX para cargar contextos
add_action( 'wp_ajax_aichat_load_contexts', 'aichat_load_contexts' );
function aichat_load_contexts() {
    check_ajax_referer( 'aichat_nonce', 'nonce' );
    global $wpdb;
    $contexts = $wpdb->get_results( "SELECT id, name, processing_progress FROM {$wpdb->prefix}aichat_contexts", ARRAY_A );
    wp_send_json_success( [ 'contexts' => $contexts ] );
}

// AJAX para actualizar nombre del contexto
add_action( 'wp_ajax_aichat_update_context_name', 'aichat_update_context_name' );
function aichat_update_context_name() {
    check_ajax_referer( 'aichat_nonce', 'nonce' );
    global $wpdb;
    $id = absint( $_POST['id'] );
    $name = sanitize_text_field( $_POST['name'] );
    $result = $wpdb->update(
        $wpdb->prefix . 'aichat_contexts',
        [ 'name' => $name ],
        [ 'id' => $id ],
        [ '%s' ],
        [ '%d' ]
    );
    if ( $result !== false ) {
        wp_send_json_success();
    } else {
        wp_send_json_error( [ 'message' => 'Failed to update context name.' ] );
    }
}

// AJAX para eliminar contexto
add_action( 'wp_ajax_aichat_delete_context', 'aichat_delete_context' );
function aichat_delete_context() {
    check_ajax_referer( 'aichat_nonce', 'nonce' );
    global $wpdb;
    $id = absint( $_POST['id'] );
    $result = $wpdb->delete( $wpdb->prefix . 'aichat_contexts', [ 'id' => $id ], [ '%d' ] );
    if ( $result !== false ) {
        $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}aichat_chunks SET id_context = 0 WHERE id_context = %d", $id ) );
        wp_send_json_success();
    } else {
        wp_send_json_error( [ 'message' => 'Failed to delete context.' ] );
    }
}

// AJAX para actualizar el progreso
add_action( 'wp_ajax_aichat_update_progress', 'aichat_update_progress' );
function aichat_update_progress() {
    check_ajax_referer( 'aichat_nonce', 'nonce' );
    $context_id = absint( $_POST['context_id'] );
    global $wpdb;
    $context = $wpdb->get_row( $wpdb->prepare( "SELECT processing_progress FROM {$wpdb->prefix}aichat_contexts WHERE id = %d", $context_id ), ARRAY_A );
    if ($context) {
        wp_send_json_success( [ 'progress' => $context['processing_progress'] ] );
    } else {
        wp_send_json_error( [ 'message' => 'Context not found' ] );
    }
}