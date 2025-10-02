<?php
if(!defined('ABSPATH')) exit;

/** Admin page: usage and cost */

function aichat_usage_admin_page(){
  if(!current_user_can('manage_options')) return;
  echo '<div class="wrap"><h1>'.esc_html__('AI Chat – Usage / Cost','axiachat-ai').'</h1>';
  echo '<p class="description">'.esc_html__('Token & cost metrics (chat). Costs are approximate based on configured pricing.','axiachat-ai').'</p>';
  echo '<div id="aichat-usage-kpis" class="aichat-usage-grid" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;">'
  .'<div class="usage-box" style="flex:1;min-width:180px;background:#fff;border:1px solid #ddd;padding:12px;border-radius:6px;"><strong>'.esc_html__('Today','axiachat-ai').'</strong><br><span data-kpi="today-cost">-</span><br><small><span data-kpi="today-tokens">-</span> '.esc_html__('tokens','axiachat-ai').'</small></div>'
  .'<div class="usage-box" style="flex:1;min-width:180px;background:#fff;border:1px solid #ddd;padding:12px;border-radius:6px;"><strong>'.esc_html__('Last 7 days','axiachat-ai').'</strong><br><span data-kpi="last7-cost">-</span><br><small><span data-kpi="last7-tokens">-</span> '.esc_html__('tokens','axiachat-ai').'</small></div>'
  .'<div class="usage-box" style="flex:1;min-width:180px;background:#fff;border:1px solid #ddd;padding:12px;border-radius:6px;"><strong>'.esc_html__('Last 30 days','axiachat-ai').'</strong><br><span data-kpi="last30-cost">-</span><br><small><span data-kpi="last30-tokens">-</span> '.esc_html__('tokens','axiachat-ai').'</small></div>'
      .'</div>';
  echo '<h2 style="margin-top:30px;">'.esc_html__('Timeseries (Last 30 days)','axiachat-ai').'</h2>';
  echo '<div style="max-width:100%;max-height:400px;overflow:hidden;">'
     .'<canvas id="aichat-usage-chart" style="max-height:400px;width:100%;"></canvas>'
  .'</div><div id="aichat-usage-nodata" style="margin-top:8px;color:#666;display:none;">'.esc_html__('No data','axiachat-ai').'</div>';
  echo '<h2 style="margin-top:30px;">'.esc_html__('Top Models (30d)','axiachat-ai').'</h2>';
  echo '<table class="widefat" id="aichat-usage-topmodels"><thead><tr><th>'.esc_html__('Model','axiachat-ai').'</th><th>'.esc_html__('Provider','axiachat-ai').'</th><th>'.esc_html__('Cost (USD)','axiachat-ai').'</th></tr></thead><tbody><tr><td colspan="3">'.esc_html__('Loading...','axiachat-ai').'</td></tr></tbody></table>';
  echo '</div>';
}

add_action('admin_enqueue_scripts', function($hook){
  // Solo cargar scripts en la página de uso/coste exacta.
  if ( $hook !== 'axiachat-ai_page_aichat-usage' ) return;

  // Patrón igual que en class-aichat-core.php: derivar base_path/base_url desde este include
  $base_path = dirname( plugin_dir_path(__FILE__) ) . '/'; // raíz plugin
  $base_url  = dirname( plugin_dir_url(__FILE__) ) . '/';

  // Único archivo Chart.js soportado ahora: chart.umd.min.js
  $chart_rel = 'assets/js/vendor/chart.umd.min.js';
  $usage_rel = 'assets/js/usage.js';
  $chart_path = $base_path . $chart_rel;
  $usage_path = $base_path . $usage_rel;
  if( ! file_exists($chart_path) ) {
    if( function_exists('aichat_log_debug') ) aichat_log_debug('[AIChat Usage] Missing chart.umd.min.js at '.$chart_path);
    return; // evita errores si falta
  }
  if( function_exists('aichat_log_debug') ) aichat_log_debug('[AIChat Usage] Using Chart file: '.$chart_rel);
  $chart_url = $base_url . $chart_rel;
  $usage_url = $base_url . $usage_rel;
  $ver_chart = (string) filemtime($chart_path);
  $ver_usage = file_exists($usage_path) ? (string) filemtime($usage_path) : '1.0.0';

  if( ! wp_script_is('aichat-chartjs','registered') ) {
    wp_register_script('aichat-chartjs', $chart_url, [], $ver_chart, true);
  }
  wp_enqueue_script('aichat-chartjs');

  // Disparar evento incluso si DOMContentLoaded ya pasó (si la página se recarga via SPA futura)
  $inline = "(function(){function fire(){document.dispatchEvent(new CustomEvent('aichat_chart_ready'));} if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fire);}else{fire();}})();";
  wp_add_inline_script('aichat-chartjs', $inline, 'after');

  // Script dashboard usage dependiente de Chart
  $usage_handle = 'aichat-usage';
  wp_enqueue_script($usage_handle, $usage_url, ['jquery','aichat-chartjs'], $ver_usage, true);
  wp_localize_script($usage_handle,'AIChatUsageAjax',[
    'ajax_url'=>admin_url('admin-ajax.php'),
    // Dedicated nonce for usage endpoints (see usage-ajax.php)
    'nonce'=>wp_create_nonce('aichat_usage'),
    'strings'=>[
  'totalTokens'=>esc_html__('Total Tokens','axiachat-ai'),
  'costLabel'=>esc_html__('Cost','axiachat-ai'),
    ],
  ]);
});
