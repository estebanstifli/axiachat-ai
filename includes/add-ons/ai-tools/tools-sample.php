<?php
// Moved sample tools from includes/tools-sample.php
//
// Convenciones de registro de tools:
// - 'description' (MODELO): guía de uso para el LLM (cuándo/cómo usar la tool, formatos, restricciones).
// - 'activity_label' (UI): texto visible en UI cuando la tool está "ejecutándose" (spinner/progreso).
// - 'schema': parámetros con tipos + descriptions que el MODELO lee para construir llamadas correctas.
// - 'name' y 'type': identificadores de la tool que ve el MODELO; mantener estables.
// - Las descriptions en propiedades del schema deben ser explícitas (formato de fechas, unidades, rangos).
if ( ! defined('ABSPATH') ) { exit; }

if ( ! function_exists('aichat_register_tool_safe') ) {
    if ( function_exists('aichat_register_tool') ) {
        function aichat_register_tool_safe($id,$args){ return aichat_register_tool($id,$args); }
    } else { return; }
}

// (Removed) Demo utilities: util_get_datetime, util_mortgage_payment

aichat_register_tool_safe( 'util_list_categories', [
  'type'=>'function','name'=>'util_list_categories','description'=>'MUST be called whenever the user asks (in English or Spanish) about blog categories. NEVER guess: always invoke this tool to fetch real categories with names, slugs and counts.',
  'activity_label'=>'Fetching real blog categories...', // UI
  'schema'=>['type'=>'object','properties'=>[
    'with_counts'=>['type'=>'boolean','description'=>'If true include post counts (default true).'],
    'limit'=>['type'=>'integer','description'=>'Optional max number of categories to return (default all).']
  ],'required'=>[],'additionalProperties'=>false],
  'callback'=>function($args){ $with_counts=isset($args['with_counts'])?(bool)$args['with_counts']:true; $limit=isset($args['limit'])?max(1,(int)$args['limit']):0; $tax_args=['taxonomy'=>'category','hide_empty'=>false]; $terms=get_terms($tax_args); if(is_wp_error($terms)){return ['error'=>'taxonomy_error','message'=>$terms->get_error_message()];} $out=[]; foreach($terms as $t){ $item=['name'=>$t->name,'slug'=>$t->slug]; if($with_counts){$item['count']=(int)$t->count;} $out[]=$item; if($limit && count($out)>=$limit) break; } return ['categories'=>$out,'total'=>count($out)]; },
  'timeout'=>5,'parallel'=>true,'max_calls'=>1]);


if ( function_exists('aichat_register_macro') ) {
  // Removed demo macro 'basic_utilities_demo'
  aichat_register_macro(['name'=>'content_categories','label'=>'Content: Blog Categories','description'=>'Allows the assistant to list real WordPress blog categories (names, slugs, counts).','tools'=>['util_list_categories']]);  
}

// === OpenAI Web Search ===
// We register a dummy atomic tool so the macro can reference it, but it's type 'custom' so it's not included in CC function tools.
aichat_register_tool_safe('__builtin_openai_web_search', [
  'type' => 'custom',
  'name' => 'openai_web_search_builtin',
  'description' => 'Builtin OpenAI Web Search tool . Selecting this enables web search on compatible models.', // MODELO
  'callback' => '__return_null'
]);

if ( function_exists('aichat_register_macro') ) {
  aichat_register_macro([
    'name' => 'openai_web_search',
    'label' => 'OpenAI: Web Search',
    'description' => 'Allows the assistant to use OpenAI built-in Web Search.', // UI/Admin
    'tools' => ['__builtin_openai_web_search']
  ]);
}

// ============ Email (Admin Only) ============
// Safe default: always sends to site admin email, ignoring custom recipient to avoid misuse from visitor prompts.
// Recommend enabling only on internal/ops bots and guarding via system instructions.
aichat_register_tool_safe( 'aichat_send_email_admin', [
  'type' => 'function',
  'name' => 'aichat_send_email_admin',
  'description' => 'Send an email notification to the site admin. For internal assistant-initiated notifications only—never on direct user request.',
  'activity_label' => 'Sending email notification to admin...',
  'schema' => [
    'type' => 'object',
    'properties' => [
      'subject' => [ 'type'=>'string', 'description'=>'Email subject (short, plain text).' ],
      'message' => [ 'type'=>'string', 'description'=>'Email message body.' ],
      'html'    => [ 'type'=>'boolean', 'description'=>'If true, body will be sent as HTML.', 'default'=>false ],
      'from_name'  => [ 'type'=>'string', 'description'=>'Optional From name (restricted to site domain).'],
      'from_email' => [ 'type'=>'string', 'description'=>'Optional From email (must match site domain).'],
      // Note: No freeform "to"—we intentionally send to admin_email to prevent abuse.
    ],
    'required' => ['subject','message'],
    'additionalProperties' => false
  ],
  'callback' => function( $args, $ctx = [] ){
    // Resolve admin recipient only
    $to = get_option('admin_email');
    if ( ! is_email( $to ) ) {
      return [ 'ok'=>false, 'error'=>'no_admin_email' ];
    }
    $subject = isset($args['subject']) ? sanitize_text_field( (string)$args['subject'] ) : '';
    $message = isset($args['message']) ? (string)$args['message'] : '';
    $html = ! empty($args['html']);
    if ( $subject === '' || $message === '' ) {
      return [ 'ok'=>false, 'error'=>'missing_subject_or_message' ];
    }
    $headers = [];
    if ( $html ) {
      $headers[] = 'Content-Type: text/html; charset=UTF-8';
      // allow basic HTML only
      $message = wp_kses_post( $message );
    } else {
      $message = wp_strip_all_tags( $message );
    }
    // Restrict optional From to same domain as site to avoid spoofing
  $home_host = wp_parse_url( home_url(), PHP_URL_HOST );
    $from_name = isset($args['from_name']) ? sanitize_text_field( (string)$args['from_name'] ) : '';
    $from_email = isset($args['from_email']) ? sanitize_email( (string)$args['from_email'] ) : '';
    if ( $from_email && is_email($from_email) ) {
      $from_host = substr(strrchr($from_email, '@'), 1);
      if ( is_string($home_host) && is_string($from_host) && strcasecmp($home_host, $from_host) === 0 ) {
        $from = $from_name ? sprintf('%s <%s>', $from_name, $from_email) : $from_email;
        $headers[] = 'From: ' . $from;
      }
    }
    $sent = wp_mail( $to, $subject, $message, $headers );
    if ( ! $sent ) {
      return [ 'ok'=>false, 'error'=>'send_failed' ];
    }
    // Do not echo back full message; return a compact status
    return [ 'ok'=>true, 'to'=>'admin', 'subject'=>mb_substr($subject,0,140) ];
  },
  'timeout' => 8,
  'parallel' => false,
  'max_calls' => 1
] );

if ( function_exists('aichat_register_macro') ) {
  aichat_register_macro([
    'name' => 'notifications_email_admin',
    'label' => 'Notifications: Email Admin',
    'description' => 'Allows the assistant to send an email notification to the site admin (internal use only).',
    'tools' => ['aichat_send_email_admin']
  ]);
}

// ============ Email (Client) ============
// Safer default: requires explicit server-side authorization via filter 'aichat_can_send_client_email'.
// By default this tool is DENIED (returns not_authorized) to prevent user-triggered misuse.
// You can authorize on specific conditions (e.g., when appointment is confirmed) using the filter.
aichat_register_tool_safe( 'aichat_send_email_client', [
  'type' => 'function',
  'name' => 'aichat_send_email_client',
  'description' => 'Send an email to a customer. Disabled by default; requires server-side authorization filter. Use for appointment confirmations, etc.',
  'activity_label' => 'Sending email to client...',
  'schema' => [
    'type' => 'object',
    'properties' => [
      'to'      => [ 'type'=>'string', 'description'=>'Recipient email address (customer).' ],
      'subject' => [ 'type'=>'string', 'description'=>'Email subject (short, plain text).' ],
      'message' => [ 'type'=>'string', 'description'=>'Email message body.' ],
      'html'    => [ 'type'=>'boolean', 'description'=>'If true, body will be sent as HTML.', 'default'=>false ],
      'from_name'  => [ 'type'=>'string', 'description'=>'Optional From name (restricted to site domain).'],
      'from_email' => [ 'type'=>'string', 'description'=>'Optional From email (must match site domain).'],
    ],
    'required' => ['to','subject','message'],
    'additionalProperties' => false
  ],
  'callback' => function( $args, $ctx = [] ){
    $to = isset($args['to']) ? sanitize_email( (string)$args['to'] ) : '';
    if ( ! is_email( $to ) ) { return [ 'ok'=>false, 'error'=>'invalid_email' ]; }
    // Authorization gate (default: false)
    $allowed = apply_filters( 'aichat_can_send_client_email', false, $to, $ctx, $args );
    if ( ! $allowed ) { return [ 'ok'=>false, 'error'=>'not_authorized' ]; }
    // Simple rate limit per session+recipient (10 minutes)
    $session = isset($ctx['session_id']) ? (string)$ctx['session_id'] : '';
    $rl_key = 'aichat_emcli_'.md5( strtolower($session.'|'.$to) );
    if ( get_transient($rl_key) ) { return [ 'ok'=>false, 'error'=>'rate_limited' ]; }
    $subject = isset($args['subject']) ? sanitize_text_field( (string)$args['subject'] ) : '';
    $message = isset($args['message']) ? (string)$args['message'] : '';
    $html = ! empty($args['html']);
    if ( $subject === '' || $message === '' ) { return [ 'ok'=>false, 'error'=>'missing_subject_or_message' ]; }
    $headers = [];
    if ( $html ) { $headers[] = 'Content-Type: text/html; charset=UTF-8'; $message = wp_kses_post( $message ); }
    else { $message = wp_strip_all_tags( $message ); }
    // Restrict From to site domain to avoid spoofing
  $home_host = wp_parse_url( home_url(), PHP_URL_HOST );
    $from_name = isset($args['from_name']) ? sanitize_text_field( (string)$args['from_name'] ) : '';
    $from_email = isset($args['from_email']) ? sanitize_email( (string)$args['from_email'] ) : '';
    if ( $from_email && is_email($from_email) ) {
      $from_host = substr(strrchr($from_email, '@'), 1);
      if ( is_string($home_host) && is_string($from_host) && strcasecmp($home_host, $from_host) === 0 ) {
        $from = $from_name ? sprintf('%s <%s>', $from_name, $from_email) : $from_email;
        $headers[] = 'From: ' . $from;
      }
    }
    $sent = wp_mail( $to, $subject, $message, $headers );
    if ( ! $sent ) { return [ 'ok'=>false, 'error'=>'send_failed' ]; }
    set_transient( $rl_key, 1, 10 * MINUTE_IN_SECONDS );
    // Return minimal info
    $mask = function($email){ $parts = explode('@',$email); if(count($parts)!==2) return $email; $name=$parts[0]; $dom=$parts[1]; $name_mask = strlen($name)>2 ? substr($name,0,1).'***'.substr($name,-1) : '*'; return $name_mask.'@'.$dom; };
    return [ 'ok'=>true, 'to'=>$mask($to), 'subject'=>mb_substr($subject,0,140) ];
  },
  'timeout' => 8,
  'parallel' => false,
  'max_calls' => 1
] );

if ( function_exists('aichat_register_macro') ) {
  aichat_register_macro([
    'name' => 'notifications_email_client',
    'label' => 'Notifications: Email Client',
    'description' => 'Allows the assistant to send an email to a customer (requires server-side authorization).',
    'tools' => ['aichat_send_email_client']
  ]);
}
