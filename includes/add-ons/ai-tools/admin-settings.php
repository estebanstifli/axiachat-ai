<?php
if ( ! defined('ABSPATH') ) { exit; }

function aichat_tools_settings_page(){
  echo '<div class="wrap aichat-tools-settings">';
  global $wpdb; $bots_table = $wpdb->prefix.'aichat_bots';
  $bots = $wpdb->get_results("SELECT slug,name FROM $bots_table ORDER BY name ASC", ARRAY_A);
  echo '<div id="aichat-tools-panel-header"><h1>'.esc_html__('AI Tools Settings','axiachat-ai').'</h1>';
  echo '<select id="aichat-tools-bot" class="regular-text" style="max-width:240px">';
  if($bots){foreach($bots as $b){ echo '<option value="'.esc_attr($b['slug']).'">'.esc_html($b['name']).'</option>'; }} else { echo '<option value="">'.esc_html__('No bots','axiachat-ai').'</option>'; }
  echo '</select>';
  echo '<button type="button" class="button button-primary" id="aichat-tools-add-rule"><span class="dashicons dashicons-plus"></span> '.esc_html__('New Rule','axiachat-ai').'</button>';
  echo '<button type="button" class="button button-secondary" id="aichat-tools-save" disabled>'.esc_html__('Save','axiachat-ai').'</button>';
  echo '</div>';
  echo '<div id="aichat-capabilities-card" class="card mb-4 shadow-sm" style="border:1px solid #e2e8f0; max-width:860px;">';
  echo '<div class="card-header bg-light d-flex align-items-center" style="border-bottom:1px solid #e2e8f0;">'
    .'<i class="bi bi-lightning-charge-fill text-warning me-2" aria-hidden="true"></i>'
    .'<strong>'.esc_html__('Enabled Capabilities for this Bot','axiachat-ai').'</strong>'
    .'</div>';
  echo '<div class="card-body p-3">';
  echo '<div id="aichat-capabilities-list" class="row gy-2">'
    .'<div class="col-12"><em>'.esc_html__('Loading capabilities...','axiachat-ai').'</em></div>'
    .'</div>';
  echo '<div class="mt-3">'
    .'<button type="button" class="button button-primary" id="aichat-capabilities-save" disabled>'
    .'<i class="bi bi-save me-1" aria-hidden="true"></i>'
    .esc_html__('Save Capabilities','axiachat-ai')
    .'</button>'
    .'<span id="aichat-capabilities-status" class="ms-2" style="font-size:12px;color:#555"></span>'
    .'</div>';
  echo '</div>';
  echo '</div>';
  echo '<p class="description">'.esc_html__('Create conditional rules that trigger automatic agent actions (navigate, speak a message, request info, etc.).','axiachat-ai').'</p>';
  echo '<div id="aichat-tools-builder"></div>';
  echo '<hr />';
  echo '<h2>'.esc_html__('Available Capabilities / Macros','axiachat-ai').'</h2>';
  $macros = function_exists('aichat_get_registered_macros') ? aichat_get_registered_macros() : [];
  if ( $macros ) {
    echo '<table class="widefat striped"><thead><tr><th>'.esc_html__('Macro','axiachat-ai').'</th><th>'.esc_html__('Underlying Tools','axiachat-ai').'</th><th>'.esc_html__('Description','axiachat-ai').'</th></tr></thead><tbody>';
    foreach ( $macros as $m ) {
      $label = $m['label'] ?? $m['name'];
      $desc  = $m['description'] ?? '';
      $tools = !empty($m['tools']) ? implode(', ', array_map('esc_html', $m['tools'])) : '—';
      echo '<tr><td>'.esc_html($label).'</td><td>'.$tools.'</td><td>'.esc_html($desc).'</td></tr>';
    }
    echo '</tbody></table>';
  } else {
    if ( function_exists('aichat_get_registered_tools') ) {
      $tools = aichat_get_registered_tools();
      if ($tools) {
        echo '<p><strong>'.esc_html__('No macros registered yet. Listing atomic tools instead.','axiachat-ai').'</strong></p>';
        echo '<table class="widefat striped"><thead><tr><th>'.esc_html__('Tool','axiachat-ai').'</th><th>'.esc_html__('Type','axiachat-ai').'</th><th>'.esc_html__('Description','axiachat-ai').'</th></tr></thead><tbody>';
        foreach($tools as $id=>$def){
          $name = isset($def['name']) ? $def['name'] : $id; $type = isset($def['type'])?$def['type']:'?'; $desc = isset($def['description'])?$def['description']:'';
          echo '<tr><td>'.esc_html($name).'</td><td>'.esc_html($type).'</td><td>'.esc_html($desc).'</td></tr>';
        }
        echo '</tbody></table>';
      } else {
        echo '<p>'.esc_html__('No tools registered yet.','axiachat-ai').'</p>';
      }
    } else {
      echo '<p>'.esc_html__('Tools API not loaded.','axiachat-ai').'</p>';
    }
  }
  echo '</div>';
}
