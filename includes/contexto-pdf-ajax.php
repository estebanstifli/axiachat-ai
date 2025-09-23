<?php
/**
 * AJAX handlers for Import PDF/Data
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==============================
// CPTs (ligeros, privados)
// ==============================
add_action('init', function(){
    // Padre: un post por fichero subido
    if ( ! post_type_exists('aichat_upload') ) {
        register_post_type('aichat_upload', array(
            'labels' => array(
                'name' => 'AIChat Uploads',
                'singular_name' => 'AIChat Upload',
            ),
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'supports' => array('title'),
        ));
    }
    // Hijo: un post por chunk de texto
    if ( ! post_type_exists('aichat_upload_chunk') ) {
        register_post_type('aichat_upload_chunk', array(
            'labels' => array(
                'name' => 'AIChat Upload Chunks',
                'singular_name' => 'AIChat Upload Chunk',
            ),
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'supports' => array('title','editor'),
        ));
    }
});

// ==============================
// Helpers
// ==============================
function aichat_pdf_log($m){ aichat_log_debug('[AIChat PDF] '.$m); }

function aichat_upload_dir(){
    $up = wp_upload_dir();
    $base = trailingslashit($up['basedir']).'aichat_uploads';
    if ( ! file_exists($base) ) { wp_mkdir_p($base); }
    return $base;
}

function aichat_bytes($v){
    $n = is_numeric($v) ? (int)$v : 0;
    return max(0, $n);
}

function aichat_is_pdf($mime, $name){
    $mime = strtolower((string)$mime);
    $ext  = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
    return ($mime === 'application/pdf') || ($ext === 'pdf');
}

function aichat_is_txt($mime, $name){
    $mime = strtolower((string)$mime);
    $ext  = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
    return ($mime === 'text/plain') || ($ext === 'txt');
}

/**
 * Chunking básico por palabras (~900-1200 palabras, solape ~180)
 */
function aichat_chunk_text($text, $target_words = 1000, $overlap = 180){
    $text = trim(preg_replace("/\r\n|\r/","\n",$text));
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $chunks = [];
    $n = count($words);
    if ($n === 0) return [];

    $i = 0; $idx = 0;
    while ($i < $n) {
        $end = min($n, $i + $target_words);
        $slice = array_slice($words, $i, $end - $i);
        $chunk = trim(implode(' ', $slice));
        if ($chunk !== '') $chunks[] = ['index'=>$idx++, 'text'=>$chunk];
        if ($end >= $n) break;
        $i = max($end - $overlap, $i + 1);
    }
    return $chunks;
}

/**
 * Extrae texto de TXT
 */
function aichat_extract_txt($path){
    $txt = @file_get_contents($path);
    if ($txt === false) return new WP_Error('read_txt','Could not read TXT file');
    // Normaliza UTF-8
    if (! seems_utf8($txt)) {
        $txt = mb_convert_encoding($txt, 'UTF-8', 'auto');
    }
    // Limpia control chars
    $txt = preg_replace('/[^\P{C}\t\n]+/u','',$txt);
    return trim($txt);
}

/**
 * Usa pdftotext si existe
 */
function aichat_pdftotext_available(){
    if (! function_exists('shell_exec')) return false;
    $out = @shell_exec('command -v pdftotext 2>/dev/null');
    if (is_string($out) && strlen(trim($out))>0) return true;
    // Windows
    $out = @shell_exec('where pdftotext 2>NUL');
    return (is_string($out) && strlen(trim($out))>0);
}

function aichat_extract_pdf_with_pdftotext($path){
    if (! aichat_pdftotext_available()) return new WP_Error('no_pdftotext','pdftotext not available');
    $cmd = 'pdftotext -enc UTF-8 -q '.escapeshellarg($path).' -';
    $out = @shell_exec($cmd);
    if (! is_string($out) || strlen($out)===0) {
        return new WP_Error('pdftotext_empty','pdftotext returned no output');
    }
    return trim($out);
}

/**
 * Fallback PHP vía Smalot\PdfParser si está disponible
 * (recomendado: instalar con composer o incluir la lib)
 */
function aichat_extract_pdf_with_smalot($path){
    if (! class_exists('\Smalot\PdfParser\Parser') ) {
        return new WP_Error('no_smalot','Smalot\\PdfParser not available');
    }
    try{
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        $text = $pdf->getText();
        if (! is_string($text) || strlen(trim($text))===0) {
            return new WP_Error('smalot_empty','Smalot returned empty text');
        }
        return trim($text);
    } catch (\Throwable $e){
        return new WP_Error('smalot_exception','Smalot exception: '.$e->getMessage());
    }
}

/**
 * Extractor unificado: TXT / PDF
 * - PDF: pdftotext -> Smalot -> error
 */
function aichat_extract_text($path, $mime, $name){
    if (aichat_is_txt($mime,$name)) {
        return aichat_extract_txt($path);
    }
    if (aichat_is_pdf($mime,$name)) {
        // Intenta pdftotext
        $t0 = microtime(true);
        $r = aichat_extract_pdf_with_pdftotext($path);
        if (! is_wp_error($r)) {
            aichat_pdf_log('pdftotext OK ('.number_format(microtime(true)-$t0,3).'s)');
            return $r;
        }
        // Fallback Smalot
        $t1 = microtime(true);
        $r2 = aichat_extract_pdf_with_smalot($path);
        if (! is_wp_error($r2)) {
            aichat_pdf_log('Smalot OK ('.number_format(microtime(true)-$t1,3).'s)');
            return $r2;
        }
        // Sin parser disponible
        return new WP_Error(
            'no_pdf_parser',
            'Could not extract text. Ensure pdftotext or a PHP PDF parser (Smalot\\PdfParser) is available.'
        );
    }
    return new WP_Error('unsupported','Unsupported file type. Only PDF or TXT are allowed.');
}

/**
 * Crea posts chunk a partir de texto; devuelve IDs
 */
function aichat_create_chunks_posts($upload_post_id, $filename, $text){
    $chunks = aichat_chunk_text($text, apply_filters('aichat_chunk_words', 1000), apply_filters('aichat_chunk_overlap', 180));
    $ids = [];
    $total = count($chunks);
    foreach ($chunks as $c) {
        $title = sprintf('%s (chunk %d/%d)', $filename, $c['index']+1, $total);
        $post_id = wp_insert_post(array(
            'post_type'    => 'aichat_upload_chunk',
            'post_status'  => 'publish', // importante para tu indexador
            'post_title'   => $title,
            'post_content' => $c['text'],
        ));
        if ($post_id && ! is_wp_error($post_id)) {
            add_post_meta($post_id, '_aichat_upload_id', (int)$upload_post_id, true);
            add_post_meta($post_id, '_aichat_chunk_index', (int)$c['index'], true);
            add_post_meta($post_id, '_aichat_tokens', str_word_count($c['text']), true);
            $ids[] = (int)$post_id;
        }
    }
    return $ids;
}

/**
 * Convierte un post "upload" a array serializable para el listado
 */
function aichat_upload_to_row($p){
    $id = (int)$p->ID;
    $filename = (string) get_post_meta($id, '_aichat_filename', true);
    $mime     = (string) get_post_meta($id, '_aichat_mime', true);
    $size     = aichat_bytes( get_post_meta($id, '_aichat_size', true) );
    $status   = (string) get_post_meta($id, '_aichat_status', true);
    $chunks   = (int) get_post_meta($id, '_aichat_chunk_count', true);
    $updated  = (string) get_post_modified_time('Y-m-d H:i:s', true, $id);

    return array(
        'id'          => $id,
        'filename'    => $filename,
        'mime'        => $mime,
        'size'        => $size,        // BYTES → el JS ya lo convierte a KB/MB
        'status'      => $status ?: 'uploaded',
        'chunk_count' => $chunks,
        'updated_at'  => $updated,
    );
}

// ==============================
// AJAX: subir archivo
// ==============================
add_action('wp_ajax_aichat_upload_file', function(){
    check_ajax_referer('aichat_pdf_nonce','nonce');
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(array('message'=>'Unauthorized'), 403);
    }
    if (empty($_FILES['file'])) {
        wp_send_json_error(array('message'=>'No file'), 400);
    }

    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(array('message'=>'Upload error code '.$f['error']), 400);
    }

    $name = sanitize_file_name($f['name']);
    $tmp  = $f['tmp_name'];
    $mime = $f['type'] ?: mime_content_type($tmp);
    $size = (int) $f['size'];

    // Validación básica
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (! in_array($ext, array('pdf','txt'), true)) {
        wp_send_json_error(array('message'=>'Only .pdf or .txt allowed'), 400);
    }

    // Move a carpeta propia
    $dir = aichat_upload_dir();
    $data = @file_get_contents($tmp);
    if ($data === false) {
        wp_send_json_error(array('message'=>'Could not read uploaded file'), 400);
    }
    $sha = hash('sha256', $data);
    $safeBase = $sha.'.'.$ext;
    $dest = trailingslashit($dir).$safeBase;
    if (! @file_put_contents($dest, $data) ) {
        wp_send_json_error(array('message'=>'Could not store uploaded file'), 500);
    }

    // Crea post "upload"
    $pid = wp_insert_post(array(
        'post_type'   => 'aichat_upload',
        'post_status' => 'private',
        'post_title'  => $name,
    ));
    if (! $pid || is_wp_error($pid)) {
        // Limpia el archivo físico usando API WP segura
        if ( file_exists( $dest ) ) {
            wp_delete_file( $dest );
        }
        wp_send_json_error(array('message'=>'Could not create upload post'), 500);
    }

    add_post_meta($pid, '_aichat_filename', $name, true);
    add_post_meta($pid, '_aichat_mime', $mime, true);
    add_post_meta($pid, '_aichat_size', $size, true);      // BYTES
    add_post_meta($pid, '_aichat_path', $dest, true);
    add_post_meta($pid, '_aichat_sha256', $sha, true);
    add_post_meta($pid, '_aichat_status', 'uploaded', true);
    add_post_meta($pid, '_aichat_chunk_count', 0, true);

    aichat_pdf_log("Uploaded '{$name}' ({$size} bytes) pid=$pid");
    wp_send_json_success(array('upload_id'=>(int)$pid));
});

// ==============================
// AJAX: listar uploads
// ==============================
add_action('wp_ajax_aichat_list_uploads', function(){
    check_ajax_referer('aichat_pdf_nonce','nonce');
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(array('message'=>'Unauthorized'), 403);
    }

    $page = max(1, (int)($_POST['page'] ?? 1));
    $per  = max(1, min(100, (int)($_POST['per_page'] ?? 10)));
    $s    = sanitize_text_field($_POST['search'] ?? '');

    $args = array(
        'post_type'      => 'aichat_upload',
        'post_status'    => array('private'),
        'posts_per_page' => $per,
        'paged'          => $page,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        's'              => $s,
        'no_found_rows'  => false,
    );
    $q = new WP_Query($args);

    $items = array_map('aichat_upload_to_row', $q->posts);
    $total = (int) $q->found_posts;

    wp_send_json_success(array(
        'items' => $items,
        'total' => $total,
        'page'  => $page,
        'per_page' => $per,
    ));
});

// ==============================
// AJAX: parse / chunk
// ==============================
add_action('wp_ajax_aichat_parse_upload', function(){
    check_ajax_referer('aichat_pdf_nonce','nonce');
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(array('message'=>'Unauthorized'), 403);
    }
    $upload_id = (int)($_POST['upload_id'] ?? 0);
    $force = !empty($_POST['force']);

    if ($upload_id <= 0) {
        wp_send_json_error(array('message'=>'Invalid upload_id'), 400);
    }

    $p = get_post($upload_id);
    if (! $p || $p->post_type !== 'aichat_upload') {
        wp_send_json_error(array('message'=>'Upload not found'), 404);
    }

    $filename = get_post_meta($upload_id,'_aichat_filename', true);
    $mime     = get_post_meta($upload_id,'_aichat_mime', true);
    $path     = get_post_meta($upload_id,'_aichat_path', true);

    if (! file_exists($path)) {
        wp_send_json_error(array('message'=>'Stored file not found on disk'), 404);
    }

    // Si ya tenía chunks y no es reparse, devolvemos existentes
    $existing = get_posts(array(
        'post_type'   => 'aichat_upload_chunk',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_key'    => '_aichat_upload_id',
        'meta_value'  => $upload_id,
    ));
    if ($existing && !$force) {
        wp_send_json_success(array(
            'chunks_created' => count($existing),
            'chunk_ids'      => array_map('intval',$existing),
            'message'        => 'Already chunked',
        ));
    }

    // Si reparse, borramos primero los chunks anteriores
    if ($force && $existing) {
        foreach ($existing as $cid) { wp_delete_post($cid, true); }
        update_post_meta($upload_id, '_aichat_chunk_count', 0);
    }

    // Extraer texto
    $t0 = microtime(true);
    $res = aichat_extract_text($path, $mime, $filename);
    if (is_wp_error($res)) {
        aichat_pdf_log('Parse error: '.$res->get_error_message());
        wp_send_json_error(array('message'=>$res->get_error_message()), 500);
    }
    $text = $res;

    // Crea chunks (posts)
    $chunk_ids = aichat_create_chunks_posts($upload_id, $filename, $text);
    update_post_meta($upload_id, '_aichat_status', 'chunked');
    update_post_meta($upload_id, '_aichat_chunk_count', count($chunk_ids));

    aichat_pdf_log("Parsed '$filename' -> ".count($chunk_ids)." chunks (".number_format(microtime(true)-$t0,3)."s)");

    wp_send_json_success(array(
        'chunks_created' => count($chunk_ids),
        'chunk_ids'      => $chunk_ids,
    ));
});

// ==============================
// AJAX: devolver IDs de chunks (para Add to Context)
// ==============================
add_action('wp_ajax_aichat_get_chunks_for_upload', function(){
    check_ajax_referer('aichat_pdf_nonce','nonce');
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(array('message'=>'Unauthorized'), 403);
    }
    $upload_id = (int)($_POST['upload_id'] ?? 0);
    if ($upload_id<=0) {
        wp_send_json_error(array('message'=>'Invalid upload_id'), 400);
    }

    $ids = get_posts(array(
        'post_type'   => 'aichat_upload_chunk',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_key'    => '_aichat_upload_id',
        'meta_value'  => $upload_id,
        'orderby'     => 'meta_value_num',
        'meta_key'    => '_aichat_chunk_index',
        'order'       => 'ASC',
    ));
    wp_send_json_success(array('chunk_ids'=>array_map('intval',$ids)));
});

// ==============================
// AJAX: borrar upload + chunks
// ==============================
add_action('wp_ajax_aichat_delete_upload', function(){
    check_ajax_referer('aichat_pdf_nonce','nonce');
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(array('message'=>'Unauthorized'), 403);
    }
    $upload_id = (int)($_POST['upload_id'] ?? 0);
    if ($upload_id<=0) {
        wp_send_json_error(array('message'=>'Invalid upload_id'), 400);
    }

    // Borra chunks primero
    $ids = get_posts(array(
        'post_type'   => 'aichat_upload_chunk',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_key'    => '_aichat_upload_id',
        'meta_value'  => $upload_id,
    ));
    foreach ($ids as $cid) { wp_delete_post($cid, true); }

    // Borra fichero físico
    $path = get_post_meta($upload_id,'_aichat_path', true);
    if ( $path && file_exists( $path ) ) {
        wp_delete_file( $path );
    }

    // Borra upload
    wp_delete_post($upload_id, true);

    wp_send_json_success(array('deleted'=>true));
});
