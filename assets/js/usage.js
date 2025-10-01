jQuery(function($){
  function microsToUSD(m){ if(!m) return '$0.000'; return '$'+(m/1000000).toFixed(3); }
  function loadSummary(){
    $.post(AIChatUsageAjax.ajax_url,{action:'aichat_get_usage_summary',nonce:AIChatUsageAjax.nonce}, function(r){
      if(!r || !r.success) return;
      var s=r.data;
      $('[data-kpi="today-cost"]').text(microsToUSD(s.today.cost));
      $('[data-kpi="today-tokens"]').text(s.today.tokens||0);
      $('[data-kpi="last7-cost"]').text(microsToUSD(s.last7.cost));
      $('[data-kpi="last7-tokens"]').text(s.last7.tokens||0);
      $('[data-kpi="last30-cost"]').text(microsToUSD(s.last30.cost));
      $('[data-kpi="last30-tokens"]').text(s.last30.tokens||0);
      var tb = $('#aichat-usage-topmodels tbody').empty();
      if(!s.top_models.length){ tb.append('<tr><td colspan="3">(none)</td></tr>'); }
      else { s.top_models.forEach(function(row){ tb.append('<tr><td>'+ (row.model||'') +'</td><td>'+ (row.provider||'') +'</td><td>'+ microsToUSD(row.cm) +'</td></tr>'); }); }
    });
  }
  var chartRef = null;
  function renderTimeseries(rows){
    var noDataEl = $('#aichat-usage-nodata');
    var ctx = document.getElementById('aichat-usage-chart'); if(!ctx) return;
    if(!rows.length){
      noDataEl.show();
      if(chartRef){ chartRef.destroy(); chartRef=null; }
      return;
    }
    noDataEl.hide();
  var labels = rows.map(x=>x.d);
  var total = rows.map(x=>parseInt(x.t|| ( (parseInt(x.p||0,10)+parseInt(x.c||0,10)) ),10));
  var cost = rows.map(x=> (x.m? (x.m/1000000).toFixed(4):0) );
    if(chartRef){ chartRef.destroy(); }
    chartRef = new Chart(ctx, {
      type:'bar',
      data:{ labels:labels, datasets:[
        {label:(AIChatUsageAjax.strings?AIChatUsageAjax.strings.totalTokens:'Total Tokens'), data:total, backgroundColor:'#4e79a7'}
      ]},
      options:{
        responsive:true,
        plugins:{
          tooltip:{callbacks:{
            title:function(items){ return items[0].label; },
            afterBody:function(items){ var i=items[0].dataIndex; return (AIChatUsageAjax.strings?AIChatUsageAjax.strings.costLabel:'Cost')+': $'+cost[i]; }
          }}
        },
        scales:{ y:{ beginAtZero:true } }
      }
    });
  }
  function loadTimeseries(){
    $.post(AIChatUsageAjax.ajax_url,{action:'aichat_get_usage_timeseries',nonce:AIChatUsageAjax.nonce}, function(r){
      if(!r || !r.success) return;
      renderTimeseries(r.data.series||[]);
    });
  }
  loadSummary();
  // Carga inteligente: si Chart ya está, carga; si no, espera evento; fallback timeout.
  function tryInitTimeseries(){
    if(typeof window.Chart !== 'undefined'){
      loadTimeseries();
      return true;
    }
    return false;
  }
  if(!tryInitTimeseries()){
    document.addEventListener('aichat_chart_ready', function(){ tryInitTimeseries(); });
    // Fallback por si el evento se disparó antes de registrar el listener o no llega
    setTimeout(tryInitTimeseries, 500);
    setTimeout(tryInitTimeseries, 1500);
  }
});
