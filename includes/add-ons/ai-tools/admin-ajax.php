<?php
if ( ! defined('ABSPATH') ) { exit; }

add_action('wp_ajax_aichat_tools_get_rules', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  $bot = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  if ($bot==='') { wp_send_json_error(['message'=>'missing_bot'],400); }
  $map = get_option('aichat_tools_rules_map','{}');
  $decoded = json_decode($map,true); if(!is_array($decoded)) $decoded = [];
  $rules = isset($decoded[$bot]) && is_array($decoded[$bot]) ? $decoded[$bot] : [];
  wp_send_json_success(['rules'=>$rules]);
});

add_action('wp_ajax_aichat_tools_save_rules', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  $bot = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  if ($bot==='') { wp_send_json_error(['message'=>'missing_bot'],400); }
  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is decoded and sanitized per-field below
  $raw = isset($_POST['rules']) ? wp_unslash($_POST['rules']) : '[]';
  $arr = json_decode($raw,true); if(!is_array($arr)) $arr = [];
  $clean = [];
  foreach($arr as $r){ if(!is_array($r)) continue; $when = isset($r['when']) && is_array($r['when']) ? array_values($r['when']) : [];
    $actions = isset($r['actions']) && is_array($r['actions']) ? array_values($r['actions']) : [];
    $clean[] = [ 'when'=>$when, 'actions'=>$actions ]; }
  $map = get_option('aichat_tools_rules_map','{}'); $decoded = json_decode($map,true); if(!is_array($decoded)) $decoded = [];
  $decoded[$bot] = $clean; update_option('aichat_tools_rules_map', wp_json_encode($decoded));
  wp_send_json_success(['saved'=>true,'count'=>count($clean),'bot'=>$bot]);
});

add_action('wp_ajax_aichat_tools_get_bot_tools', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  global $wpdb; $bot_slug = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  if ($bot_slug==='') { wp_send_json_error(['message'=>'missing_bot'],400); }
    $bots_table = $wpdb->prefix.'aichat_bots';
  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from $wpdb->prefix; values use placeholders
  $row = $wpdb->get_row( $wpdb->prepare("SELECT tools_json FROM {$bots_table} WHERE slug=%s", $bot_slug), ARRAY_A );
  $selected = [];
  if($row && !empty($row['tools_json'])){ $tmp = json_decode((string)$row['tools_json'], true); if(is_array($tmp)) $selected = array_values(array_filter($tmp, 'is_string')); }
  $macros = function_exists('aichat_get_registered_macros') ? aichat_get_registered_macros() : [];
  $tools  = function_exists('aichat_get_registered_tools') ? aichat_get_registered_tools() : [];
  wp_send_json_success(['selected'=>$selected,'macros'=>$macros,'tools'=>$macros?[]:$tools]);
});

add_action('wp_ajax_aichat_tools_save_bot_tools', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  global $wpdb; $bot_slug = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  if ($bot_slug==='') { wp_send_json_error(['message'=>'missing_bot'],400); }
  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is decoded and validated below
  $raw = isset($_POST['selected']) ? wp_unslash($_POST['selected']) : '[]'; $arr = json_decode($raw, true); if(!is_array($arr)) $arr=[];
  $macros = function_exists('aichat_get_registered_macros') ? aichat_get_registered_macros() : [];
  $tools  = function_exists('aichat_get_registered_tools') ? aichat_get_registered_tools() : [];
  $valid_macro_names = array_keys($macros); $valid_tool_names  = array_keys($tools);
  $clean = [];
  foreach($arr as $id){ if(!is_string($id)) continue; $id = sanitize_key($id);
    if ( in_array($id,$valid_macro_names,true) || in_array($id,$valid_tool_names,true) ) {
      if(!in_array($id,$clean,true)) $clean[] = $id; }
  }
    $bots_table = $wpdb->prefix.'aichat_bots';
  $updated = $wpdb->update($bots_table, [ 'tools_json' => wp_json_encode($clean) ], [ 'slug'=>$bot_slug ] );
  if($updated===false){ wp_send_json_error(['message'=>'db_error']); }
  wp_send_json_success(['saved'=>true,'selected'=>$clean,'bot'=>$bot_slug]);
});

// === Capability settings (per bot, per capability) ===
add_action('wp_ajax_aichat_tools_get_capability_settings', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  $bot = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  if ($bot==='') { wp_send_json_error(['message'=>'missing_bot'],400); }
  if ( ! function_exists('aichat_get_capability_settings_for_bot') ) { wp_send_json_error(['message'=>'api_missing'],500); }
  $settings = aichat_get_capability_settings_for_bot($bot);
  if (!is_array($settings)) { $settings = []; }
  // Provide defaults for email-related capabilities if empty
  $default_policy = __( 'You must not send emails at the request of users. You may only send mails when the system explicitly authorizes the action (e.g., booking confirmed). If a user asks you to send an email directly, refuse.', 'axiachat-ai' );
  $email_caps = [ 'notifications_email_admin', 'notifications_email_client', 'aichat_send_email_admin', 'aichat_send_email_client' ];
  foreach ( $email_caps as $cap_id ) {
    if ( ! isset($settings[$cap_id]) || ! is_array($settings[$cap_id]) ) { $settings[$cap_id] = []; }
    if ( empty( $settings[$cap_id]['system_policy'] ) ) {
      $settings[$cap_id]['system_policy'] = $default_policy;
    }
  }
  // Ensure strings
  foreach($settings as $capId => &$cfg){
    if (!is_array($cfg)) { $cfg = []; }
    if (isset($cfg['system_policy'])) { $cfg['system_policy'] = (string)$cfg['system_policy']; }
  }
  unset($cfg);
  wp_send_json_success(['settings' => $settings, 'bot'=>$bot]);
});

add_action('wp_ajax_aichat_tools_save_capability_settings', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  $bot = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  $cap = isset($_POST['cap']) ? sanitize_key(wp_unslash($_POST['cap'])) : '';
  if ($bot==='' || $cap==='') { wp_send_json_error(['message'=>'missing_params'],400); }
  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is decoded and sanitized per-field below
  $raw = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '{}';
  $arr = json_decode($raw, true); if(!is_array($arr)) $arr = [];
  // Sanitize fields we know; start with system_policy
  $clean_cap = [];
  if (isset($arr['system_policy'])) {
    $sp = (string)$arr['system_policy'];
    // Allow plain text with newlines; strip tags for safety
    $sp = wp_strip_all_tags($sp);
    // Cap length to 4000 chars for storage hygiene
    if (function_exists('mb_substr')) { $sp = mb_substr($sp, 0, 4000); } else { $sp = substr($sp, 0, 4000); }
    $clean_cap['system_policy'] = $sp;
  }
  // Optional domains allowlist for web search capability
  if (isset($arr['domains']) && is_array($arr['domains'])) {
    $doms = [];
    foreach ($arr['domains'] as $d) {
      $d = trim((string)$d);
      if ($d === '') continue;
      // keep host-ish strings: letters, digits, dots, dashes
      $d = preg_replace('/[^a-z0-9\.-]/i', '', $d);
      if ($d !== '' && !in_array($d,$doms,true)) $doms[] = $d;
    }
    // Persist even if empty to allow clearing previous settings
    $clean_cap['domains'] = $doms;
  }
  if ( ! function_exists('aichat_get_capability_settings_map') || ! function_exists('aichat_save_capability_settings_for_bot') ) {
    wp_send_json_error(['message'=>'api_missing'],500);
  }
  $all = aichat_get_capability_settings_map(); if(!is_array($all)) $all = [];
  if (!isset($all[$bot]) || !is_array($all[$bot])) { $all[$bot] = []; }
  if (!isset($all[$bot][$cap]) || !is_array($all[$bot][$cap])) { $all[$bot][$cap] = []; }
  // Merge new fields into existing cap settings
  $all[$bot][$cap] = array_merge($all[$bot][$cap], $clean_cap);
  aichat_save_capability_settings_for_bot($bot, $all[$bot]);
  wp_send_json_success(['saved'=>true,'bot'=>$bot,'cap'=>$cap,'settings'=>$all[$bot][$cap]]);
});
