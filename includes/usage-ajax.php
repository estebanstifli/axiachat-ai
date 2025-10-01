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
  // Use wp_date + current_time('timestamp') to avoid mixing PHP date() (server TZ) with WP timezone.
  $ts_today = current_time('timestamp'); // Local blog timestamp
  $d_today = wp_date('Y-m-d', $ts_today);
  $d_7    = wp_date('Y-m-d', $ts_today - 7 * DAY_IN_SECONDS);
  $d_30   = wp_date('Y-m-d', $ts_today - 30 * DAY_IN_SECONDS);
  // Build a prepared query for the last 30 days (local time) starting from local midnight 30 days ago.
  $start_30_midnight = $d_30 . ' 00:00:00';
  $sql_summary = $wpdb->prepare(
    "SELECT DATE(created_at) d, SUM(total_tokens) tt, SUM(cost_micros) cm, COUNT(*) c
       FROM {$conv}
      WHERE created_at >= %s
      GROUP BY DATE(created_at)",
    $start_30_midnight
  );
  $rows = $wpdb->get_results( $sql_summary, ARRAY_A ) ?: [];
  $today = ['tokens'=>0,'cost'=>0,'conversations'=>0];
  $last7 = ['tokens'=>0,'cost'=>0,'conversations'=>0];
  $last30 = ['tokens'=>0,'cost'=>0,'conversations'=>0];
  foreach($rows as $r){
    $d = $r['d']; $tt=(int)$r['tt']; $cm=(int)$r['cm']; $c=(int)$r['c'];
    if($d === $d_today){ $today['tokens']+=$tt; $today['cost']+=$cm; $today['conversations']+=$c; }
    if($d >= $d_7){ $last7['tokens']+=$tt; $last7['cost']+=$cm; $last7['conversations']+=$c; }
    if($d >= $d_30){ $last30['tokens']+=$tt; $last30['cost']+=$cm; $last30['conversations']+=$c; }
  }
  // Top modelos últimos 30 días (mismo rango calculado) usando consulta preparada.
  $sql_top = $wpdb->prepare(
    "SELECT model, provider, SUM(cost_micros) cm
       FROM {$conv}
      WHERE created_at >= %s AND cost_micros IS NOT NULL
      GROUP BY model, provider
      ORDER BY cm DESC
      LIMIT 5",
    $start_30_midnight
  );
  $top_models = $wpdb->get_results( $sql_top, ARRAY_A ) ?: [];
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
    $ts_today = current_time('timestamp');
    $date_to = wp_date('Y-m-d', $ts_today);
    $date_from = wp_date('Y-m-d', $ts_today - 30 * DAY_IN_SECONDS);
  }
  // Validar formato simple YYYY-MM-DD
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_to)){
    wp_send_json_error(['message'=>'Invalid date format'],400);
  }
  // BETWEEN inclusivo: inicio 00:00:00 fin 23:59:59
  $start_ts = $date_from.' 00:00:00';
  $end_ts = $date_to.' 23:59:59';
  // Build fully prepared query (table name is trusted via $wpdb->prefix). Using prepare for date bounds appeases phpcs.
  $sql = $wpdb->prepare(
    "SELECT DATE(created_at) d, SUM(prompt_tokens) p, SUM(completion_tokens) c, SUM(total_tokens) t, SUM(cost_micros) m
       FROM {$conv}
      WHERE created_at BETWEEN %s AND %s
      GROUP BY DATE(created_at)
      ORDER BY d ASC",
      $start_ts,
      $end_ts
  );
  $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
  wp_send_json_success(['series'=>$rows,'date_from'=>$date_from,'date_to'=>$date_to]);
}
