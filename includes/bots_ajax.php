<?php
/**
 * AI Chat - Bots AJAX (full schema)
 */

if ( ! defined('ABSPATH') ) { exit; }

add_action('wp_ajax_aichat_bots_list',     'aichat_bots_list');
add_action('wp_ajax_aichat_bot_create',    'aichat_bot_create');
add_action('wp_ajax_aichat_bot_update',    'aichat_bot_update');
add_action('wp_ajax_aichat_bot_duplicate', 'aichat_bot_duplicate');
add_action('wp_ajax_aichat_bot_reset',     'aichat_bot_reset');
add_action('wp_ajax_aichat_bot_delete',    'aichat_bot_delete');

function aichat_bots_table(){ global $wpdb; return $wpdb->prefix.'aichat_bots'; }
function aichat_bots_log($m,$ctx=[]){ error_log('[AIChat Bots AJAX] '.$m.( $ctx? ' | '.wp_json_encode($ctx):'' )); }
function aichat_bots_check(){ if(!current_user_can('manage_options')){ wp_send_json_error(['message'=>'Forbidden'],403);} check_ajax_referer('aichat_bots_nonce','nonce'); }


function aichat_bots_insert_default(){
  global $wpdb;
  $table = aichat_bots_table();
  $defaults = aichat_bots_defaults();
  $wpdb->insert($table, $defaults);
}

function aichat_bots_defaults($over=[]){
  $now = current_time('mysql');
  $d = [
    'name' => 'Default', 'slug'=>'default', 'type'=>'text', 'instructions'=>'',
    'provider'=>'openai','model'=>'gpt-4o','temperature'=>0.70,'max_tokens'=>2048,'reasoning'=>'off','verbosity'=>'medium',
    'context_mode'=>'embeddings','context_id'=>0,
    'input_max_length'=>512,'max_messages'=>20,'context_max_length'=>4096,
    'ui_color'=>'#1a73e8','ui_position'=>'br','ui_avatar_enabled'=>0,'ui_avatar_key'=>null,'ui_icon_url'=>null,'ui_start_sentence'=>'Hi! How can I help you?',
    /* nuevos por defecto */
    'ui_placeholder'  => 'Write your question...',
    'ui_button_send'  => 'Send',
    'ui_closable'           => 1,
    'ui_minimizable'        => 1,
    'ui_draggable'          => 1,
    'ui_minimized_default'  => 0,
    'is_active'=>1,'created_at'=>$now,'updated_at'=>$now
  ];
  return array_merge($d,$over);
}

function aichat_bots_unique_slug($slug,$exclude=0){
  global $wpdb; $t=aichat_bots_table();
  $base = sanitize_title($slug); if($base==='') $base='bot';
  $try=$base; $i=2;
  while(true){
    $cnt = (int)$wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $t WHERE slug=%s AND id<>%d",$try,(int)$exclude) );
    if($cnt===0) return $try;
    $try = $base.'-'.$i; $i++; if($i>9999) return $base.'-'.time();
  }
}

function aichat_bots_sanitize_patch($patch,$row=null){
  $out=[];

  // alias legacy
  if (isset($patch['label']) && !isset($patch['name'])) $patch['name']=$patch['label'];
  if (isset($patch['mode'])  && !isset($patch['type'])) $patch['type']=$patch['mode'];

  // básicos
  if (isset($patch['name']))         $out['name'] = sanitize_text_field($patch['name']);
  if (isset($patch['slug']))         $out['slug'] = sanitize_title($patch['slug']);
  if (isset($patch['instructions'])) $out['instructions'] = wp_kses_post($patch['instructions']);
  if (isset($patch['type'])) {
    $t = sanitize_text_field($patch['type']);
    $out['type'] = in_array($t,['text','voice_text'],true) ? $t : 'text';
  }

  // modelo
  if (isset($patch['provider']))  $out['provider']  = sanitize_text_field($patch['provider']);
  if (isset($patch['model']))     $out['model']     = sanitize_text_field($patch['model']);
  if (isset($patch['temperature'])) {
    $temp = floatval($patch['temperature']); if ($temp<0) $temp=0; if($temp>2) $temp=2; $out['temperature']=$temp;
  }
  if (isset($patch['max_tokens']))       $out['max_tokens'] = max(1, intval($patch['max_tokens']));
  if (isset($patch['reasoning'])) {
    $r = sanitize_text_field($patch['reasoning']);
    $out['reasoning'] = in_array($r,['off','fast','accurate'],true)?$r:'off';
  }
  if (isset($patch['verbosity'])) {
    $v = sanitize_text_field($patch['verbosity']);
    $out['verbosity'] = in_array($v,['low','medium','high'],true)?$v:'medium';
  }

  // contexto
  if (isset($patch['context_mode'])) {
    $cm = sanitize_text_field($patch['context_mode']);
    $out['context_mode'] = in_array($cm,['embeddings','page','none'],true)?$cm:'embeddings';
  }
  if (isset($patch['context_id'])) $out['context_id'] = max(0, intval($patch['context_id']));

  // thresholds
  if (isset($patch['input_max_length']))    $out['input_max_length'] = max(1,intval($patch['input_max_length']));
  if (isset($patch['max_messages']))        $out['max_messages']     = max(1,intval($patch['max_messages']));
  if (isset($patch['context_max_length']))  $out['context_max_length']= max(128,intval($patch['context_max_length']));

  // UI
  if (isset($patch['ui_color']))        $out['ui_color'] = preg_match('/^#[0-9a-fA-F]{6}$/',$patch['ui_color']) ? $patch['ui_color'] : '#1a73e8';
  if (isset($patch['ui_position'])) {
    $p = sanitize_text_field($patch['ui_position']);
    $out['ui_position'] = in_array($p,['br','bl','tr','tl'],true)?$p:'br';
  }
  if (isset($patch['ui_avatar_enabled'])) $out['ui_avatar_enabled'] = intval(!!$patch['ui_avatar_enabled']);
  if (isset($patch['ui_avatar_key']))     $out['ui_avatar_key']     = sanitize_text_field($patch['ui_avatar_key']);
  if (isset($patch['ui_icon_url']))       $out['ui_icon_url']       = esc_url_raw($patch['ui_icon_url']);
  if (isset($patch['ui_start_sentence'])) $out['ui_start_sentence'] = sanitize_text_field($patch['ui_start_sentence']);

  /* nuevos campos UI */
  if (isset($patch['ui_placeholder']))    $out['ui_placeholder']    = sanitize_text_field($patch['ui_placeholder']);
  if (isset($patch['ui_button_send']))    $out['ui_button_send']    = sanitize_text_field($patch['ui_button_send']);
  if (isset($patch['ui_closable']))           $out['ui_closable']          = intval(!!$patch['ui_closable']);
  if (isset($patch['ui_minimizable']))        $out['ui_minimizable']       = intval(!!$patch['ui_minimizable']);
  if (isset($patch['ui_draggable']))          $out['ui_draggable']         = intval(!!$patch['ui_draggable']);
  if (isset($patch['ui_minimized_default']))  $out['ui_minimized_default'] = intval(!!$patch['ui_minimized_default']);

  // Coherencia provider ↔ modelo
  if (isset($out['provider']) || isset($out['model'])) {
    $prov = isset($out['provider']) ? $out['provider'] : ($row['provider'] ?? 'openai');
    $model = isset($out['model']) ? $out['model'] : ($row['model'] ?? '');

    if ($prov === 'anthropic') {
      if (strpos($model, 'claude-') !== 0) {
        $out['model'] = 'claude-3-5-sonnet-20240620';
      }
     // Validar modelos obsoletos sin versiones específicas
     if (in_array($model, ['claude-3-5-sonnet', 'claude-3-5-sonnet-latest', 'claude-3-5-opus', 
                         'claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku'])) {
      $out['model'] = 'claude-3-5-sonnet-20240620';
    }
    if (isset($out['model'])) {
        $alias = strtolower($out['model']);
        $map = [
          'claude-3-5-sonnet' => 'claude-3-5-sonnet-20240620',
          'claude-3-opus'     => 'claude-3-opus-20240229',
          'claude-3-sonnet'   => 'claude-3-sonnet-20240229',
          'claude-3-haiku'    => 'claude-3-haiku-20240307',
          'claude-3-5-sonnet-latest' => 'claude-3-5-sonnet-20240620',
        ];
        if (isset($map[$alias])) $out['model'] = $map[$alias];
    }
    } else { // openai / otros
      if (strpos($model, 'claude-') === 0) {
        $out['model'] = 'gpt-4o';
      }
    }
  }

  return $out;
}

function aichat_bots_cast_row($r){
  if (!is_array($r)) return $r;
  $ints   = ['id','context_id','max_tokens','input_max_length','max_messages','context_max_length','is_active'];
  $bools  = ['ui_avatar_enabled','ui_closable','ui_minimizable','ui_draggable','ui_minimized_default'];
  $floats = ['temperature'];
  foreach($ints as $k){ if(isset($r[$k])) $r[$k] = (int)$r[$k]; }
  foreach($bools as $k){ if(isset($r[$k])) $r[$k] = (int)!empty($r[$k]); }
  foreach($floats as $k){ if(isset($r[$k])) $r[$k] = (float)$r[$k]; }
  return $r;
}
 
/* ---------- LIST ---------- */
function aichat_bots_list(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY id ASC", ARRAY_A);
  $rows = array_map('aichat_bots_cast_row', (array)$rows);

  if (empty($rows)) {
    $def = aichat_bots_defaults();
    $def['slug'] = aichat_bots_unique_slug($def['slug'], 0);
    $wpdb->insert($t, $def);
    $id = (int)$wpdb->insert_id;
    $rows = [ array_merge(['id'=>$id], aichat_bots_cast_row($def)) ];
  }

  $out = array_map(function($r){
    $r['label'] = $r['name'];
    $r['mode']  = $r['type'];
    $r['is_default'] = ($r['slug']==='default') ? 1 : 0;
    return $r;
  }, $rows);

  aichat_bots_log('LIST ok', ['count'=>count($out)]);
  wp_send_json_success($out);
}

/* ---------- CREATE ---------- */
function aichat_bot_create(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  $now = current_time('mysql');
  $row = aichat_bots_defaults([
    'name'=>'New Bot','slug'=>'new-bot','type'=>'text','created_at'=>$now,'updated_at'=>$now
  ]);
  $row['slug'] = aichat_bots_unique_slug($row['slug'], 0);

  $ok = $wpdb->insert($t,$row);
  if (!$ok) { aichat_bots_log('CREATE error', ['db_error'=>$wpdb->last_error]); wp_send_json_error(['message'=>'DB insert error'],500); }

  $id = (int)$wpdb->insert_id;
  $r  = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  $r  = aichat_bots_cast_row($r);
  $r['label']=$r['name']; $r['mode']=$r['type']; $r['is_default']=($r['slug']==='default')?1:0;

  aichat_bots_log('CREATE ok',['id'=>$id]);
  wp_send_json_success($r);
}

/* ---------- UPDATE ---------- */
function aichat_bot_update(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
  if ($id<=0) wp_send_json_error(['message'=>'Missing id'],400);

  $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  if (!$row) wp_send_json_error(['message'=>'Bot not found'],404);

  $raw = isset($_POST['patch']) ? $_POST['patch'] : '{}';
  $patch = is_string($raw) ? json_decode(stripslashes($raw), true) : (array)$raw;
  if (!is_array($patch)) $patch=[];

  $data = aichat_bots_sanitize_patch($patch, $row);

  if (isset($data['slug'])) $data['slug'] = aichat_bots_unique_slug($data['slug'], $id);

  if (empty($data)) { aichat_bots_log('UPDATE noop',['id'=>$id]); wp_send_json_success(['updated'=>false,'id'=>$id]); }

  $data['updated_at'] = current_time('mysql');

  aichat_bots_log('UPDATE', ['id'=>$id,'fields'=>array_keys($data)]);
  $ok = $wpdb->update($t, $data, ['id'=>$id]);
  if ($ok===false) { aichat_bots_log('UPDATE error',['db_error'=>$wpdb->last_error]); wp_send_json_error( [ 'message' => __( 'Database update error.', 'aichat' ) ], 500 ); }

  $r = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  $r = aichat_bots_cast_row($r);
  $r['label']=$r['name']; $r['mode']=$r['type']; $r['is_default']=($r['slug']==='default')?1:0;
  wp_send_json_success(['updated'=>true,'bot'=>$r]);
}

/* ---------- DUPLICATE ---------- */
function aichat_bot_duplicate(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
  if ($id<=0) wp_send_json_error(['message'=>'Missing id'],400);

  $r = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  if (!$r) wp_send_json_error(['message'=>'Bot not found'],404);

  $now = current_time('mysql');
  $copy = $r; unset($copy['id']); // copia todo
  $copy['name'] = trim($r['name'])!=='' ? $r['name'].' Copy' : 'Bot Copy';
  $copy['slug'] = aichat_bots_unique_slug(($r['slug']?:'bot').'-copy',0);
  $copy['created_at'] = $now; $copy['updated_at'] = $now;

  $ok = $wpdb->insert($t, $copy);
  if (!$ok) { aichat_bots_log('DUP error',['db_error'=>$wpdb->last_error]); wp_send_json_error(['message'=>'DB insert error'],500); }

  $new_id = (int)$wpdb->insert_id;
  $nr = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$new_id), ARRAY_A );
  $nr = aichat_bots_cast_row($nr);
  $nr['label']=$nr['name']; $nr['mode']=$nr['type']; $nr['is_default']=($nr['slug']==='default')?1:0;
  wp_send_json_success($nr);
}

/* ---------- RESET ---------- */
function aichat_bot_reset(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
  if ($id<=0) wp_send_json_error(['message'=>'Missing id'],400);

  $r = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  if (!$r) wp_send_json_error(['message'=>'Bot not found'],404);

  // Mantener slug, resetear el resto a defaults
  $now = current_time('mysql');
  $d = aichat_bots_defaults();
  unset($d['slug'], $d['created_at']); // preservamos slug y created_at original
  $d['name'] = ($r['slug']==='default') ? 'Default' : 'New Bot';
  $d['updated_at'] = $now;

  $ok = $wpdb->update($t, $d, ['id'=>$id]);
  if ($ok===false) { aichat_bots_log('RESET error',['db_error'=>$wpdb->last_error]); wp_send_json_error(['message'=>'DB update error'],500); }

  $nr = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  $nr = aichat_bots_cast_row($nr);
  $nr['label']=$nr['name']; $nr['mode']=$nr['type']; $nr['is_default']=($nr['slug']==='default')?1:0;
  wp_send_json_success($nr);
}

/* ---------- DELETE ---------- */
function aichat_bot_delete(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
  if ($id<=0) wp_send_json_error(['message'=>'Missing id'],400);

  $r = $wpdb->get_row( $wpdb->prepare("SELECT id,slug FROM $t WHERE id=%d",$id), ARRAY_A );
  if (!$r) wp_send_json_error(['message'=>'Bot not found'],404);

  $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $t");
  if ($total<=1) wp_send_json_error(['message'=>'Cannot delete the only bot'],400);
  if ($r['slug']==='default') wp_send_json_error(['message'=>'Cannot delete default bot'],400);

  $ok = $wpdb->delete($t, ['id'=>$id], ['%d']);
  if (!$ok) { aichat_bots_log('DELETE error',['db_error'=>$wpdb->last_error]); wp_send_json_error(['message'=>'DB delete error'],500); }

  wp_send_json_success(['deleted'=>true,'id'=>$id]);
}
