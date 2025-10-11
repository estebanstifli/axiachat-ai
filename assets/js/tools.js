(function($){
  'use strict';

  const WHEN_OPTIONS = [
    {value:'user_wants', label: (window.aichat_tools_i18n||{}).cond_user_wants || 'User wants'},
    {value:'user_talks_about', label: (window.aichat_tools_i18n||{}).cond_user_talks_about || 'User talks about'},
    {value:'user_asks_about', label: (window.aichat_tools_i18n||{}).cond_user_asks_about || 'User asks about'},
    {value:'user_sentiment', label: (window.aichat_tools_i18n||{}).cond_user_sentiment || 'User sentiment is'},
    {value:'phrase_contains', label: (window.aichat_tools_i18n||{}).cond_phrase_contains || 'Phrase contains'},
    {value:'date_is', label: (window.aichat_tools_i18n||{}).cond_date_is || 'Date is'},
    {value:'is_holiday', label: (window.aichat_tools_i18n||{}).cond_is_holiday || 'Is holiday'},
    {value:'url_contains', label: (window.aichat_tools_i18n||{}).cond_url_contains || 'Page URL contains'},
    {value:'custom', label: (window.aichat_tools_i18n||{}).cond_custom || 'Other (custom)'}
  ];

  const ACTION_OPTIONS = [
    {value:'navigate', label:(window.aichat_tools_i18n||{}).act_navigate || 'Navigate to'},
    {value:'say_exact', label:(window.aichat_tools_i18n||{}).act_say_exact || 'Say exact message'},
    {value:'always_include', label:(window.aichat_tools_i18n||{}).act_always_include || 'Always include'},
    {value:'always_talk_about', label:(window.aichat_tools_i18n||{}).act_always_talk_about || 'Always talk about'},
    {value:'request_info', label:(window.aichat_tools_i18n||{}).act_request_info || 'Request information'},
    {value:'send_email', label:(window.aichat_tools_i18n||{}).act_send_email || 'Send email'},
    {value:'api_request', label:(window.aichat_tools_i18n||{}).act_api_request || 'Send API request'},
    {value:'site_search', label:(window.aichat_tools_i18n||{}).act_site_search || 'Site search'},
    {value:'list_articles', label:(window.aichat_tools_i18n||{}).act_list_articles || 'List articles'},
    {value:'book_appointment', label:(window.aichat_tools_i18n||{}).act_book_appointment || 'Book an appointment'},
    {value:'knowledge_base', label:(window.aichat_tools_i18n||{}).act_knowledge_base || 'Answer from knowledge base'},
    {value:'enable_screen_share', label:(window.aichat_tools_i18n||{}).act_enable_screen_share || 'Enable screen share'},
    {value:'push_notification', label:(window.aichat_tools_i18n||{}).act_push_notification || 'Send push notification'}
  ];

  const state = {
    rules: [],
    dirty: false,
    capabilities: {
      available: [], // array of {id,label,description,type:'macro'|'tool'}
      selected: [],
      loading: false,
      dirty: false,
      settings: {} // map capId -> { system_policy: string, ... }
    }
  };

  function render(){
    const $wrap = $('#aichat-tools-builder');
    if(!$wrap.length) return;
    $wrap.empty();

    if(state.rules.length === 0){
      const txtNo = (window.aichat_tools_i18n||{}).no_rules || 'No rules defined yet. Use the + button to create the first one.';
      $('<p class="description"/>').text(txtNo).appendTo($wrap);
    }

    state.rules.forEach((rule, idx)=>{
      const $card = $('<div class="aichat-rule card mb-4 border-0 shadow-sm"/>');
      const $body = $('<div class="card-body p-3"/>').appendTo($card);

      // WHEN block
      const $whenBlock = $('<div class="aichat-block when-block mb-3"/>').appendTo($body);
  $('<div class="aichat-block-title"/>').text((window.aichat_tools_i18n||{}).when_label || 'WHEN').appendTo($whenBlock);
      const $conditionsContainer = $('<div class="conditions-container"/>').appendTo($whenBlock);

      rule.when.forEach((cond, cIdx)=>{
        $conditionsContainer.append(renderCondition(idx,cIdx,cond));
      });

      const $addCondBtn = $('<button type="button" class="button button-small aichat-add-condition"><span class="dashicons dashicons-plus"></span></button>');
      $addCondBtn.on('click',()=>{ addCondition(idx); });
      $whenBlock.append($addCondBtn);

      // ACTION block
      const $actionBlock = $('<div class="aichat-block action-block mb-3"/>').appendTo($body);
  $('<div class="aichat-block-title"/>').text((window.aichat_tools_i18n||{}).do_label || 'DO').appendTo($actionBlock);
      const $actionsContainer = $('<div class="actions-container"/>').appendTo($actionBlock);
      rule.actions.forEach((act,aIdx)=>{
        $actionsContainer.append(renderAction(idx,aIdx,act));
      });
      const $addActBtn = $('<button type="button" class="button button-small aichat-add-action"><span class="dashicons dashicons-plus"></span></button>');
      $addActBtn.on('click',()=>{ addAction(idx); });
      $actionBlock.append($addActBtn);

      // Footer (delete rule)
      const $footer = $('<div class="text-end pt-2"/>');
  const $del = $('<button type="button" class="button button-secondary"/>').text((window.aichat_tools_i18n||{}).delete_rule || 'Delete rule').on('click',()=>{ deleteRule(idx); });
      $footer.append($del);
      $body.append($footer);

      $wrap.append($card);
    });
  }

  function renderCondition(ruleIdx, condIdx, cond){
    const c = cond || { type:'user_wants', value:'' };
    const $row = $('<div class="row g-2 align-items-center mb-2 condition-row"/>');
    const $col1 = $('<div class="col-md-5"/>').appendTo($row);
    const $sel = $('<select class="form-select form-select-sm" />').appendTo($col1);
    WHEN_OPTIONS.forEach(o=>{ $('<option/>').val(o.value).text(o.label).appendTo($sel); });
    $sel.val(c.type);
    $sel.on('change',()=>{ state.rules[ruleIdx].when[condIdx].type = $sel.val(); markDirty(); });

    const $col2 = $('<div class="col-md-6"/>').appendTo($row);
  const $input = $('<input type="text" class="form-control form-control-sm"/>').attr('placeholder',(window.aichat_tools_i18n||{}).placeholder_value || 'value').val(c.value).appendTo($col2);
    $input.on('input',()=>{ state.rules[ruleIdx].when[condIdx].value = $input.val(); markDirty(); });

    const $col3 = $('<div class="col-md-1 text-end"/>').appendTo($row);
    $('<button type="button" class="button button-small" title="Eliminar"><span class="dashicons dashicons-trash"></span></button>')
      .on('click',()=>{ removeCondition(ruleIdx, condIdx); })
      .appendTo($col3);

    return $row;
  }

  function renderAction(ruleIdx, actIdx, act){
    const a = act || { type:'say_exact', params:{ text:'' } };
    const $row = $('<div class="row g-2 align-items-center mb-2 action-row"/>');
    const $col1 = $('<div class="col-md-5"/>').appendTo($row);
    const $sel = $('<select class="form-select form-select-sm" />').appendTo($col1);
    ACTION_OPTIONS.forEach(o=>{ $('<option/>').val(o.value).text(o.label).appendTo($sel); });
    $sel.val(a.type);
    $sel.on('change',()=>{ state.rules[ruleIdx].actions[actIdx].type = $sel.val(); ensureActionParams(ruleIdx,actIdx); markDirty(); render(); });

    const $col2 = $('<div class="col-md-6 action-extra"/>').appendTo($row);
    renderActionExtra($col2, ruleIdx, actIdx);

    const $col3 = $('<div class="col-md-1 text-end"/>').appendTo($row);
    $('<button type="button" class="button button-small" title="Eliminar"><span class="dashicons dashicons-trash"></span></button>')
      .on('click',()=>{ removeAction(ruleIdx, actIdx); })
      .appendTo($col3);

    return $row;
  }

  function renderActionExtra($container, ruleIdx, actIdx){
    const act = state.rules[ruleIdx].actions[actIdx];
    $container.empty();
    switch(act.type){
      case 'say_exact':
        $('<input type="text" class="form-control form-control-sm"/>')
          .attr('placeholder',(window.aichat_tools_i18n||{}).placeholder_text || 'Text to say')
          .val(act.params.text||'')
          .on('input', function(){ act.params.text = $(this).val(); markDirty(); })
          .appendTo($container);
        break;
      case 'navigate':
        $('<input type="url" class="form-control form-control-sm"/>')
          .attr('placeholder',(window.aichat_tools_i18n||{}).placeholder_url || 'https://...')
          .val(act.params.url||'')
          .on('input', function(){ act.params.url = $(this).val(); markDirty(); })
          .appendTo($container);
        break;
      case 'send_email':
        const $email = $('<input type="email" class="form-control form-control-sm mb-1"/>')
          .attr('placeholder',(window.aichat_tools_i18n||{}).placeholder_email || 'recipient@domain.com')
          .val(act.params.to||'')
          .on('input', function(){ act.params.to = $(this).val(); markDirty(); })
          .appendTo($container);
        const $msg = $('<textarea class="form-control form-control-sm" rows="2"/>')
          .attr('placeholder',(window.aichat_tools_i18n||{}).placeholder_message || 'Message')
          .val(act.params.body||'')
          .on('input', function(){ act.params.body = $(this).val(); markDirty(); })
          .appendTo($container);
        break;
      case 'request_info':
        // Chips style: comma separated tokens => array
        $('<input type="text" class="form-control form-control-sm"/>')
          .attr('placeholder',(window.aichat_tools_i18n||{}).placeholder_fields || 'Fields to request (e.g. phone,name)')
          .val((act.params.fields||[]).join(','))
          .on('input', function(){ act.params.fields = $(this).val().split(',').map(s=>s.trim()).filter(Boolean); markDirty(); })
          .appendTo($container);
        break;
      default:
        $('<input type="text" class="form-control form-control-sm"/>')
          .attr('placeholder',(window.aichat_tools_i18n||{}).placeholder_param || 'Parameter')
          .val(act.params.value||'')
          .on('input', function(){ act.params.value = $(this).val(); markDirty(); })
          .appendTo($container);
    }
  }

  function ensureActionParams(ruleIdx, actIdx){
    const act = state.rules[ruleIdx].actions[actIdx];
    if(!act.params) act.params = {};
    switch(act.type){
      case 'say_exact': if(!('text' in act.params)) act.params.text=''; break;
      case 'navigate': if(!('url' in act.params)) act.params.url=''; break;
      case 'send_email': if(!('to' in act.params)) act.params.to=''; if(!('body' in act.params)) act.params.body=''; break;
      case 'request_info': if(!('fields' in act.params)) act.params.fields=[]; break;
      default: if(!('value' in act.params)) act.params.value='';
    }
  }

  // Mutators
  function addRule(){ state.rules.push({ when:[{type:'user_wants',value:''}], actions:[{type:'say_exact', params:{text:''}}] }); markDirty(); render(); }
  function deleteRule(idx){ state.rules.splice(idx,1); markDirty(); render(); }
  function addCondition(ruleIdx){ state.rules[ruleIdx].when.push({type:'user_wants',value:''}); markDirty(); render(); }
  function removeCondition(ruleIdx, condIdx){ state.rules[ruleIdx].when.splice(condIdx,1); markDirty(); render(); }
  function addAction(ruleIdx){ state.rules[ruleIdx].actions.push({type:'say_exact',params:{text:''}}); markDirty(); render(); }
  function removeAction(ruleIdx, actIdx){ state.rules[ruleIdx].actions.splice(actIdx,1); markDirty(); render(); }

  function markDirty(){ state.dirty = true; $('#aichat-tools-save').prop('disabled', false); }

  function currentBot(){ return $('#aichat-tools-bot').val() || ''; }

  // ==== Capabilities (macros/tools) ====
  function renderCapabilities(){
    const $list = $('#aichat-capabilities-list');
    if(!$list.length) return;
    $list.empty();
    if(state.capabilities.loading){
      $('<div class="col-12"><em>Loading...</em></div>').appendTo($list);
      return;
    }
    if(state.capabilities.available.length===0){
      $('<div class="col-12"><em>'+(window.aichat_tools_i18n?.caps_none || 'No capabilities available. Register macros or tools.')+'</em></div>').appendTo($list);
      return;
    }
    state.capabilities.available.forEach(cap=>{
      const id = cap.id;
      const checked = state.capabilities.selected.includes(id);
      const $col = $('<div class="col-md-6 col-xl-4"/>' );
      const $card = $('<div class="aichat-cap border rounded p-2 h-100" style="background:#fff; border-color:#e2e8f0;"/>' ).appendTo($col);
      const icon = cap.type==='macro' ? 'bi-layers' : 'bi-gear';
      const $top = $('<div class="d-flex align-items-start gap-2" />').appendTo($card);
      const $label = $('<label class="d-flex align-items-start gap-2 flex-grow-1" style="cursor:pointer;" />').appendTo($top);
      const $cb = $('<input type="checkbox" class="mt-1"/>' ).val(id).prop('checked',checked).appendTo($label);
      const $icon = $('<i class="bi '+icon+' text-primary" style="font-size:16px;" aria-hidden="true"></i>').appendTo($label);
      const $textBox = $('<div class="flex-grow-1"/>' ).appendTo($label);
      $('<div class="fw-semibold"/>' ).text(cap.label).appendTo($textBox);
      if(cap.description){ $('<div class="text-muted small"/>' ).text(cap.description).appendTo($textBox); }
      // config button
      const $cfgBtn = $('<button type="button" class="button button-small" title="Config"><i class="bi bi-sliders"></i> '+(window.aichat_tools_i18n?.config || 'Config')+'</button>');
      $('<div class="ms-2"/>').append($cfgBtn).appendTo($top);
      $cfgBtn.on('click', function(){ toggleCapabilityConfig(id, $card); });
      $cb.on('change', function(){
        const val = $(this).val();
        if($(this).is(':checked')){ if(!state.capabilities.selected.includes(val)) state.capabilities.selected.push(val); }
        else { state.capabilities.selected = state.capabilities.selected.filter(v=>v!==val); }
        state.capabilities.dirty = true;
        $('#aichat-capabilities-save').prop('disabled', false);
      });
      // collapsed config area
      const $cfgWrap = $('<div class="cap-config mt-2" style="display:none;" data-cap="'+id+'" />').appendTo($card);
      renderCapabilityConfig($cfgWrap, id);
      $list.append($col);
    });
  }

  function loadCapabilities(){
    state.capabilities.loading = true; renderCapabilities();
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_get_bot_tools', nonce:aichat_tools_ajax.nonce, bot: currentBot() },
      dataType:'json'
    }).done(res=>{
      state.capabilities.loading = false;
      if(res.success){
        const selected = res.data.selected || [];
        const list = [];
        if(res.data.macros && Object.keys(res.data.macros).length){
          Object.values(res.data.macros).forEach(m=>{
            list.push({ id:m.name, label:m.label||m.name, description:m.description||'', type:'macro' });
          });
        } else if (res.data.tools){
          Object.entries(res.data.tools).forEach(([id,def])=>{
            list.push({ id, label:(def.name||id), description:def.description||'', type:'tool' });
          });
        }
        state.capabilities.available = list;
        state.capabilities.selected = selected;
      } else {
        state.capabilities.available = [];
        state.capabilities.selected = [];
      }
      // Load per-capability settings then render UI
      loadCapabilitySettings().always(function(){
        renderCapabilities();
        $('#aichat-capabilities-save').prop('disabled', true);
        $('#aichat-capabilities-status').text('');
      });
    }).fail(()=>{
      state.capabilities.loading=false; state.capabilities.available=[]; renderCapabilities();
    });
  }

  function loadCapabilitySettings(){
    const dfd = $.Deferred();
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_get_capability_settings', nonce:aichat_tools_ajax.nonce, bot: currentBot() },
      dataType:'json'
    }).done(function(res){
      if(res.success){ state.capabilities.settings = res.data.settings || {}; }
      else { state.capabilities.settings = {}; }
      dfd.resolve();
    }).fail(function(){ state.capabilities.settings = {}; dfd.resolve(); });
    return dfd.promise();
  }

  function toggleCapabilityConfig(capId, $card){
    const $wrap = $card.find('.cap-config[data-cap="'+capId+'"]');
    if(!$wrap.length) return;
    if($wrap.is(':visible')){ $wrap.slideUp(120); }
    else { $wrap.slideDown(120); }
  }

  function renderCapabilityConfig($wrap, capId){
    $wrap.empty();
    const cfg = state.capabilities.settings[capId] || {};
    const $row = $('<div class="mb-2" />').appendTo($wrap);
    $('<label class="form-label fw-semibold"/>').text((window.aichat_tools_i18n?.system_policy || 'System Policy')).appendTo($row);
    const $ta = $('<textarea class="form-control" rows="3"/>').val(cfg.system_policy || '').appendTo($row);
    // Optional domains allowlist for web search
    if (capId === 'openai_web_search'){
      const $domRow = $('<div class="mb-2" />').appendTo($wrap);
      $('<label class="form-label fw-semibold"/>').text(window.aichat_tools_i18n?.domains || 'Allowed domains').appendTo($domRow);
      const $dom = $('<input type="text" class="form-control" placeholder="example.com, another.com"/>').val((cfg.domains||[]).join(', ')).appendTo($domRow);
      $wrap.data('domainsInput', $dom);
    }
    const $btn = $('<button type="button" class="button button-secondary mt-1"/>').text(window.aichat_tools_i18n?.save_policy || 'Save Policy').appendTo($wrap);
    const $status = $('<span class="ms-2 small text-muted"/>').appendTo($wrap);
    $btn.on('click', function(){
      const payload = { system_policy: $ta.val() };
      const $dom = $wrap.data('domainsInput');
      if ($dom) {
        payload.domains = $dom.val().split(',').map(s=>s.trim()).filter(Boolean);
      }
      saveCapabilitySettings(capId, payload, $status);
    });
  }

  function saveCapabilitySettings(capId, settings, $status){
    $status.text(window.aichat_tools_i18n?.saving || 'Saving...');
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_save_capability_settings', nonce:aichat_tools_ajax.nonce, bot: currentBot(), cap: capId, settings: JSON.stringify(settings) },
      dataType:'json'
    }).done(function(res){
      if(res.success){
        state.capabilities.settings[capId] = res.data.settings || settings;
        $status.text(window.aichat_tools_i18n?.saved || 'Saved');
        setTimeout(function(){ $status.text(''); }, 1200);
      } else {
        $status.text(window.aichat_tools_i18n?.error || 'Error');
      }
    }).fail(function(){ $status.text(window.aichat_tools_i18n?.error || 'Error'); });
  }

  function saveCapabilities(){
    $('#aichat-capabilities-save').prop('disabled', true).text(window.aichat_tools_i18n?.caps_saving || 'Saving capabilities...');
    $('#aichat-capabilities-status').text('');
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_save_bot_tools', nonce:aichat_tools_ajax.nonce, bot: currentBot(), selected: JSON.stringify(state.capabilities.selected) },
      dataType:'json'
    }).done(res=>{
      if(res.success){
        state.capabilities.dirty=false;
        $('#aichat-capabilities-save').text(window.aichat_tools_i18n?.caps_saved || 'Capabilities saved');
        setTimeout(()=>{ $('#aichat-capabilities-save').text(window.aichat_tools_i18n?.caps_save || 'Save Capabilities'); }, 1400);
      } else {
        $('#aichat-capabilities-save').text(window.aichat_tools_i18n?.caps_error || 'Error saving capabilities');
      }
    }).fail(()=>{
      $('#aichat-capabilities-save').text(window.aichat_tools_i18n?.caps_error || 'Error saving capabilities');
    }).always(()=>{
      if(state.capabilities.dirty) $('#aichat-capabilities-save').prop('disabled', false);
    });
  }

  function loadRules(){
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_get_rules', nonce: aichat_tools_ajax.nonce, bot: currentBot() },
      dataType:'json'
    }).done(res=>{
      if(res.success){ state.rules = res.data.rules || []; } else { state.rules=[]; }
      render();
    }).fail(()=>{ console.warn('Failed to load rules'); });
  }

  function saveRules(){
  $('#aichat-tools-save').prop('disabled', true).text((window.aichat_tools_i18n||{}).saving || 'Saving...');
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_save_rules', nonce:aichat_tools_ajax.nonce, bot: currentBot(), rules: JSON.stringify(state.rules) },
      dataType:'json'
    }).done(res=>{
      if(res.success){ state.dirty=false; $('#aichat-tools-save').text((window.aichat_tools_i18n||{}).saved || 'Saved').delay(1200).queue(function(n){ $(this).text((window.aichat_tools_i18n||{}).save || 'Save'); n(); }); }
      else { $('#aichat-tools-save').text((window.aichat_tools_i18n||{}).error || 'Error'); }
    }).fail(()=>{ $('#aichat-tools-save').text((window.aichat_tools_i18n||{}).error || 'Error'); })
      .always(()=>{ if(state.dirty) $('#aichat-tools-save').prop('disabled', false); });
  }

  $(document).ready(function(){
    if($('#aichat-tools-builder').length){
      loadRules();
      loadCapabilities();
      $('#aichat-tools-add-rule').on('click', addRule);
      $('#aichat-tools-save').on('click', saveRules);
      $('#aichat-tools-bot').on('change', function(){ state.rules=[]; render(); loadRules(); loadCapabilities(); });
      $('#aichat-capabilities-save').on('click', saveCapabilities);
    }
  });

})(jQuery);
