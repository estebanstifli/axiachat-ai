<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Incluir funciones necesarias
require_once plugin_dir_path(__FILE__) . 'contexto-functions.php';

// AJAX para cargar contextos
add_action( 'wp_ajax_aichat_load_contexts', 'aichat_load_contexts' );
function aichat_load_contexts() {
    check_ajax_referer( 'aichat_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message'=>'Forbidden'],403);
    }
    global $wpdb;
    $table_ctx = $wpdb->prefix . 'aichat_contexts';
    $table_chunks = $wpdb->prefix . 'aichat_chunks';
    // Traer todos los campos necesarios para reconstruir la tabla en JS
    $sql = "SELECT c.id, c.name, c.processing_progress, c.processing_status, c.created_at, c.autosync, c.autosync_mode,
                   (SELECT COUNT(*) FROM $table_chunks ch WHERE ch.id_context = c.id) AS chunk_count,
                   (SELECT COUNT(DISTINCT post_id) FROM $table_chunks ch2 WHERE ch2.id_context = c.id) AS post_count
            FROM $table_ctx c ORDER BY c.id ASC";
    $contexts = $wpdb->get_results( $sql, ARRAY_A );
    if ( ! $contexts ) { $contexts = []; }
    wp_send_json_success( [ 'contexts' => $contexts ] );
}

// AJAX para actualizar nombre del contexto
add_action( 'wp_ajax_aichat_update_context_name', 'aichat_update_context_name' );
function aichat_update_context_name() {
    check_ajax_referer( 'aichat_nonce', 'nonce' );
    global $wpdb;
    $id = absint( $_POST['id'] );
    $name = sanitize_text_field( $_POST['name'] );
    $data = [ 'name' => $name ];
    $formats = [ '%s' ];

    // Opcionales: autosync settings si vienen en la petición
    if ( isset($_POST['autosync']) ) {
        $autosync = (int)$_POST['autosync'] ? 1 : 0;
        $data['autosync'] = $autosync; $formats[] = '%d';
    }
    if ( isset($_POST['autosync_mode']) ) {
        $mode = sanitize_text_field($_POST['autosync_mode']);
        if (! in_array($mode, ['updates','updates_and_new'], true) ) {
            $mode = 'updates';
        }
        $data['autosync_mode'] = $mode; $formats[] = '%s';
    }
    $result = $wpdb->update(
        $wpdb->prefix . 'aichat_contexts',
        $data,
        [ 'id' => $id ],
        $formats,
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

// AJAX: búsqueda semántica de prueba dentro de un contexto
add_action( 'wp_ajax_aichat_search_context_chunks', 'aichat_search_context_chunks' );
function aichat_search_context_chunks(){
    check_ajax_referer( 'aichat_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message'=>'Forbidden'], 403);
    }
    $context_id = isset($_POST['context_id']) ? absint($_POST['context_id']) : 0;
    $query      = isset($_POST['q']) ? trim( sanitize_text_field( wp_unslash($_POST['q']) ) ) : '';
    $limit      = isset($_POST['limit']) ? max(1, min(20, absint($_POST['limit']))) : 10;
    if ($context_id <= 0) {
        wp_send_json_error(['message'=>'Missing context_id']);
    }
    if ($query === '') {
        wp_send_json_error(['message'=>'Empty query']);
    }
    // Generar embedding de la consulta
    $q_embed = aichat_generate_embedding( $query );
    if ( ! $q_embed ) {
        wp_send_json_error(['message'=>'Embedding failed']);
    }
    global $wpdb; $table = $wpdb->prefix.'aichat_chunks';
    $rows = $wpdb->get_results( $wpdb->prepare("SELECT post_id, title, content, embedding, type FROM $table WHERE id_context=%d", $context_id), ARRAY_A );
    if ( ! $rows ) { wp_send_json_success(['results'=>[]]); }
    $scored = [];
    foreach($rows as $r){
        $emb = json_decode($r['embedding'], true);
        if (!is_array($emb)) continue;
        $score = aichat_cosine_similarity($q_embed, $emb);
        $snippet = mb_substr( wp_strip_all_tags($r['content']), 0, 240 );
        $scored[] = [
            'post_id' => (int)$r['post_id'],
            'title'   => (string)$r['title'],
            'type'    => isset($r['type']) ? (string)$r['type'] : '',
            'score'   => round($score, 6),
            'excerpt' => $snippet . (strlen($r['content'])>240 ? '…' : ''),
        ];
    }
    usort($scored, function($a,$b){ return ($b['score']<=>$a['score']); });
    $scored = array_slice($scored, 0, $limit);
    wp_send_json_success(['results'=>$scored, 'query'=>$query]);
}

// AJAX: obtener metadatos del contexto (para panel de edición/test)
add_action('wp_ajax_aichat_get_context_meta','aichat_get_context_meta');
function aichat_get_context_meta(){
    check_ajax_referer('aichat_nonce','nonce');
    if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'Forbidden'],403); }
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if ($id<=0) { wp_send_json_error(['message'=>'Missing id']); }
    global $wpdb; $ctx_table = $wpdb->prefix.'aichat_contexts'; $chunks_table = $wpdb->prefix.'aichat_chunks';
    $row = $wpdb->get_row( $wpdb->prepare("SELECT id, name, context_type, remote_type, created_at, processing_status, processing_progress, autosync, autosync_mode, autosync_post_types FROM $ctx_table WHERE id=%d", $id), ARRAY_A );
    if ( ! $row ) { wp_send_json_error(['message'=>'Not found']); }
    $chunk_count = (int)$wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $chunks_table WHERE id_context=%d", $id) );
    // Conteo posts únicos
    $post_count = (int)$wpdb->get_var( $wpdb->prepare("SELECT COUNT(DISTINCT post_id) FROM $chunks_table WHERE id_context=%d", $id) );
    $row['chunk_count'] = $chunk_count;
    $row['post_count']  = $post_count;
    wp_send_json_success(['context'=>$row]);
}