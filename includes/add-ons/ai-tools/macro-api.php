<?php
if ( ! defined('ABSPATH') ) { exit; }
// Moved Macro Tools Layer (from includes/macro-tools.php)

global $AICHAT_MACRO_TOOLS;
if ( ! is_array( $AICHAT_MACRO_TOOLS ) ) { $AICHAT_MACRO_TOOLS = []; }

function aichat_register_macro( array $def ){
  global $AICHAT_MACRO_TOOLS;
  $name = isset($def['name']) ? sanitize_key($def['name']) : '';
  if ( $name === '' ) return false;
  $tools = isset($def['tools']) && is_array($def['tools']) ? array_values(array_filter($def['tools'])) : [];
  if ( empty($tools) ) return false;
  $macro = [
    'name'        => $name,
    'label'       => isset($def['label']) ? sanitize_text_field($def['label']) : $name,
    'description' => isset($def['description']) ? sanitize_text_field($def['description']) : '',
    'tools'       => $tools,
  ];
  $AICHAT_MACRO_TOOLS[$name] = $macro;
  return true;
}

function aichat_get_registered_macros(){
  global $AICHAT_MACRO_TOOLS; return $AICHAT_MACRO_TOOLS;
}

function aichat_expand_macros_to_atomic( array $selected_ids ){
  if ( empty($selected_ids) ) return [];
  $macros = aichat_get_registered_macros(); $out = [];
  foreach($selected_ids as $id){
    if ( isset($macros[$id]) ) { foreach($macros[$id]['tools'] as $t){ $out[]=$t; } }
    else { $out[] = $id; }
  }
  $uniq = []; foreach($out as $t){ if(!isset($uniq[$t])) $uniq[$t]=true; } return array_keys($uniq);
}
