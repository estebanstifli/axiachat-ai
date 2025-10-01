<?php
if(!defined('ABSPATH')) exit;

add_action('wp_ajax_aichat_get_usage_summary','aichat_get_usage_summary');
add_action('wp_ajax_aichat_get_usage_timeseries','aichat_get_usage_timeseries');

function aichat_usage_cap_check(){
  if(!current_user_can('manage_options')){ wp_send_json_error(['message'=>'Forbidden'],403); }
}

function aichat_get_usage_summary(){
  aichat_usage_cap_check();
  global $wpdb; $conv = $wpdb->prefix.'aichat_conversations';
  $d_today = current_time('Y-m-d');
  $d_7 = date('Y-m-d', strtotime('-7 days', strtotime($d_today)));
  $d_30 = date('Y-m-d', strtotime('-30 days', strtotime($d_today)));

  $rows = $wpdb->get_results("SELECT DATE(created_at) d, SUM(total_tokens) tt, SUM(cost_micros) cm, COUNT(*) c FROM $conv WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at)", ARRAY_A) ?: [];
  $today = ['tokens'=>0,'cost'=>0,'conversations'=>0];
  $last7 = ['tokens'=>0,'cost'=>0,'conversations'=>0];
  $last30 = ['tokens'=>0,'cost'=>0,'conversations'=>0];
  foreach($rows as $r){
    $d = $r['d']; $tt=(int)$r['tt']; $cm=(int)$r['cm']; $c=(int)$r['c'];
    if($d === $d_today){ $today['tokens']+=$tt; $today['cost']+=$cm; $today['conversations']+=$c; }
    if($d >= $d_7){ $last7['tokens']+=$tt; $last7['cost']+=$cm; $last7['conversations']+=$c; }
    if($d >= $d_30){ $last30['tokens']+=$tt; $last30['cost']+=$cm; $last30['conversations']+=$c; }
  }
  // Top modelos últimos 30 días
  $top_models = $wpdb->get_results("SELECT model, provider, SUM(cost_micros) cm FROM $conv WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND cost_micros IS NOT NULL GROUP BY model, provider ORDER BY cm DESC LIMIT 5", ARRAY_A) ?: [];
  wp_send_json_success([
    'today'=>$today,
    'last7'=>$last7,
    'last30'=>$last30,
    'top_models'=>$top_models
  ]);
}

function aichat_get_usage_timeseries(){
  aichat_usage_cap_check();
  global $wpdb; $conv = $wpdb->prefix.'aichat_conversations';
  $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
  $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
  if(!$date_from || !$date_to){
    $date_to = current_time('Y-m-d');
    $date_from = date('Y-m-d', strtotime('-30 days', strtotime($date_to)));
  }
  // Validar formato simple YYYY-MM-DD
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_to)){
    wp_send_json_error(['message'=>'Invalid date format'],400);
  }
  // BETWEEN inclusivo: inicio 00:00:00 fin 23:59:59
  $start_ts = $date_from.' 00:00:00';
  $end_ts = $date_to.' 23:59:59';
  $where = $wpdb->prepare('created_at BETWEEN %s AND %s', $start_ts, $end_ts);
  $sql = "SELECT DATE(created_at) d, SUM(prompt_tokens) p, SUM(completion_tokens) c, SUM(total_tokens) t, SUM(cost_micros) m FROM $conv WHERE $where GROUP BY DATE(created_at) ORDER BY d ASC";
  $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
  wp_send_json_success(['series'=>$rows,'date_from'=>$date_from,'date_to'=>$date_to]);
}
