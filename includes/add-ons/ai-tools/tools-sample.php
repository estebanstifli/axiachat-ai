<?php
// Moved sample tools from includes/tools-sample.php
if ( ! defined('ABSPATH') ) { exit; }

if ( ! function_exists('aichat_register_tool_safe') ) {
    if ( function_exists('aichat_register_tool') ) {
        function aichat_register_tool_safe($id,$args){ return aichat_register_tool($id,$args); }
    } else { return; }
}

function aichat_sample_get_wp_timezone(){
    $tz_string = get_option('timezone_string');
    if ( ! $tz_string ) {
        $offset  = (float) get_option( 'gmt_offset', 0 );
        $hours   = (int) $offset; $minutes = ( $offset - $hours );
        $sign    = $offset < 0 ? '-' : '+';
        $tz_string = sprintf( 'UTC%s%02d:%02d', $sign, abs($hours), abs( $minutes * 60 ) );
    }
    try { return new DateTimeZone( $tz_string ); } catch ( Exception $e ) { return new DateTimeZone( 'UTC' ); }
}

aichat_register_tool_safe( 'util_get_datetime', [
  'type'=>'function','name'=>'util_get_datetime','description'=>'Get the current server date and time in ISO8601, human-readable and Unix timestamp forms. Optional timezone identifier.',
  'activity_label'=>'Fetching current date & time...',
  'schema'=>['type'=>'object','properties'=>[
    'timezone'=>['type'=>'string','description'=>'PHP timezone identifier, e.g., Europe/Madrid. Defaults to site timezone.'],
    'format'=>['type'=>'string','description'=>'Optional PHP date() format string for custom_format output.'],
  ],'required'=>[],'additionalProperties'=>false],
  'callback'=>function($args){ $tz=isset($args['timezone'])&&is_string($args['timezone'])?$args['timezone']:'';
    try{$dtz=$tz?new DateTimeZone($tz):aichat_sample_get_wp_timezone();}catch(Exception $e){$dtz=aichat_sample_get_wp_timezone();}
    $now=new DateTime('now',$dtz); $format=isset($args['format'])&&is_string($args['format'])?$args['format']:'';
    return ['iso8601'=>$now->format(DateTime::ATOM),'human'=>$now->format('l, d F Y H:i:s T'),'timestamp'=>$now->getTimestamp(),'timezone'=>$now->format('e'),'custom_format'=>$format?$now->format($format):null]; },
  'timeout'=>2,'parallel'=>true,'max_calls'=>2]);

aichat_register_tool_safe( 'util_mortgage_payment', [
  'type'=>'function','name'=>'util_mortgage_payment','description'=>'Calculate monthly payment and summary for a fixed-rate amortizing loan (principal, annual interest %, years).',
  'activity_label'=>'Calculating mortgage payment...',
  'schema'=>['type'=>'object','properties'=>[
    'principal'=>['type'=>'number','description'=>'Loan principal amount (>=0).'],
    'annual_interest_percent'=>['type'=>'number','description'=>'Annual nominal interest rate in percent (e.g., 5.5).'],
    'years'=>['type'=>'integer','description'=>'Loan term in whole years (e.g., 30).'],],
    'required'=>['principal','annual_interest_percent','years'],'additionalProperties'=>false],
  'callback'=>function($args){ $P=isset($args['principal'])?max(0,(float)$args['principal']):0; $annual_percent=isset($args['annual_interest_percent'])?(float)$args['annual_interest_percent']:0; $years=isset($args['years'])?max(1,(int)$args['years']):1; $n=$years*12; $r=($annual_percent/100)/12; if($P<=0){return ['error'=>'Principal must be > 0.'];}
    if($r<=0){$payment=$P/$n;} else {$payment=$P*( $r*pow(1+$r,$n) )/( pow(1+$r,$n)-1 );}
    $balance=$P; $amort_first=[]; for($i=1;$i<=min(3,$n);$i++){ $interest=$r>0?$balance*$r:0; $principal_paid=$payment-$interest; $balance-=$principal_paid; if($balance<0){$balance=0;} $amort_first[]=['month'=>$i,'interest'=>round($interest,2),'principal'=>round($principal_paid,2),'balance'=>round($balance,2)]; }
    $total_payment=$payment*$n; $total_interest=$total_payment-$P; return ['monthly_payment'=>round($payment,2),'total_interest'=>round($total_interest,2),'total_paid'=>round($total_payment,2),'term_months'=>$n,'first_months'=>$amort_first,'assumptions'=>'Fixed-rate, fully amortizing, no fees, monthly compounding.']; },
  'timeout'=>3,'parallel'=>true,'max_calls'=>1]);

aichat_register_tool_safe( 'util_list_categories', [
  'type'=>'function','name'=>'util_list_categories','description'=>'MUST be called whenever the user asks (in English or Spanish) about blog categories. NEVER guess: always invoke this tool to fetch real categories with names, slugs and counts.',
  'activity_label'=>'Fetching real blog categories...','schema'=>['type'=>'object','properties'=>[
    'with_counts'=>['type'=>'boolean','description'=>'If true include post counts (default true).'],
    'limit'=>['type'=>'integer','description'=>'Optional max number of categories to return (default all).']
  ],'required'=>[],'additionalProperties'=>false],
  'callback'=>function($args){ $with_counts=isset($args['with_counts'])?(bool)$args['with_counts']:true; $limit=isset($args['limit'])?max(1,(int)$args['limit']):0; $tax_args=['taxonomy'=>'category','hide_empty'=>false]; $terms=get_terms($tax_args); if(is_wp_error($terms)){return ['error'=>'taxonomy_error','message'=>$terms->get_error_message()];} $out=[]; foreach($terms as $t){ $item=['name'=>$t->name,'slug'=>$t->slug]; if($with_counts){$item['count']=(int)$t->count;} $out[]=$item; if($limit && count($out)>=$limit) break; } return ['categories'=>$out,'total'=>count($out)]; },
  'timeout'=>5,'parallel'=>true,'max_calls'=>1]);

if ( function_exists('aichat_ssa_disponibilidad') ) {
  aichat_register_tool_safe( 'aichat_ssa_disponibilidad', [
    'type'=>'function','name'=>'aichat_ssa_disponibilidad',
    'description'=>'Fetch REAL appointment availability (start datetime slots) for Simply Schedule Appointments. Call whenever the user asks about availability. NEVER guess times.','activity_label'=>'Checking real appointment availability...',
    'schema'=>['type'=>'object','properties'=>[
      'appointment_type_id'=>['type'=>'integer','description'=>'(Optional) Appointment type ID.'],
      'start'=>['type'=>'string','description'=>'Optional start (Y-m-d H:i:s). Defaults to now.'],
      'end'=>['type'=>'string','description'=>'Optional end (Y-m-d H:i:s).'],
      'limit'=>['type'=>'integer','description'=>'Optional max number of slots.'],
    ],'required'=>[],'additionalProperties'=>false],
    'callback'=>function($args){ if(!function_exists('aichat_ssa_disponibilidad')) return ['error'=>'ssa_not_loaded']; $appointment_type_id=isset($args['appointment_type_id'])?(int)$args['appointment_type_id']:null; $start=isset($args['start'])?trim($args['start']):null; $end=isset($args['end'])?trim($args['end']):null; $slots=call_user_func('aichat_ssa_disponibilidad',$appointment_type_id,$start,$end); if(is_string($slots)) return ['error'=>$slots]; if(!is_array($slots)) return ['error'=>'unexpected_return_type']; return ['slots'=>array_values($slots),'count'=>count($slots)]; },
    'timeout'=>5,'parallel'=>false,'max_calls'=>1]);
}

if ( function_exists('aichat_register_macro') ) {
  aichat_register_macro(['name'=>'basic_utilities_demo','label'=>'Basic Utilities (Demo)','description'=>'Provides current datetime and mortgage payment calculation.','tools'=>['util_get_datetime','util_mortgage_payment']]);
  aichat_register_macro(['name'=>'content_categories','label'=>'Content: Blog Categories','description'=>'Allows the assistant to list real WordPress blog categories (names, slugs, counts).','tools'=>['util_list_categories']]);
  if ( function_exists('aichat_ssa_disponibilidad') ) { aichat_register_macro(['name'=>'appointments_availability','label'=>'Appointments: Availability','description'=>'Provides real booking availability slots.','tools'=>['aichat_ssa_disponibilidad']]); }
}
