<?php
// SSA Tools and Macros registration
//
// Notas sobre convenciones de registro de tools (importante para mantener claridad):
// - 'description': TEXTO PARA EL MODELO (LLM). Úsalo para explicar CUÁNDO/COMO debe usar la herramienta.
//   Puedes incluir reglas tipo "MUST/SHOULD/NEVER". Este texto NO lo ve el usuario final.
// - 'activity_label': TEXTO PARA LA UI (barra de progreso/estado en Test Tools y/o frontend). El modelo no lo ve.
// - 'name': nombre expuesto al modelo para invocar la tool (function name). Mantener snake_case estable.
// - 'schema': JSON Schema que el modelo usa para construir los argumentos. Las 'descriptions' de cada propiedad
//   también las "lee" el modelo; sé concreto (formatos de fecha, unidades, restricciones). Usar additionalProperties=false.
// - 'timeout' / 'parallel' / 'max_calls': límites de ejecución del servidor (no del modelo); NO afectan al razonamiento del modelo.
// - 'callback': implementación PHP; nunca expongas detalles internos sensibles en su salida.
// - 'strict' (opcional): si está presente y true, fuerza validación estricta de parámetros en algunos proveedores.
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/helpers.php';

if ( ! function_exists('aichat_register_tool_safe') ) {
  if ( function_exists('aichat_register_tool') ) {
    function aichat_register_tool_safe($id,$args){ return aichat_register_tool($id,$args); }
  } else {
    if ( function_exists('aichat_log_debug') ) { aichat_log_debug('SSA tools: AI Tools API missing (aichat_register_tool)'); }
    return;
  }
}
if ( function_exists('aichat_log_debug') ) { aichat_log_debug('SSA tools: starting registration'); }

// Tool: List SSA appointment types
aichat_register_tool_safe( 'aichat_ssa_list_services', [
  'type' => 'function',
  'name' => 'aichat_ssa_list_services',
  // description (para el MODELO): describe claramente el output y cuándo usarla.
  'description' => 'List Simply Schedule Appointments appointment types (id, title, duration in minutes).',
  // activity_label (para la UI): texto breve de estado mientras corre la tool.
  'activity_label' => 'Listing appointment types...',
  // properties must be a JSON object, not an array
  'schema' => [ 'type'=>'object', 'properties' => (object)[], 'required'=>[], 'additionalProperties'=>false ],
  'callback' => function(){
    if ( ! aichat_ssa_is_active() ) return [ 'ok'=>false, 'error'=>'ssa_not_active' ];
    $services = aichat_ssa_get_services();
    return [ 'ok'=>true, 'services'=>$services, 'total'=>count($services) ];
  },
  'timeout' => 6,
  'parallel' => true,
  'max_calls' => 2,
]);
if ( function_exists('aichat_log_debug') ) { aichat_log_debug('SSA tools: registered aichat_ssa_list_services'); }

// Tool: Get upcoming availability slots for an appointment type
aichat_register_tool_safe( 'aichat_ssa_get_availability', [
  'type' => 'function',
  'name' => 'aichat_ssa_get_availability',
  // description (MODELO): detalla formatos de fecha y zona horaria esperada.
  'description' => 'Get upcoming available time slots for a given SSA appointment type. Optional date range (Y-m-d H:i:s, site local time).',
  'activity_label' => 'Fetching appointment availability...',
  'schema' => [
    'type'=>'object',
    'properties' => [
      // Las descriptions de propiedades son leídas por el MODELO para construir args correctos.
      'appointment_type_id' => [ 'type'=>'integer', 'description'=>'SSA appointment type ID' ],
      'from' => [ 'type'=>'string', 'description'=>'Start of range (Y-m-d H:i:s) in site local time; Y-m-d accepted' ],
      'to'   => [ 'type'=>'string', 'description'=>'End of range (Y-m-d H:i:s) in site local time; Y-m-d accepted' ],
      'starts_only' => [ 'type'=>'boolean', 'description'=>'If true, return only start datetimes (Y-m-d H:i:s) instead of full start/end slots' ],
    ],
    'required' => ['appointment_type_id'],
    'additionalProperties' => false
  ],
  'callback' => function( $args ){
    $sid = isset($args['appointment_type_id']) ? (int)$args['appointment_type_id'] : 0;
    $from = isset($args['from']) ? (string)$args['from'] : null;
    $to   = isset($args['to']) ? (string)$args['to'] : null;
    $starts_only = !empty($args['starts_only']);
    if ( $sid <= 0 ) return [ 'ok'=>false, 'error'=>'invalid_service' ];
    $slots = aichat_ssa_get_upcoming_slots( $sid, $from, $to, $starts_only );
    // Normalize response keys depending on mode
    if ($starts_only) {
      return [ 'ok'=>true, 'appointment_type_id'=>$sid, 'starts'=>$slots, 'count'=>count($slots), 'compact'=>true ];
    }
    return [ 'ok'=>true, 'appointment_type_id'=>$sid, 'slots'=>$slots, 'count'=>count($slots), 'compact'=>false ];
  },
  'timeout' => 8,
  'parallel' => true,
  'max_calls' => 2,
]);
if ( function_exists('aichat_log_debug') ) { aichat_log_debug('SSA tools: registered aichat_ssa_get_availability'); }

// Tool: Create appointment (using booking agent)
// NOTE: This should generally be guarded by server-side policy/macros and possibly captcha/confirmation flows.
aichat_register_tool_safe( 'aichat_ssa_create_appointment', [
  'type' => 'function',
  'name' => 'aichat_ssa_create_appointment',
  // description (MODELO): explica precondiciones y formato estricto del campo start y customer.
  'description' => 'Create an appointment in Simply Schedule Appointments. Requires appointment_type_id, customer info and a selected start datetime (Y-m-d H:i:s) in site local time.',
  'activity_label' => 'Creating appointment...',
  'schema' => [
    'type'=>'object',
    'properties' => [
      // Las descriptions aquí guían al MODELO; mantener nombres/formatos exactos.
      'appointment_type_id' => [ 'type'=>'integer', 'description'=>'SSA appointment type ID' ],
      'start' => [ 'type'=>'string', 'description'=>'Selected slot start (Y-m-d H:i:s) in site local time; Y-m-d accepted' ],
      'customer' => [ 'type'=>'object', 'description'=>'Customer info', 'properties'=>[
        'name'  => [ 'type'=>'string' ],
        'email' => [ 'type'=>'string' ],
        'phone' => [ 'type'=>'string' ],
      ], 'required'=>['email'], 'additionalProperties'=>false ],
    ],
    'required' => ['appointment_type_id','start','customer'],
    'additionalProperties' => false
  ],
  'callback' => function( $args, $ctx = [] ){
    $sid = isset($args['appointment_type_id']) ? (int)$args['appointment_type_id'] : 0;
    $start = isset($args['start']) ? (string)$args['start'] : '';
    $cust = isset($args['customer']) && is_array($args['customer']) ? $args['customer'] : [];
    // Route via booking agent helper so it handles timezone and availability validations
    if ( ! function_exists('aichat_ssa_reservar') ) {
      // Fallback to legacy helper if booking agent not present (convert start to timestamp UTC if needed)
      $start_ts = strtotime($start);
      $res = aichat_ssa_create_appointment( $sid, $cust, $start_ts ?: 0 );
    } else {
      $res = aichat_ssa_reservar( $sid, $start, 'pending_form', $cust, [ 'input_timezone' => 'local' ] );
    }
    return $res;
  },
  'timeout' => 10,
  'parallel' => false,
  'max_calls' => 1,
]);
if ( function_exists('aichat_log_debug') ) { aichat_log_debug('SSA tools: registered aichat_ssa_create_appointment'); }

// Macros for SSA flows
if ( function_exists('aichat_register_macro') ) {
  // Macros: agrupan tools para facilitar su selección en bots. Las descriptions/labels de macros son para ADMIN/UI.
  // El MODELO no "ve" las macros; ve las tools resultantes activadas en el bot.
  aichat_register_macro([
    'name' => 'ssa_availability',
    'label' => 'SSA: Availability',
    'description' => 'Enable assistant to list appointment types and fetch availability slots for a selected type.', // UI/Admin
    'tools' => ['aichat_ssa_list_services','aichat_ssa_get_availability']
  ]);
  aichat_register_macro([
    'name' => 'ssa_booking',
    'label' => 'SSA: Booking',
    'description' => 'Enable assistant to create appointments after the user confirms a slot.', // UI/Admin
    'tools' => ['aichat_ssa_create_appointment']
  ]);
  if ( function_exists('aichat_log_debug') ) { aichat_log_debug('SSA tools: macros registered'); }
} else {
  if ( function_exists('aichat_log_debug') ) { aichat_log_debug('SSA tools: macro API missing (aichat_register_macro)'); }
}
