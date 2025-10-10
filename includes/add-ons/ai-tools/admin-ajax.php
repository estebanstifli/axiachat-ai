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
  $row = $wpdb->get_row( $wpdb->prepare("SELECT tools_json FROM $bots_table WHERE slug=%s", $bot_slug), ARRAY_A );
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
