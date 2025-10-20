<?php
if ( ! defined('ABSPATH') ) { exit; }

function aichat_tools_settings_page(){
  echo '<div class="wrap aichat-tools-settings">';
  global $wpdb; $bots_table = $wpdb->prefix.'aichat_bots';
  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix; no user input in this query
  $bots = $wpdb->get_results("SELECT slug,name FROM {$bots_table} ORDER BY name ASC", ARRAY_A);
  echo '<div id="aichat-tools-panel-header" class="mb-3"><h1>'.esc_html__('AI Tools Settings','axiachat-ai').'</h1>';
  echo '<select id="aichat-tools-bot" class="regular-text aichat-tools-bot-select">';
  if($bots){foreach($bots as $b){ echo '<option value="'.esc_attr($b['slug']).'">'.esc_html($b['name']).'</option>'; }} else { echo '<option value="">'.esc_html__('No bots','axiachat-ai').'</option>'; }
  echo '</select>';
  echo '</div>';
  // Tabs navigation
  echo '<ul class="nav nav-tabs aichat-tools-tabs" id="aichat-tools-tabs" role="tablist">';
  echo '  <li class="nav-item" role="presentation">'
    . '    <button class="nav-link active" id="tab-capabilities" data-bs-toggle="tab" data-bs-target="#pane-capabilities" type="button" role="tab" aria-controls="pane-capabilities" aria-selected="true">'
    . esc_html__('Capabilities','axiachat-ai')
    . '    </button>'
    . '  </li>';
  echo '  <li class="nav-item" role="presentation">'
    . '    <button class="nav-link" id="tab-rules" data-bs-toggle="tab" data-bs-target="#pane-rules" type="button" role="tab" aria-controls="pane-rules" aria-selected="false">'
    . esc_html__('Rules','axiachat-ai')
    . '    </button>'
    . '  </li>';
  echo '  <li class="nav-item" role="presentation">'
    . '    <button class="nav-link" id="tab-testtools" data-bs-toggle="tab" data-bs-target="#pane-testtools" type="button" role="tab" aria-controls="pane-testtools" aria-selected="false">'
    . esc_html__('Test Tools','axiachat-ai')
    . '    </button>'
    . '  </li>';
  echo '</ul>';

  echo '<div class="tab-content aichat-tab-content-full" id="aichat-tools-tabcontent">';
  // Capabilities pane
  echo '  <div class="tab-pane fade show active pt-3" id="pane-capabilities" role="tabpanel" aria-labelledby="tab-capabilities">';
  echo '    <div id="aichat-capabilities-card" class="card card100 mb-4 shadow-sm aichat-card-border">';
  echo '      <div class="card-header bg-light d-flex align-items-center aichat-card-header-border">'
    . '        <i class="bi bi-lightning-charge-fill text-warning me-2" aria-hidden="true"></i>'
    . '        <strong>'.esc_html__('Enabled Capabilities for this Bot','axiachat-ai').'</strong>'
    . '      </div>';
  echo '      <div class="card-body p-3">';
  echo '        <div id="aichat-capabilities-list" class="row gy-2">'
    . '          <div class="col-12"><em>'.esc_html__('Loading capabilities...','axiachat-ai').'</em></div>'
    . '        </div>';
  echo '        <div class="mt-3">'
    . '          <button type="button" class="button button-primary" id="aichat-capabilities-save" disabled>'
    . '            <i class="bi bi-save me-1" aria-hidden="true"></i>'
    .               esc_html__('Save Capabilities','axiachat-ai')
    . '          </button>'
    . '          <span id="aichat-capabilities-status" class="ms-2 aichat-status-text"></span>'
    . '        </div>';
  echo '      </div>';
  echo '    </div>';
  echo '    <h2>'.esc_html__('Available Capabilities / Macros','axiachat-ai').'</h2>';
  $macros = function_exists('aichat_get_registered_macros') ? aichat_get_registered_macros() : [];
  if ( $macros ) {
    echo '<table class="widefat striped"><thead><tr><th>'.esc_html__('Macro','axiachat-ai').'</th><th>'.esc_html__('Underlying Tools','axiachat-ai').'</th><th>'.esc_html__('Description','axiachat-ai').'</th></tr></thead><tbody>';
    foreach ( $macros as $m ) {
      $label = $m['label'] ?? $m['name'];
      $desc  = $m['description'] ?? '';
  $tools = !empty($m['tools']) ? implode(', ', array_map('esc_html', $m['tools'])) : esc_html__('—','axiachat-ai');
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
  echo '  </div>'; // end capabilities tab pane

  // Rules pane
  echo '  <div class="tab-pane fade pt-3" id="pane-rules" role="tabpanel" aria-labelledby="tab-rules">';
  echo '    <p class="description">'.esc_html__('Create conditional rules that trigger automatic agent actions (navigate, speak a message, request info, etc.).','axiachat-ai').'</p>';
  echo '    <div class="d-flex gap-2 mb-2">';
  echo '      <button type="button" class="button button-primary" id="aichat-tools-add-rule"><span class="dashicons dashicons-plus"></span> '.esc_html__('New Rule','axiachat-ai').'</button>';
  echo '      <button type="button" class="button button-secondary" id="aichat-tools-save" disabled>'.esc_html__('Save','axiachat-ai').'</button>';
  echo '    </div>';
  echo '    <div id="aichat-tools-builder"></div>';
  echo '  </div>'; // end rules tab pane
  
  // Test Tools pane
  echo '  <div class="tab-pane fade pt-3" id="pane-testtools" role="tabpanel" aria-labelledby="tab-testtools">';
  echo '    <div class="card card100 mb-4 shadow-sm aichat-card-border">';
  echo '      <div class="card-header bg-light d-flex align-items-center aichat-card-header-border">'
    . '        <i class="bi bi-bug-fill text-danger me-2" aria-hidden="true"></i>'
    . '        <strong>'.esc_html__('Test Underlying Tools','axiachat-ai').'</strong>'
    . '      </div>';
  echo '      <div class="card-body p-3">';
  echo '        <div class="mb-3">';
  echo '          <label for="aichat-testtool-select" class="form-label">'.esc_html__('Select a tool','axiachat-ai').'</label>';
  echo '          <select id="aichat-testtool-select" class="form-select" disabled><option>'.esc_html__('Loading tools...','axiachat-ai').'</option></select>';
  echo '        </div>';
  echo '        <div id="aichat-testtool-desc" class="text-muted mb-2"></div>';
  echo '        <div id="aichat-testtool-form" class="mb-3"></div>';
  echo '        <div class="d-flex gap-2">';
  echo '          <button type="button" class="button button-primary" id="aichat-testtool-run" disabled>'
    . '            <i class="bi bi-play-fill" aria-hidden="true"></i> '.esc_html__('Test','axiachat-ai')
    . '          </button>';
  echo '          <span id="aichat-testtool-status" class="ms-2 aichat-status-text"></span>';
  echo '        </div>';
  echo '        <hr/>';
  echo '        <div>';
  echo '          <label class="form-label">'.esc_html__('Result','axiachat-ai').'</label>';
  echo '          <pre id="aichat-testtool-result" style="background:#0b1020;color:#d7e1ff;padding:10px;border-radius:6px;overflow:auto;max-height:380px;"></pre>';
  echo '        </div>';
  echo '      </div>';
  echo '    </div>';
  echo '  </div>'; // end test tools pane
  echo '</div>'; // end tab-content
  echo '</div>';
}
