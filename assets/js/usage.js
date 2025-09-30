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
  function loadTimeseries(){
    $.post(AIChatUsageAjax.ajax_url,{action:'aichat_get_usage_timeseries',nonce:AIChatUsageAjax.nonce}, function(r){
      if(!r || !r.success) return;
      var rows = r.data.series||[];
      var labels = rows.map(x=>x.d);
      var prompt = rows.map(x=>parseInt(x.p||0,10));
      var completion = rows.map(x=>parseInt(x.c||0,10));
      var cost = rows.map(x=> (x.m? (x.m/1000000).toFixed(4):0) );
      var ctx = document.getElementById('aichat-usage-chart'); if(!ctx) return;
      if(chartRef){ chartRef.destroy(); }
      chartRef = new Chart(ctx, {
        type:'bar',
        data:{ labels:labels, datasets:[
          {label:'Prompt Tokens', data:prompt, backgroundColor:'#4e79a7'},
          {label:'Completion Tokens', data:completion, backgroundColor:'#f28e2b'}
        ]},
        options:{
          responsive:true,
          plugins:{
            tooltip:{callbacks:{
              afterBody:function(items){
                var i = items[0].dataIndex; return 'Cost: $'+cost[i];
              }
            }}
          },
          scales:{ y:{ beginAtZero:true } }
        }
      });
    });
  }
  loadSummary();
  loadTimeseries();
});
