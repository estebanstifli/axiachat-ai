<?php
if ( ! defined('ABSPATH') ) { exit; }
/**
 * Macro Tools Layer (Admin-visible abstraction)
 *
 * Purpose:
 *  - Allow admin to enable high-level capabilities (macros) per bot.
 *  - Each macro expands internally to one or more atomic tools (already registered via aichat_register_tool).
 *  - The LLM ONLY receives atomic tools. Macros are never exposed directly to the model.
 *  - Backwards compatible: bots.tools_json may still contain atomic tool names directly.
 *
 * Minimal schema for a macro definition:
 *  [
 *    'name'        => 'GestionarAgenda',          // slug (unique, required)
 *    'label'       => 'Gestionar Agenda',         // human label (optional)
 *    'description' => 'Reserva y consulta citas', // admin description (optional)
 *    'tools'       => ['Calendar_ListFreeSlots','Calendar_CreateEvent'] // array of atomic tool names (required, >=1)
 *  ]
 *
 * Registration example (place in a custom plugin or in an init hook):
 *   add_action('init', function(){
 *     if ( function_exists('aichat_register_macro') ) {
 *       aichat_register_macro([
 *         'name' => 'GestionarAgenda',
 *         'label'=> 'Gestionar Agenda (Google)',
 *         'description' => 'Consultar huecos y crear eventos de calendario.',
 *         'tools' => ['Calendar_ListFreeSlots','Calendar_CreateEvent']
 *       ]);
 *     }
 *   });
 *
 * Use aichat_get_registered_macros() to retrieve all macros.
 */

global $AICHAT_MACRO_TOOLS;
if ( ! is_array( $AICHAT_MACRO_TOOLS ) ) {
  $AICHAT_MACRO_TOOLS = [];
}

/**
 * Register a macro (idempotent by name).
 *
 * @param array $def
 * @return bool success
 */
function aichat_register_macro( array $def ){  
  global $AICHAT_MACRO_TOOLS;
  $name = isset($def['name']) ? sanitize_key($def['name']) : '';
  if ( $name === '' ) return false;
  $tools = isset($def['tools']) && is_array($def['tools']) ? array_values(array_filter($def['tools'])) : [];
  if ( empty($tools) ) return false; // must have at least one atomic tool
  $macro = [
    'name'        => $name,
    'label'       => isset($def['label']) ? sanitize_text_field($def['label']) : $name,
    'description' => isset($def['description']) ? sanitize_text_field($def['description']) : '',
    'tools'       => $tools,
  ];
  $AICHAT_MACRO_TOOLS[$name] = $macro;
  return true;
}

/**
 * Retrieve all registered macros.
 *
 * @return array
 */
function aichat_get_registered_macros(){
  global $AICHAT_MACRO_TOOLS;
  return $AICHAT_MACRO_TOOLS;
}

/**
 * Expand an array of admin-selected identifiers (macros OR atomic tool names)
 * into the final unique list of atomic tool names.
 *
 * @param array $selected_ids values stored in bots.tools_json
 * @return array list of atomic tool names (unique)
 */
function aichat_expand_macros_to_atomic( array $selected_ids ){
  if ( empty($selected_ids) ) return [];
  $macros = aichat_get_registered_macros();
  $out = [];
  foreach ( $selected_ids as $id ) {
    if ( isset($macros[$id]) ) {
      foreach ( $macros[$id]['tools'] as $tname ) {
        $out[] = $tname;
      }
    } else {
      // treat as atomic tool name directly (backwards compatibility)
      $out[] = $id;
    }
  }
  // unique preserving first appearance
  $uniq = [];
  foreach ( $out as $t ) { if ( ! isset($uniq[$t]) ) { $uniq[$t]=true; } }
  return array_keys($uniq);
}
