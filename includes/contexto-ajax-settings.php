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
    $sql = "SELECT c.id, c.name, c.context_type, c.processing_progress, c.processing_status, c.created_at, c.autosync, c.autosync_mode,
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

// AJAX: Run AutoSync Now (manual trigger)
add_action('wp_ajax_aichat_autosync_run_now','aichat_autosync_run_now');
function aichat_autosync_run_now(){
    check_ajax_referer('aichat_nonce','nonce');
    if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'Forbidden'],403); }
    global $wpdb;
    $ctx_id = isset($_POST['context_id']) ? absint($_POST['context_id']) : 0;
    $mode_req = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'modified';
    if ($ctx_id<=0) { wp_send_json_error(['message'=>'Missing context_id']); }
    $table_ctx = $wpdb->prefix.'aichat_contexts';
    $row = $wpdb->get_row($wpdb->prepare("SELECT id, context_type, autosync, autosync_mode, autosync_post_types, items_to_process, processing_status FROM $table_ctx WHERE id=%d", $ctx_id), ARRAY_A);
    if(!$row){ wp_send_json_error(['message'=>'Context not found']); }
    if($row['context_type'] !== 'local'){ wp_send_json_error(['message'=>'Only local contexts supported']); }

    $types_csv = trim((string)$row['autosync_post_types']);
    $post_types = [];
    if($types_csv!==''){
        foreach(explode(',',$types_csv) as $t){ $t=trim($t); if($t!=='') $post_types[]=$t; }
    }
    if(empty($post_types)){ $post_types=['ALL_POSTS']; }
    $limited = ($types_csv==='LIMITED');

    // Effective mode resolution
    $effective = 'modified';
    if($mode_req==='full') { $effective='full'; }
    elseif($mode_req==='modified_and_new' && !$limited && $row['autosync_mode']==='updates_and_new'){ $effective='modified_and_new'; }

    $current_queue = maybe_unserialize($row['items_to_process']);
    if(!is_array($current_queue)) $current_queue=[];

    $modified=[]; $new=[]; $orphans=[]; $full_ids=[];
    $added_ids=[];

    // Build actual WP post_types list for queries
    $wp_types=[]; // map ALL_* tokens to real post_types
    foreach($post_types as $tk){
        switch($tk){
            case 'ALL_POSTS': $wp_types[]='post'; break;
            case 'ALL_PAGES': $wp_types[]='page'; break;
            case 'ALL_PRODUCTS': $wp_types[]='product'; break;
            case 'ALL_UPLOADED': /* handled specially below for uploads converted to chunks already*/ break;
        }
    }
    if(empty($wp_types)) { $wp_types=['post']; }
    // Construir lista de placeholders segura para post_type IN()
    $placeholders_types = implode(',', array_fill(0, count($wp_types), '%s'));

    // Queries similar to cron
    // Modified
    $modified_params = array_merge([$ctx_id], $wp_types);
    $modified_sql = $wpdb->prepare("SELECT p.ID
        FROM {$wpdb->posts} p
        JOIN {$wpdb->prefix}aichat_chunks c ON c.post_id=p.ID AND c.id_context=%d
        WHERE p.post_status='publish' AND p.post_type IN ($placeholders_types)
        GROUP BY p.ID
        HAVING TIMESTAMP(MAX(COALESCE(c.updated_at,c.created_at))) < TIMESTAMP(MAX(p.post_modified_gmt))
        LIMIT 500", $modified_params);
    $modified = $wpdb->get_col($modified_sql);

    if($effective==='modified_and_new'){
    $new_params = array_merge([$ctx_id], $wp_types);
    $new_sql = $wpdb->prepare("SELECT p.ID
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}aichat_chunks c ON c.post_id=p.ID AND c.id_context=%d
        WHERE c.post_id IS NULL AND p.post_status='publish' AND p.post_type IN ($placeholders_types)
        ORDER BY p.ID DESC
        LIMIT 500", $new_params);
        $new = $wpdb->get_col($new_sql);
    }

    // Orphans
    $orphans_sql = $wpdb->prepare("SELECT DISTINCT c.post_id
            FROM {$wpdb->prefix}aichat_chunks c
            LEFT JOIN {$wpdb->posts} p ON p.ID = c.post_id
            WHERE c.id_context=%d AND (p.ID IS NULL OR p.post_status <> 'publish')
            LIMIT 500", $ctx_id);
    $orphans = $wpdb->get_col($orphans_sql);

    if($effective==='full'){
        // FULL rebuild semantics:
        // - If context scope is LIMITED (no ALL_* tokens) we ONLY rebuild the existing indexed items (from chunks table).
        // - If scope includes ALL_* tokens, we re-scan full post lists for those types.
        $full_ids=[];
        if($limited){
            $full_ids = $wpdb->get_col( $wpdb->prepare("SELECT DISTINCT post_id FROM {$wpdb->prefix}aichat_chunks WHERE id_context=%d", $ctx_id) );
        } else {
            foreach($wp_types as $pt){
                $ids = get_posts(['post_type'=>$pt,'post_status'=>'publish','numberposts'=>-1,'fields'=>'ids']);
                if($ids) $full_ids = array_merge($full_ids,$ids);
            }
            // NOTE: ALL_UPLOADED omitted for now (uploaded chunks already expanded at creation time)
        }
        $full_ids = aichat_stable_unique_ids($full_ids);
    }

    // Build queue merge
    if($effective==='full'){
        $added_ids = $full_ids; // replace queue entirely
        $new_queue = $full_ids; // full rebuild
    } else {
        $merge_ids = array_merge($modified,$new);
        $new_queue = array_merge($current_queue, $merge_ids);
        $new_queue = aichat_stable_unique_ids($new_queue);
        $added_ids = array_diff($new_queue, $current_queue);
    }

    // Update context row
    $wpdb->update($table_ctx,[
        'items_to_process' => maybe_serialize($new_queue),
        'processing_status'=> 'pending',
        'processing_progress'=> 0
    ],['id'=>$ctx_id]);

    // Delete orphan chunks
    $deleted_orphans=0;
    if(!empty($orphans)){
        $orph_placeholders = implode(',', array_fill(0, count($orphans), '%d'));
        $del_params = array_merge([$ctx_id], array_map('intval',$orphans));
        $delete_sql = $wpdb->prepare("DELETE FROM {$wpdb->prefix}aichat_chunks WHERE id_context=%d AND post_id IN ($orph_placeholders)", $del_params);
        $wpdb->query($delete_sql);
        $deleted_orphans = count($orphans);
    }

    wp_send_json_success([
        'context_id' => $ctx_id,
        'mode_requested' => $mode_req,
        'mode_effective' => $effective,
        'modified_count' => count($modified),
        'new_count' => count($new),
        'orphans_deleted' => $deleted_orphans,
        'queued_total' => count($new_queue),
        'added_to_queue' => count($added_ids)
    ]);
}

// AJAX: Browse chunks (paginated) for local contexts only
add_action('wp_ajax_aichat_browse_context_chunks','aichat_browse_context_chunks');
function aichat_browse_context_chunks(){
    check_ajax_referer('aichat_nonce','nonce');
    if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'Forbidden'],403); }
    global $wpdb; $ctx_table = $wpdb->prefix.'aichat_contexts'; $chunks_table = $wpdb->prefix.'aichat_chunks';
    $ctx_id = isset($_POST['context_id']) ? absint($_POST['context_id']) : 0;
    if($ctx_id<=0){ wp_send_json_error(['message'=>'Missing context_id']); }
    $context = $wpdb->get_row($wpdb->prepare("SELECT id, context_type FROM $ctx_table WHERE id=%d", $ctx_id), ARRAY_A);
    if(!$context){ wp_send_json_error(['message'=>'Context not found']); }
    if($context['context_type']!=='local'){ wp_send_json_error(['message'=>'Browse not available for remote contexts']); }

    $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 25;
    if($per_page <=0) $per_page=25; if($per_page>50) $per_page=50;
    $offset = ($page-1)*$per_page;
    $q = isset($_POST['q']) ? trim(wp_unslash($_POST['q'])) : '';
    if(strlen($q)>80) $q = substr($q,0,80);
    // Escape LIKE wildcards
    $q_like = $q!=='' ? '%' . $wpdb->esc_like($q) . '%' : '';
    $filter_type = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
    $allowed_types = ['post','page','product','upload'];
    if($filter_type && !in_array($filter_type,$allowed_types,true)) $filter_type='';

    $where = $wpdb->prepare("c.id_context=%d", $ctx_id);
    if($filter_type){
        $where .= $wpdb->prepare(" AND c.type=%s", $filter_type);
    }
    if($q_like){
        // Search in title or content (content truncated by LIKE may be heavy; add LIMIT already)
        $where .= $wpdb->prepare(" AND (c.title LIKE %s OR c.content LIKE %s)", $q_like, $q_like);
    }

    // Count total
    $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $chunks_table c WHERE $where");
    if($total===0){
        wp_send_json_success([
            'context_id'=>$ctx_id,
            'rows'=>[],
            'total'=>0,
            'total_pages'=>0,
            'page'=>$page,
            'per_page'=>$per_page
        ]);
    }

    // Fetch rows
    $sql = "SELECT c.post_id, c.type, c.title, c.updated_at, c.created_at, c.chunk_index, LENGTH(c.content) AS size, c.content
            FROM $chunks_table c
            WHERE $where
            ORDER BY COALESCE(c.updated_at,c.created_at) DESC, c.id DESC
            LIMIT %d OFFSET %d";
    $prepared = $wpdb->prepare($sql, $per_page, $offset);
    $rows_raw = $wpdb->get_results($prepared, ARRAY_A);
    $rows = [];
    foreach($rows_raw as $r){
        $content_plain = wp_strip_all_tags($r['content']);
        $excerpt = mb_substr($content_plain,0,140);
        if(mb_strlen($content_plain)>140) $excerpt .= '…';
        $rows[] = [
            'post_id' => (int)$r['post_id'],
            'type' => (string)$r['type'],
            'title' => (string)$r['title'],
            'updated_at' => $r['updated_at'] ?: $r['created_at'],
            'chunk_index' => (int)$r['chunk_index'],
            'size' => (int)$r['size'],
            'excerpt' => $excerpt
        ];
    }
    $total_pages = (int)ceil($total / $per_page);
    wp_send_json_success([
        'context_id'=>$ctx_id,
        'rows'=>$rows,
        'total'=>$total,
        'total_pages'=>$total_pages,
        'page'=>$page,
        'per_page'=>$per_page
    ]);
}