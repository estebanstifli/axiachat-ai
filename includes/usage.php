<?php
if(!defined('ABSPATH')) exit;

/** Admin page: usage and cost */

function aichat_usage_admin_page(){
  if(!current_user_can('manage_options')) return;
  echo '<div class="wrap"><h1>AI Chat – Usage / Cost</h1>';
  echo '<p class="description">Token & cost metrics (chat only). Costs are approximate based on configured pricing.</p>';
  echo '<div id="aichat-usage-kpis" class="aichat-usage-grid" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;">'
      .'<div class="usage-box" style="flex:1;min-width:180px;background:#fff;border:1px solid #ddd;padding:12px;border-radius:6px;"><strong>Today</strong><br><span data-kpi="today-cost">-</span><br><small><span data-kpi="today-tokens">-</span> tokens</small></div>'
      .'<div class="usage-box" style="flex:1;min-width:180px;background:#fff;border:1px solid #ddd;padding:12px;border-radius:6px;"><strong>Last 7 days</strong><br><span data-kpi="last7-cost">-</span><br><small><span data-kpi="last7-tokens">-</span> tokens</small></div>'
      .'<div class="usage-box" style="flex:1;min-width:180px;background:#fff;border:1px solid #ddd;padding:12px;border-radius:6px;"><strong>Last 30 days</strong><br><span data-kpi="last30-cost">-</span><br><small><span data-kpi="last30-tokens">-</span> tokens</small></div>'
      .'</div>';
  echo '<h2 style="margin-top:30px;">Timeseries (Last 30 days)</h2>';
  echo '<canvas id="aichat-usage-chart" height="140"></canvas>';
  echo '<h2 style="margin-top:30px;">Top Models (30d)</h2>';
  echo '<table class="widefat" id="aichat-usage-topmodels"><thead><tr><th>Model</th><th>Provider</th><th>Cost (USD)</th></tr></thead><tbody><tr><td colspan="3">Loading...</td></tr></tbody></table>';
  echo '</div>';
}

add_action('admin_enqueue_scripts', function($hook){
  if($hook !== 'ai-chat_page_aichat-usage') return;
  wp_enqueue_script('chartjs', plugin_dir_url(__FILE__).'../assets/js/vendor/chart.4.4.0.min.js', [], '4.4.0', true);
  wp_enqueue_script('aichat-usage', plugin_dir_url(__FILE__).'../assets/js/usage.js', ['jquery','chartjs'], filemtime(plugin_dir_path(__FILE__).'../assets/js/usage.js'), true);
  wp_localize_script('aichat-usage','AIChatUsageAjax',[
    'ajax_url'=>admin_url('admin-ajax.php'),
    'nonce'=>wp_create_nonce('aichat_ajax'),
  ]);
});
