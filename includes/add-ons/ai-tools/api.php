<?php
/**
 * AI Tools Registry API (moved from includes/tools.php)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'aichat_register_tool' ) ) {
    function aichat_register_tool( $id, $args ) {
        static $tools = [];

        $raw_id = (string)$id;
        if ( ! preg_match( '/^[a-z0-9_]{2,64}$/', $raw_id ) ) {
            return false;
        }

        $defaults = [
            'type'        => 'function',
            'name'        => $raw_id,
            'description' => '',
            'schema'      => [],
            'strict'      => true,
            'callback'    => null,
            'auth'        => null,
            'timeout'     => 5,
            'parallel'    => true,
            'max_calls'   => 1,
            'custom_input_format' => null,
        ];
        $tool = array_merge( $defaults, (array)$args );

        if ( $tool['type'] !== 'function' && $tool['type'] !== 'custom' ) {
            return false;
        }
        if ( ! is_callable( $tool['callback'] ) ) {
            return false;
        }
        if ( $tool['type'] === 'function' ) {
            // Defensive normalization of JSON Schema
            if ( is_array($tool['schema']) ) {
                // Default to object if type omitted
                if ( empty($tool['schema']['type']) ) {
                    $tool['schema']['type'] = 'object';
                }
                // Ensure properties is an object when empty
                if ( isset($tool['schema']['properties']) ) {
                    if ( is_array($tool['schema']['properties']) && empty($tool['schema']['properties']) ) {
                        // Convert [] → {} to satisfy OpenAI validator
                        $tool['schema']['properties'] = (object) [];
                    }
                } else {
                    // Provide empty object properties by default
                    $tool['schema']['properties'] = (object) [];
                }
                // Coerce additionalProperties to boolean if present
                if ( isset($tool['schema']['additionalProperties']) ) {
                    $tool['schema']['additionalProperties'] = (bool) $tool['schema']['additionalProperties'];
                }
                // Ensure required is array if present
                if ( isset($tool['schema']['required']) && ! is_array($tool['schema']['required']) ) {
                    $tool['schema']['required'] = [];
                }
            }
            // Validation after normalization
            if ( ! is_array( $tool['schema'] ) || empty( $tool['schema'] ) ) {
                return false;
            }
            if ( empty( $tool['schema']['type'] ) || $tool['schema']['type'] !== 'object' ) {
                return false;
            }
        }
        if ( ! is_int( $tool['timeout'] ) ) { $tool['timeout'] = (int)$tool['timeout']; }
        if ( $tool['timeout'] <= 0 ) { $tool['timeout'] = 5; }
        if ( ! is_int( $tool['max_calls'] ) || $tool['max_calls'] <= 0 ) { $tool['max_calls'] = 1; }

        $tool['name'] = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string)$tool['name'] );
        if ( $tool['name'] === '' ) { $tool['name'] = $raw_id; }
        $tool['description'] = mb_substr( (string)$tool['description'], 0, 600 );

        $tools[ $raw_id ] = $tool;
        return true;
    }
}

if ( ! function_exists( 'aichat_get_registered_tools' ) ) {
    function aichat_get_registered_tools() {
        if ( isset( $GLOBALS['aichat_registered_tools'] ) && is_array( $GLOBALS['aichat_registered_tools'] ) ) {
            return $GLOBALS['aichat_registered_tools'];
        }
        return [];
    }
}

add_action( 'init', function(){
    if ( ! isset( $GLOBALS['aichat_registered_tools'] ) ) {
        $GLOBALS['aichat_registered_tools'] = [];
    }
});

add_action( 'plugins_loaded', function(){
    if ( ! function_exists('aichat_register_tool') ) return;
    if ( ! has_action('aichat_tool_registered') ) {
        add_action('aichat_tool_registered', function($id, $def){
            if ( ! isset($GLOBALS['aichat_registered_tools']) ) $GLOBALS['aichat_registered_tools'] = [];
            $GLOBALS['aichat_registered_tools'][$id] = $def;
        }, 10, 2);
    }
});

if ( ! function_exists('aichat_register_tool_decorator_applied') ) {
    function aichat_register_tool_decorator_applied() { return true; }
    if ( function_exists('aichat_register_tool') ) {
        if ( ! function_exists('aichat_register_tool_safe') ) {
            function aichat_register_tool_safe( $id, $args ) {
                $ok = \aichat_register_tool( $id, $args );
                if ( $ok && isset($GLOBALS['aichat_registered_tools'][$id]) === false ) {
                    if ( ! isset($GLOBALS['aichat_registered_tools']) ) $GLOBALS['aichat_registered_tools'] = [];
                    $def = $args; $def['id'] = $id;
                    $GLOBALS['aichat_registered_tools'][$id] = $def;
                    do_action('aichat_tool_registered', $id, $def );
                }
                return $ok;
            }
        }
    }
}

// ================= Capability Settings (per bot, per capability) =================
// Option shape: { "bot-slug": { "capability_id": { "system_policy": "...", ... } } }
if ( ! function_exists('aichat_get_capability_settings_map') ) {
    function aichat_get_capability_settings_map() {
        $raw = get_option('aichat_tools_capability_settings', '{}');
        $map = json_decode( (string) $raw, true );
        return is_array($map) ? $map : [];
    }
}
if ( ! function_exists('aichat_get_capability_settings_for_bot') ) {
    function aichat_get_capability_settings_for_bot( $bot_slug ) {
        $all = aichat_get_capability_settings_map();
        $slug = sanitize_title( (string)$bot_slug );
        return isset($all[$slug]) && is_array($all[$slug]) ? $all[$slug] : [];
    }
}
if ( ! function_exists('aichat_save_capability_settings_for_bot') ) {
    function aichat_save_capability_settings_for_bot( $bot_slug, array $settings_map ) {
        $all = aichat_get_capability_settings_map();
        $slug = sanitize_title( (string)$bot_slug );
        $all[$slug] = $settings_map;
        update_option('aichat_tools_capability_settings', wp_json_encode($all));
        return true;
    }
}

// Inject per-capability system policies into the system prompt for the active bot
add_filter('aichat_messages_before_provider', function( $messages, $meta ){
    if ( ! is_array($messages) || empty($messages) ) return $messages;
    if ( empty($meta['bot']) || ! is_array($meta['bot']) ) return $messages;
    $bot = $meta['bot'];
    $slug = isset($bot['slug']) ? sanitize_title($bot['slug']) : '';
    if ( $slug === '' ) return $messages;
    // Decode selected capabilities/marcos
    $selected = [];
    if ( ! empty($bot['tools_json']) ) {
        $tmp = json_decode( (string)$bot['tools_json'], true );
        if ( is_array($tmp) ) { foreach($tmp as $id){ if ( is_string($id) ) $selected[] = sanitize_key($id); } }
    }
    if ( empty($selected) ) return $messages;
    $settings = aichat_get_capability_settings_for_bot( $slug );
    if ( empty($settings) ) return $messages;
    $policies = [];
    foreach ( $selected as $cap_id ) {
        if ( isset($settings[$cap_id]['system_policy']) ) {
            $sp = trim( (string) $settings[$cap_id]['system_policy'] );
            if ( $sp !== '' ) { $policies[] = $sp; }
        }
    }
    if ( empty($policies) ) return $messages;
    // Append to system message content
    if ( isset($messages[0]['role']) && $messages[0]['role'] === 'system' ) {
        $addon = "\n\nCAPABILITY POLICIES:\n- " . implode("\n- ", array_map('wp_strip_all_tags', $policies));
        $messages[0]['content'] .= $addon;
    }
    return $messages;
}, 20, 2);

// Inject provider-native tools (e.g., web_search) for OpenAI Responses when selected via macro, with optional domain constraints
add_filter('aichat_openai_responses_tools', function( $tools, $ctx ){
    // $ctx: ['model'=>..., 'bot'=>bot_slug]
    // Detect if macro 'openai_web_search' is selected for this bot
    $bot_slug = isset($ctx['bot']) ? sanitize_title($ctx['bot']) : '';
    if ($bot_slug === '') return $tools;
    // Load bot row to inspect selected capabilities (tools_json)
    global $wpdb; $bots_table = $wpdb->prefix.'aichat_bots';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safe; slug uses placeholder
    $row = $wpdb->get_row( $wpdb->prepare("SELECT tools_json FROM {$bots_table} WHERE slug=%s", $bot_slug), ARRAY_A );
    $selected = [];
    if ($row && !empty($row['tools_json'])){ $tmp = json_decode((string)$row['tools_json'], true); if(is_array($tmp)) $selected = array_values(array_filter($tmp, 'is_string')); }
    if ( empty($selected) ) return $tools;
    $has_web_search_macro = in_array('openai_web_search', $selected, true);
    if ( ! $has_web_search_macro ) return $tools;
    // Optional: read capability settings to restrict domains
    $domains = [];
    if ( function_exists('aichat_get_capability_settings_for_bot') ) {
        $cap_settings = aichat_get_capability_settings_for_bot($bot_slug);
        if ( isset($cap_settings['openai_web_search']['domains']) && is_array($cap_settings['openai_web_search']['domains']) ) {
            $domains = array_values(array_filter(array_map('sanitize_text_field', $cap_settings['openai_web_search']['domains'])));
        }
    }
    // Build OpenAI native web_search tool entry for Responses
    // Supports filters.allowed_domains as per OpenAI Responses API (2025)
    $ws = [ 'type' => 'web_search' ];
    if ($domains) {
        $ws['filters'] = [ 'allowed_domains' => $domains ];
    }
    // Ensure it's not duplicated
    $found = false; foreach($tools as $t){ if( isset($t['type']) && $t['type']==='web_search' ){ $found=true; break; } }
    if ( ! $found ) { $tools[] = $ws; }
    return $tools;
}, 10, 2);

// Early injection of allowed domains policy into system message (runs before provider call)
add_filter('aichat_messages_before_provider', function($messages, $meta){
    if ( empty($meta['bot']) || !is_array($meta['bot']) ) return $messages;
    $bot = $meta['bot']; $slug = isset($bot['slug']) ? sanitize_title($bot['slug']) : '';
    if ($slug==='') return $messages;
    // Check web search macro selected
    $selected = [];
    if (!empty($bot['tools_json'])){ $tmp = json_decode((string)$bot['tools_json'], true); if(is_array($tmp)) $selected = array_values(array_filter($tmp, 'is_string')); }
    if ( empty($selected) || !in_array('openai_web_search',$selected,true) ) return $messages;
    // Load domains from capability settings
    $domains = [];
    if ( function_exists('aichat_get_capability_settings_for_bot') ) {
        $cap_settings = aichat_get_capability_settings_for_bot($slug);
        if ( isset($cap_settings['openai_web_search']['domains']) && is_array($cap_settings['openai_web_search']['domains']) ) {
            $domains = array_values(array_filter(array_map('sanitize_text_field', $cap_settings['openai_web_search']['domains'])));
        }
    }
    if (!$domains) return $messages;
    if ( isset($messages[0]['role']) && $messages[0]['role']==='system' ) {
        $hint = "\n\nWEB SEARCH POLICY: Prefer retrieving and citing information from these domains: ".implode(', ', array_map('wp_strip_all_tags',$domains)).". If relevant info isn't available there, you may extend to trusted sources.";
        $messages[0]['content'] .= $hint;
    }
    return $messages;
}, 18, 2);
