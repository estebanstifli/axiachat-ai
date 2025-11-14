<?php
if(!defined('ABSPATH')) exit;

/**
 * Pricing table (USD) per 1K tokens. Can be filtered.
 * cost_micros stored as integer micro-USD.
 */
function aichat_model_pricing(){
  /**
   * Precios oficiales convertidos a USD por 1K tokens (input/output) – última revisión: 2025-11-14.
   * Fuente: páginas de pricing públicas OpenAI / Anthropic / Gemini API. Sólo tarifas "standard" (sin cached input, batch, fine‑tune o priority tiers).
   * Enlaces oficiales:
   *   - OpenAI: https://openai.com/api/pricing/
   *   - Anthropic: https://www.anthropic.com/pricing
   *   - Gemini API: https://ai.google.dev/pricing
   * Si cambian las tarifas puedes sobreescribir vía filtro 'aichat_model_pricing'.
   * Nota: Algunos modelos tienen distintas variantes (mini, nano, etc). Mantenemos alias básicos para matching por prefijo.
   */
  $pricing = [
    'openai' => [
      // GPT‑5 family
      'gpt-5'        => ['input_per_1k'=>0.00125, 'output_per_1k'=>0.01000], // $1.25 / $10 per 1M
      'gpt-5-mini'   => ['input_per_1k'=>0.00025, 'output_per_1k'=>0.00200], // $0.25 / $2.00 per 1M
      'gpt-5-nano'   => ['input_per_1k'=>0.00005, 'output_per_1k'=>0.00040], // $0.05 / $0.40 per 1M

      // GPT‑4.1 family
      'gpt-4.1'       => ['input_per_1k'=>0.00200, 'output_per_1k'=>0.00800], // $2 / $8 per 1M
      'gpt-4.1-mini'  => ['input_per_1k'=>0.00040, 'output_per_1k'=>0.00160], // $0.40 / $1.60 per 1M
      'gpt-4.1-nano'  => ['input_per_1k'=>0.00010, 'output_per_1k'=>0.00040], // $0.10 / $0.40 per 1M

      // GPT‑4o family (tarifas estándar actualizadas)
      'gpt-4o'        => ['input_per_1k'=>0.00250, 'output_per_1k'=>0.01000], // $2.50 / $10 per 1M
      'gpt-4o-mini'   => ['input_per_1k'=>0.00015, 'output_per_1k'=>0.00060], // $0.15 / $0.60 per 1M

      // GPT‑4 Turbo (legacy models)
      'gpt-4-turbo'   => ['input_per_1k'=>0.01000, 'output_per_1k'=>0.03000], // $10 / $30 per 1M

      // GPT‑3.5 Turbo (legacy models)
      'gpt-3.5-turbo' => ['input_per_1k'=>0.00050, 'output_per_1k'=>0.00150], // $0.50 / $1.50 per 1M

      // Embeddings
      'text-embedding-3-small' => ['input_per_1k'=>0.00002, 'output_per_1k'=>0.00002], // $0.02 / $0.02 per 1M
      'text-embedding-3-large' => ['input_per_1k'=>0.00013, 'output_per_1k'=>0.00013], // $0.13 / $0.13 per 1M
    ],
    'claude' => [
      // Claude 3.x / 3.5 (valores estándar conocidos):
      'claude-3.5-sonnet' => ['input_per_1k'=>0.00300,'output_per_1k'=>0.01500], // $3 / $15 per 1M
      'claude-3-opus'     => ['input_per_1k'=>0.01500,'output_per_1k'=>0.07500], // $15 / $75 per 1M
      'claude-3-haiku'    => ['input_per_1k'=>0.00025,'output_per_1k'=>0.00125], // $0.25 / $1.25 per 1M
    ],
    'gemini' => [
      // Gemini 2.5 family (Nov 2025) - tarifas paid tier
      'gemini-2.5-pro'        => ['input_per_1k'=>0.00125, 'output_per_1k'=>0.01000], // $1.25 / $10 per 1M (≤200k) - Advanced reasoning
      'gemini-2.5-flash'      => ['input_per_1k'=>0.00030, 'output_per_1k'=>0.00250], // $0.30 / $2.50 per 1M (text/image/video) - Hybrid reasoning, 1M context
      'gemini-2.5-flash-lite' => ['input_per_1k'=>0.00010, 'output_per_1k'=>0.00040], // $0.10 / $0.40 per 1M (text/image/video) - Cost-efficient, high throughput
      
      // Gemini 2.0 family (2024)
      'gemini-2.0-flash'      => ['input_per_1k'=>0.00010, 'output_per_1k'=>0.00040], // $0.10 / $0.40 per 1M (text/image/video) - Agents era, 1M context
      'gemini-2.0-flash-lite' => ['input_per_1k'=>0.00008, 'output_per_1k'=>0.00030], // $0.075 / $0.30 per 1M - Most efficient
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
