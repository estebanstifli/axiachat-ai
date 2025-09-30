<?php
if(!defined('ABSPATH')) exit;

/**
 * Pricing table (USD) per 1K tokens. Can be filtered.
 * cost_micros stored as integer micro-USD.
 */
function aichat_model_pricing(){
  $pricing = [
    'openai' => [
      'gpt-4o' => ['input_per_1k'=>0.005, 'output_per_1k'=>0.015],
      'gpt-4o-mini' => ['input_per_1k'=>0.00015,'output_per_1k'=>0.00060],
      'text-embedding-3-small' => ['input_per_1k'=>0.00002,'output_per_1k'=>0.00002],
    ],
    'claude' => [
      'claude-3-haiku' => ['input_per_1k'=>0.00025,'output_per_1k'=>0.00125],
    ],
  ];
  return apply_filters('aichat_model_pricing', $pricing);
}

function aichat_calc_cost_micros($provider,$model,$prompt_tokens,$completion_tokens){
  $pricing = aichat_model_pricing();
  $prov = strtolower((string)$provider);
  $m = strtolower((string)$model);
  if(!isset($pricing[$prov])) return null;
  // intentar match exacto; si no, fallback aproximado por prefijo
  $entry = null;
  if(isset($pricing[$prov][$m])){ $entry = $pricing[$prov][$m]; }
  else {
    foreach($pricing[$prov] as $k=>$v){ if(stripos($m,$k)===0){ $entry=$v; break; } }
  }
  if(!$entry) return null;
  $in = max(0,(int)$prompt_tokens); $out = max(0,(int)$completion_tokens);
  $cost = ($in/1000.0)*$entry['input_per_1k'] + ($out/1000.0)*$entry['output_per_1k'];
  return (int)round($cost * 1000000); // micro-USD
}

/** Update / upsert daily aggregate */
function aichat_update_daily_usage_row($provider,$model,$prompt,$completion,$total,$cost_micros){
  global $wpdb; $table = $wpdb->prefix.'aichat_usage_daily';
  $date = current_time('Y-m-d');
  $wpdb->query($wpdb->prepare(
    "INSERT INTO $table (date,provider,model,prompt_tokens,completion_tokens,total_tokens,cost_micros,conversations)
      VALUES (%s,%s,%s,%d,%d,%d,%d,1)
      ON DUPLICATE KEY UPDATE
        prompt_tokens = prompt_tokens + VALUES(prompt_tokens),
        completion_tokens = completion_tokens + VALUES(completion_tokens),
        total_tokens = total_tokens + VALUES(total_tokens),
        cost_micros = cost_micros + VALUES(cost_micros),
        conversations = conversations + 1",
    $date,$provider,$model,$prompt,$completion,$total,$cost_micros
  ));
}
