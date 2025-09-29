<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Easy Config Wizard backend scaffolding
 */

function aichat_easy_config_page(){
    if ( ! current_user_can('manage_options') ) { return; }
    $nonce = wp_create_nonce('aichat_easycfg');
    echo '<div class="wrap aichat-easy-config-wrapper">';
    echo '<h1>'.esc_html__('AI Chat – Easy Config','ai-chat').'</h1>';
    echo '<div id="aichat-easy-config-root" data-nonce="'.esc_attr($nonce).'"></div>';
    echo '<noscript><p>'.esc_html__('This wizard requires JavaScript.','ai-chat').'</p></noscript>';
    echo '</div>';
}

// Helper: JSON success/error wrappers
function aichat_easycfg_require_cap(){
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message'=>'forbidden'],403);
    }
}

// Discover site content candidates (posts/pages/products limited)
add_action('wp_ajax_aichat_easycfg_discover', function(){
    aichat_easycfg_require_cap();
    check_ajax_referer('aichat_easycfg','nonce');

    $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'legacy';
    if ($mode === 'smart') {
        $data = aichat_easycfg_discover_smart();
        wp_send_json_success($data);
    }

    // LEGACY fallback (últimos contenidos)
    $limit = 200; // hard cap to avoid overload
    $post_types = ['post','page'];
    if ( class_exists('WooCommerce') ) { $post_types[] = 'product'; }
    $args = [
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'fields' => 'ids',
        'orderby' => 'date',
        'order' => 'DESC'
    ];
    $ids = get_posts($args);
    $items = [];
    foreach($ids as $pid){
        $p = get_post($pid); if(!$p) continue;
        $items[] = [ 'id'=>(int)$pid, 'title'=>get_the_title($p), 'type'=>$p->post_type ];
    }
    wp_send_json_success([
        'total' => count($ids),
        'ids'   => $ids,
        'items' => $items,
        'mode'  => 'legacy'
    ]);
});

/**
 * Smart discovery: prioriza homepage -> enlaces internos -> páginas legales -> productos/categorías clave.
 */
function aichat_easycfg_discover_smart() : array {
    $home_url = home_url('/');
    $max_total = 200;
    $ids = [];
    $seen = [];

    // 1. Página de inicio (si está configurada una estática)
    $front_id = (int) get_option('page_on_front');
    if ($front_id) {
        $ids[] = $front_id; $seen[$front_id]=true;
        $content = get_post_field('post_content', $front_id);
        $link_ids = aichat_easycfg_extract_linked_post_ids($content, $home_url, 80);
        foreach ($link_ids as $pid) {
            if (!isset($seen[$pid])) { $ids[]=$pid; $seen[$pid]=true; }
        }
    }

    // 2. Páginas legales / FAQ / About por heurística de slug o título
    $legal_needles = ['aviso-legal','legal','termin','condicion','terminos','faq','preguntas','privacy','privacidad','cookies','about','quienes','envio','devol','contact'];
    $legal_pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => 50,
        'fields' => 'ids'
    ]);
    foreach($legal_pages as $pid){
        if (isset($seen[$pid])) continue;
        $p = get_post($pid); if(!$p) continue;
        $slug = mb_strtolower($p->post_name);
        $title = mb_strtolower($p->post_title);
        foreach($legal_needles as $needle){
            if (strpos($slug,$needle)!==false || strpos($title,$needle)!==false) {
                $ids[]=$pid; $seen[$pid]=true; break;
            }
        }
    }

    // 3. WooCommerce productos y categorías más representativos
    if ( class_exists('WooCommerce') ) {
        // Categorías top por count
        $terms = get_terms(['taxonomy'=>'product_cat','hide_empty'=>true]);
        if (!is_wp_error($terms) && $terms) {
            usort($terms, fn($a,$b)=> $b->count <=> $a->count);
            $terms = array_slice($terms,0,8);
            foreach($terms as $term){
                // productos recientes de cada categoría
                if ( class_exists('WooCommerce') && function_exists('wc_get_products') ) {
                    $prods = wc_get_products([
                        'status'=>'publish',
                        'limit'=>3,
                        'orderby'=>'date',
                        'order'=>'DESC',
                        'return'=>'ids',
                        'category'=>[$term->slug]
                    ]);
                    foreach($prods as $pid){
                        if (!isset($seen[$pid])) { $ids[]=$pid; $seen[$pid]=true; }
                    }
                }
            }
        }
        // Página de tienda
        if ( class_exists('WooCommerce') && function_exists('wc_get_page_id') ) {
            $shop_id = (int) wc_get_page_id('shop');
            if ($shop_id>0 && !isset($seen[$shop_id])) { $ids[]=$shop_id; $seen[$shop_id]=true; }
        }
    }

    // 4. Si tenemos muy pocos (<5) hacer fallback a últimos posts
    if ( count($ids) < 5 ) {
        $extra = get_posts([
            'post_type' => ['post','page'],
            'post_status'=>'publish',
            'posts_per_page'=> (5-count($ids))*2,
            'fields'=>'ids',
            'orderby'=>'date',
            'order'=>'DESC'
        ]);
        foreach($extra as $pid){ if(!isset($seen[$pid])) { $ids[]=$pid; $seen[$pid]=true; } }
    }

    // Limitar
    $ids = array_slice($ids,0,$max_total);

    // Build items metadata
    $items = [];
    foreach($ids as $pid){ $p = get_post($pid); if($p){ $items[] = ['id'=>(int)$pid,'title'=>get_the_title($p),'type'=>$p->post_type]; } }
    return [
        'total' => count($ids),
        'ids'   => $ids,
        'items' => $items,
        'mode'  => 'smart'
    ];
}

// Stubs defensivos (solo para evitar avisos si el analizador no detecta condicionales). No se ejecutarán en WooCommerce real.
if ( ! function_exists('wc_get_products') ) {
    function wc_get_products($args = []) { return []; }
}
if ( ! function_exists('wc_get_page_id') ) {
    function wc_get_page_id($page) { return 0; }
}

/**
 * Extrae IDs de posts/páginas/productos enlazados dentro de un HTML dado.
 */
function aichat_easycfg_extract_linked_post_ids( $html, string $home_url, int $max = 80 ) : array {
    $out = [];
    if (!is_string($html) || $html==='') return $out;
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if ( ! $dom->loadHTML('<?xml encoding="utf-8"?>'.$html) ) { return $out; }
    $links = $dom->getElementsByTagName('a');
    $seen_urls = [];
    foreach($links as $a){
        $href = $a->getAttribute('href');
        if (!$href) continue;
        // Normalizar
        if (strpos($href,'#')===0) continue; // ancla interna
        if (strpos($href,'mailto:')===0 || strpos($href,'tel:')===0) continue;
        if (!preg_match('~^https?://~',$href)) { // relativo
            $href = rtrim($home_url,'/').'/'. ltrim($href,'/');
        }
        // Mismo dominio
        if ( strpos($href, $home_url)!==0 ) continue;
        $href = strtok($href,'#');
        $href = preg_replace('~[?].*$~','',$href);
        if (isset($seen_urls[$href])) continue; $seen_urls[$href]=true;
        $pid = url_to_postid($href);
        if ($pid && get_post_status($pid)==='publish') { $out[] = $pid; }
        if (count($out) >= $max) break;
    }
    return $out;
}

// Create a context row for the wizard
add_action('wp_ajax_aichat_easycfg_create_context', function(){
    aichat_easycfg_require_cap();
    check_ajax_referer('aichat_easycfg','nonce');

    global $wpdb; $table = $wpdb->prefix.'aichat_contexts';
    $name = sanitize_text_field( $_POST['name'] ?? 'Easy Config Context');

    // Reuse if already created this session (simple approach)
    $existing = $wpdb->get_var( $wpdb->prepare("SELECT id FROM $table WHERE name=%s LIMIT 1", $name) );
    if ( $existing ) {
        wp_send_json_success(['context_id'=>(int)$existing,'reused'=>true]);
    }

    $ok = $wpdb->insert($table,[
        'name' => $name,
        'context_type' => 'local',
        'processing_status' => 'completed', // we'll index directly into chunks
        'processing_progress' => 0,
    ], ['%s','%s','%s','%d']);

    if ( ! $ok ) { wp_send_json_error(['message'=>'db_insert_failed']); }
    $id = (int)$wpdb->insert_id;

    wp_send_json_success(['context_id'=>$id,'reused'=>false]);
});

// Batch index posts into chunks table using existing aichat_index_post
add_action('wp_ajax_aichat_easycfg_index_batch', function(){
    aichat_easycfg_require_cap();
    check_ajax_referer('aichat_easycfg','nonce');

    $context_id = isset($_POST['context_id']) ? (int)$_POST['context_id'] : 0;
    $batch = isset($_POST['ids']) ? (array)$_POST['ids'] : [];
    $processed = [];
    foreach($batch as $pid){
        $pid = (int)$pid; if ($pid<=0) continue;
        $ok = function_exists('aichat_index_post') ? aichat_index_post($pid, $context_id) : false;
        $processed[] = ['id'=>$pid,'ok'=>$ok?1:0];
    }
    wp_send_json_success(['processed'=>$processed]);
});

// Save API key (if provided in wizard)
add_action('wp_ajax_aichat_easycfg_save_api_key', function(){
    aichat_easycfg_require_cap();
    check_ajax_referer('aichat_easycfg','nonce');

    $key = sanitize_text_field( $_POST['api_key'] ?? '' );
    if ( $key ) { update_option('aichat_openai_api_key', $key ); }
    wp_send_json_success(['saved'=> $key ? 1 : 0]);
});

// Status helper (API key presence etc.)
add_action('wp_ajax_aichat_easycfg_status', function(){
    aichat_easycfg_require_cap();
    check_ajax_referer('aichat_easycfg','nonce');
    $key = get_option('aichat_openai_api_key','');
    wp_send_json_success([
        'has_api_key' => $key ? 1 : 0,
    ]);
});

// Create / update default bot linking context
add_action('wp_ajax_aichat_easycfg_create_bot', function(){
    aichat_easycfg_require_cap();
    check_ajax_referer('aichat_easycfg','nonce');

    $context_id = isset($_POST['context_id']) ? (int)$_POST['context_id'] : 0;

    // Find default bot (slug 'default')
    global $wpdb; $table = aichat_bots_table();
    $bot = $wpdb->get_row( $wpdb->prepare("SELECT id,slug FROM $table WHERE slug=%s LIMIT 1", 'default'), ARRAY_A );
    if ( ! $bot ) { wp_send_json_error(['message'=>'default_bot_missing']); }

    $wpdb->update($table,[
        'context_mode' => 'embeddings',
        'context_id'   => $context_id,
        'updated_at'   => current_time('mysql')
    ], ['id'=>$bot['id']], ['%s','%d','%s'], ['%d']);

    update_option('aichat_easy_config_completed', 1);

    wp_send_json_success(['bot_id'=>(int)$bot['id'],'context_id'=>$context_id]);
});
