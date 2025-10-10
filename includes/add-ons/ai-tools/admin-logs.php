<?php
if ( ! defined('ABSPATH') ) { exit; }

function aichat_tools_logs_page(){
  global $wpdb; $table = $wpdb->prefix.'aichat_tool_calls';
  echo '<div class="wrap"><h1>'.esc_html__('AI Tools Logs','axiachat-ai').'</h1>';
  $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s", $table));
  if ( ! $exists ) { echo '<p>'.esc_html__('Tool calls table does not exist yet.','axiachat-ai').'</p></div>'; return; }
  $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 500");
  if(!$rows){ echo '<p>'.esc_html__('No tool calls recorded yet.','axiachat-ai').'</p></div>'; return; }
  echo '<table class="widefat striped"><thead><tr>'
    .'<th>ID</th>'
    .'<th>'.esc_html__('Bot','axiachat-ai').'</th>'
    .'<th>'.esc_html__('Tool','axiachat-ai').'</th>'
    .'<th>'.esc_html__('Round','axiachat-ai').'</th>'
    .'<th>'.esc_html__('Duration ms','axiachat-ai').'</th>'
    .'<th>'.esc_html__('Created','axiachat-ai').'</th>'
    .'<th>'.esc_html__('Excerpt','axiachat-ai').'</th>'
    .'</tr></thead><tbody>';
  foreach($rows as $r){
    $excerpt = mb_substr((string)$r->output_excerpt,0,120);
    echo '<tr><td>'.intval($r->id).'</td><td>'.esc_html($r->bot_slug).'</td><td>'.esc_html($r->tool_name).'</td><td>'.intval($r->round).'</td><td>'.intval($r->duration_ms).'</td><td>'.esc_html($r->created_at).'</td><td><code>'.esc_html($excerpt).'</code></td></tr>';
  }
  echo '</tbody></table></div>';
}
