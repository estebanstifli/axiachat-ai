/**
 * assets/js/bots.js
 * Chatbots Admin UI (tabs + full form + autosave)
 *
 * Requiere: window.aichat_bots_ajax = { ajax_url, nonce, embedding_options }
 */
(function($){   
  "use strict";
   console.log('[AIChat Bots] READY — panel?', $('#aichat-panel').length, 
              'tabs?', $('#aichat-tab-strip').length, 
              'ajax obj?', !!window.aichat_bots_ajax);
  if(window.aichat_bots_ajax){
    console.log('[AIChat Bots] templates localized?', !!window.aichat_bots_ajax.instruction_templates, 'count=', window.aichat_bots_ajax.instruction_templates ? Object.keys(window.aichat_bots_ajax.instruction_templates).length : 0);
  }

  // ---------------- State ----------------
  let bots = [];          // lista de bots desde el servidor
  let activeId = null;    // id activo (db)
  const saveTimers = {};  // debounce por bot

  // -------------- Utils / Logs ----------
  const DBG = true;
  const DEBOUNCE_MS = 250; // antes 500: preview más ágil

  // ---- Preview helpers ----
  let previewTimer = null;
  // const PREVIEW_FIELDS = [...]; // ya no filtramos por campos
  function refreshPreview(bot){
    const base = (window.aichat_bots_ajax && aichat_bots_ajax.preview_url)
      ? aichat_bots_ajax.preview_url
      : (location.origin + '/?aichat_preview=1&bot=');

    const slug = (bot && bot.slug) ? bot.slug : 'default';
    const url  = base + encodeURIComponent(slug) + '&t=' + Date.now(); // bust cache

    const $if = $('#aichat-preview');
    if (!$if.length) return;

    // opcional: spinner si tienes overlay en el HTML/CSS
    // $('#aichat-preview').addClass('is-loading');

    $if.off('load.aichat').on('load.aichat', function(){
      // $('#aichat-preview').removeClass('is-loading');
    }).attr('src', url);
  }

  function schedulePreview(botId){
    clearTimeout(previewTimer);
    previewTimer = setTimeout(()=>{
      const bot = findBot(botId);
      if (bot) refreshPreview(bot);
    }, DEBOUNCE_MS);
  }

  const log = (...a)=>{ if(DBG) console.log('[AIChat Bots]', ...a); };
  
  /* ================== MODELOS POR PROVIDER ================== */
  function providerModels(prov){
    if (prov === 'anthropic') {
      return [
        { val:'claude-3-5-sonnet-20240620', label:'Claude 3.5 Sonnet (Jun 2024)' },
        { val:'claude-3-opus-20240229',     label:'Claude 3 Opus' },
        { val:'claude-3-sonnet-20240229',   label:'Claude 3 Sonnet' },
        { val:'claude-3-haiku-20240307',    label:'Claude 3 Haiku' }
      ];
    }
    // OpenAI
    return [
      { val:'gpt-5',       label:'GPT-5' },
      { val:'gpt-5-mini',  label:'GPT-5 Mini' },
      { val:'gpt-5-nano',  label:'GPT-5 Nano' },
      { val:'gpt-4o',      label:'GPT-4o' },
      { val:'gpt-4o-mini', label:'GPT-4o Mini' },
      { val:'gpt-4-turbo', label:'GPT-4 Turbo' },
      { val:'gpt-3.5-turbo', label:'GPT-3.5 Turbo' }
    ];
  }

  /* ================== REBUILD MODELOS ================== */
  function rebuildModelSelect(botId){
    const b = findBot(botId);
    if(!b) return;
    // Panel actual
    const $panel = $('#aichat-panel');
    const $sel = $panel.find(`#model-${botId}`);
    if(!$sel.length) return;
    const list = providerModels(b.provider || 'openai');
    const prev = b.model;
    $sel.empty();
    list.forEach(m=>{
      if(m && m.val && m.label){
        $sel.append('<option value="'+m.val+'">'+m.label+'</option>');
      }
    });
    if(!list.some(m=>m.val===prev)){
      b.model = list[0].val;
      $sel.val(b.model);
    } else {
      $sel.val(prev);
    }
    updateModelTokenInfo(b.id);
  }

  /* ================== TOKEN INFO OPCIONAL ================== */
  const MODEL_TOKEN_INFO = {
    'gpt-5':        { ctx:256000, comp:32768, rec:32768 },
    'gpt-5-mini':   { ctx:128000, comp:16384, rec:12000 },
    'gpt-5-nano':   { ctx:64000,  comp:8192,  rec:6000 },
    'gpt-4o':        { ctx:128000, comp:16384, rec:16384 },
    'gpt-4o-mini':   { ctx:128000, comp:12288, rec:8000 },
    'gpt-4-turbo':   { ctx:128000, comp:4096,  rec:3500 },
    'gpt-3.5-turbo': { ctx:16384,  comp:4096,  rec:3000 },
    'claude-3-5-sonnet-20240620': { ctx:200000, comp:8192,  rec:6000 },
    'claude-3-opus-20240229':     { ctx:200000, comp:4096,  rec:3500 },
    'claude-3-sonnet-20240229':   { ctx:200000, comp:4096,  rec:3500 },
    'claude-3-haiku-20240307':    { ctx:200000, comp:4096,  rec:3000 }
  };

  function updateModelTokenInfo(botId){
    const b = findBot(botId);
    if(!b) return;
    const info = MODEL_TOKEN_INFO[b.model];
    const $panel = $('#aichat-panel');
    const $inp = $panel.find(`#mx-${botId}`);
    if(!$inp.length) return;
    // Usar un contenedor único por bot
    let $box = $panel.find(`#token-info-${botId}`);
    if(!info){
      if($box.length) $box.remove();
      return;
    }
    if(!$box.length){
      $box = $('<div class="aichat-model-token-info" id="token-info-'+botId+'"></div>')
        .insertAfter($inp);
    }
    $box.html(
      'Contextual: '+info.ctx.toLocaleString()+
      ' - Completion: '+info.comp.toLocaleString()+
      ' <span class="recommended">Recommended: '+info.rec.toLocaleString()+'</span>'
    );
  }

  // Inyectar estilos una vez
  if(!document.getElementById('aichat-model-token-info-style')){
    const st=document.createElement('style');
    st.id='aichat-model-token-info-style';
    st.textContent=`.aichat-model-token-info{font-size:11px;margin-top:4px;color:#555}
    .aichat-model-token-info .recommended{color:#1a73e8;font-weight:600}`;
    document.head.appendChild(st);
  }

  // Derivar plantillas desde PHP (si existen)
  function getInstructionTemplates(){
    const raw = (window.aichat_bots_ajax && window.aichat_bots_ajax.instruction_templates) || {};
    // Normalizar a array [{key,id,name,description,template}]
    return Object.keys(raw).map(k=>({
      key: k,
      id: k,
      name: raw[k].name || k,
      description: raw[k].description || '',
      template: raw[k].template || ''
    })).filter(t=>t.template);
  }

  /* ================== HOOK RENDER FILA ================== */
  const _afterRenderBotRow_orig = window.afterRenderBotRow || null;
  window.afterRenderBotRow = function(botId){
    if(_afterRenderBotRow_orig) _afterRenderBotRow_orig(botId);
    rebuildModelSelect(botId);
  };

  const defaults = ()=>({
    id: null,
    name: 'Default',
    slug: 'default',
    type: 'text',
    instructions: 'Respond like a website support agent—friendly and creative. Use the page the customer is currently browsing as context.',

    provider: 'openai',
    model: 'gpt-4o',
    temperature: 0.7,
    max_tokens: 2048,
    reasoning: 'off',     // off|fast|accurate
    verbosity: 'medium',  // low|medium|high

  context_mode: 'page', // embeddings|page|none (changed default to 'page')
    context_id: 0,

    input_max_length: 512,
    max_messages: 20,
    context_max_length: 4096,

    ui_color: '#1a73e8',
    ui_position: 'br',         // br|bl|tr|tl
    ui_avatar_enabled: 0,
    ui_avatar_key: null,
    ui_icon_url: '',
    ui_start_sentence: 'Hi! How can I help you?',
    /* nuevos por defecto en UI */
    ui_placeholder: 'Escribe tu pregunta...',
    ui_button_send: 'Enviar',
  // Restored window control flags
  ui_closable: 1,
  ui_minimizable: 1,
  ui_draggable: 1,
  ui_minimized_default: 0,
  ui_superminimized_default: 0,

    is_default: 0
  });

  const embeddingOptions = ()=>{
    const raw = (window.aichat_bots_ajax && Array.isArray(window.aichat_bots_ajax.embedding_options))
      ? window.aichat_bots_ajax.embedding_options : [{id:0,text:'— None —'}];
    return raw;
  };

  const shortcodeForBot = (bot)=> `[aichat id="${(bot.slug||'default')}"]`;

  function debouncePerBot(botId, fn){
    clearTimeout(saveTimers[botId]);
    saveTimers[botId] = setTimeout(fn, DEBOUNCE_MS);
  }

  function findBot(id){ return bots.find(b => String(b.id) === String(id)); }

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));
  }

  // -------------- AJAX ------------------
  function ajaxPost(action, data){
    data = data || {};
    data.action = action;
    data.nonce  = (window.aichat_bots_ajax && aichat_bots_ajax.nonce) ? aichat_bots_ajax.nonce : '';
    log('AJAX →', action, data);
    return $.post(aichat_bots_ajax.ajax_url, data);
  }

  function loadBots(){
    return ajaxPost('aichat_bots_list', {})
      .done(res=>{
        log('LIST ←', res);
        if (res && res.success && Array.isArray(res.data)) {
          bots = res.data.map(row => Object.assign(defaults(), row));
          if (bots.length) activeId = bots[0].id;
        } else {
          bots = [Object.assign(defaults(), {id:0})];
          activeId = 0;
        }
        renderAll();
      })
      .fail(err=>{
        console.error('LIST error', err);
        bots = [Object.assign(defaults(), {id:0})];
        activeId = 0;
        renderAll();
      });
  }

  function createBot(){
    return ajaxPost('aichat_bot_create', {})
      .done(res=>{
        log('CREATE ←', res);
        if (res && res.success && res.data) {
          const bot = Object.assign(defaults(), res.data);
          bots.push(bot);
          activeId = bot.id;
          renderAll();
        }
      });
  }

  function updateBot(botId, patch){
    const bot = findBot(botId);
    if (bot) Object.assign(bot, patch);

    debouncePerBot(botId, ()=>{
      const payload = { id: botId, patch: JSON.stringify(patch) };
      ajaxPost('aichat_bot_update', payload)
        .done(res=> log('UPDATE ←', res))
        .fail(err=> console.error('UPDATE error', err));
    });
  }

  function duplicateBot(botId){
    return ajaxPost('aichat_bot_duplicate', { id: botId })
      .done(res=>{
        log('DUP ←', res);
        if (res && res.success && res.data) {
          const copy = Object.assign(defaults(), res.data);
          bots.push(copy);
          activeId = copy.id;
          renderAll();
        }
      });
  }

  function resetBot(botId){
    return ajaxPost('aichat_bot_reset', { id: botId })
      .done(res=>{
        log('RESET ←', res);
        if (res && res.success && res.data) {
          const idx = bots.findIndex(b => String(b.id)===String(botId));
          if (idx>=0) {
            bots[idx] = Object.assign(defaults(), res.data);
            activeId = bots[idx].id;
            renderAll();
          }
        }
      });
  }

  function deleteBot(botId){
    return ajaxPost('aichat_bot_delete', { id: botId })
      .done(res=>{
        log('DEL ←', res);
        if (res && res.success) {
          bots = bots.filter(b => String(b.id)!==String(botId));
          if (!bots.length) {
            bots = [Object.assign(defaults(), {id:0, is_default:1})];
          }
          if (!findBot(activeId)) activeId = bots[0].id;
          renderAll();
        }
      });
  }

  // -------------- Render ----------------
  function renderAll(){
    renderTabs();
    highlightActiveTab();
    renderPanel(activeId);
    updateArrows();
  }

  function renderTabs(){
    const $rail = $('#aichat-tab-strip');
    $rail.empty();

    bots.forEach(b=>{
      const $btn = $('<button/>', {
        type:'button',
        class:'aichat-tab' + (String(b.id)===String(activeId) ? ' active' : ''),
        'data-id': b.id,
        'aria-label': `Bot ${b.name || 'Bot'}`
      }).append('<i class="bi bi-robot"></i>')
        .append($('<span class="aichat-tab-title"/>').text(b.name || 'Bot'));
      $rail.append($btn);
    });
  }

  function highlightActiveTab(){
    const $rail = $('#aichat-tab-strip');
    $rail.find('.aichat-tab').removeClass('active');
    $rail.find(`.aichat-tab[data-id="${activeId}"]`).addClass('active');
  }

  function radio(field, value, bot, label){
    const checked = (String(bot[field])===String(value)) ? 'checked' : '';
    return `
      <label class="me-3">
        <input type="radio" class="form-check-input aichat-field" name="${field}-${bot.id}"
               data-field="${field}" data-id="${bot.id}" value="${value}" ${checked}> ${label}
      </label>
    `;
  }

  function nicePos(code){
    const map = { br:'Bottom-right', bl:'Bottom-left', tr:'Top-right', tl:'Top-left' };
    return map[code] || code;
  }

  function renderPanel(botId){
    const bot = findBot(botId);
    const $panel = $('#aichat-panel');
    if (!$panel.length || !bot){ $panel.html(''); return; }

    const models = providerModels(bot.provider);
    const modelsHTML = models.map(m=> `<option value="${m.val}" ${bot.model===m.val?'selected':''}>${m.label}</option>`).join('');

    const embHTML = embeddingOptions()
      .map(o => `<option value="${String(o.id)}" ${String(bot.context_id)===String(o.id)?'selected':''}>${escapeHtml(o.text)}</option>`)
      .join('');

    // Avatares desde assets/images/avatar1.png ... avatar9.png
    const scriptEl = document.querySelector('script[src*="assets/js/bots.js"]');
    const pluginBase = scriptEl ? scriptEl.src.replace(/assets\/js\/bots\.js.*$/, '') : '';
    const imgBase = `${pluginBase}assets/images/`;

    const avatars = Array.from({ length: 9 }, (_, idx) => {
      const key = `avatar${idx + 1}`;
      const url = `${imgBase}${key}.png`;
      const isActive = (String(bot.ui_avatar_key) === key);
      const checked = isActive ? 'checked' : '';
      const activeCls = isActive ? ' active' : '';
      return `
        <label class="aichat-avatar me-2 mb-2${activeCls}" title="${key}">
          <input type="radio" class="aichat-field d-none"
                 data-field="ui_avatar_key" data-id="${bot.id}"
                 name="ui_avatar_key-${bot.id}" value="${key}" ${checked}>
          <img src="${url}" alt="${key}">
        </label>
      `;
    }).join('');

  const tplList = getInstructionTemplates();
  const tplItemsHTML = tplList.map(t=>`<div class=\"aichat-tpl-item\" data-bot=\"${bot.id}\" data-id=\"${t.id}\" title=\"${escapeHtml(t.description)}\">${escapeHtml(t.name)}</div>`).join('');

    const html = `
      <form class="aichat-bot-form" data-id="${bot.id}">
        <div class="accordion aichat-accordion" id="acc-${bot.id}">

          <!-- General -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="g-h-${bot.id}">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#g-b-${bot.id}">
                <i class="bi bi-gear me-2"></i> General
              </button>
            </h2>
            <div id="g-b-${bot.id}" class="accordion-collapse collapse show">
              <div class="accordion-body">
                <div class="aichat-inline">
                  <div class="form-floating">
                    <input type="text" class="form-control aichat-field" data-field="name" data-id="${bot.id}" id="name-${bot.id}" placeholder="Name" value="${escapeHtml(bot.name||'')}">
                    <label for="name-${bot.id}">Chatbot Name</label>
                  </div>
                  <div class="form-floating">
                    <input type="text" class="form-control aichat-field" data-field="slug" data-id="${bot.id}" id="slug-${bot.id}" placeholder="ID" value="${escapeHtml(bot.slug||'')}">
                    <label for="slug-${bot.id}">ID (slug)</label>
                  </div>
                  <div class="form-floating">
                    <select class="form-select aichat-field" data-field="type" data-id="${bot.id}" id="type-${bot.id}">
                      <option value="text" ${bot.type==='text'?'selected':''}>Text</option>
                      <option value="voice_text" ${bot.type==='voice_text'?'selected':''}>Voice & Text</option>
                    </select>
                    <label for="type-${bot.id}">Chatbot Type</label>
                  </div>
                </div>

                <div class="mt-3">
                  <div class="d-flex align-items-baseline gap-2 mb-2">
                    <label class="form-label fw-semibold mb-0" for="inst-${bot.id}">Instructions / System prompt</label>
                    <span class="form-text-muted">(You can edit freely or load a predefined template)</span>
                  </div>
                  <div class="aichat-tpl-simple" id="tpl-panel-${bot.id}">
                    <div class="aichat-tpl-box" data-bot="${bot.id}">
                      <button type="button" class="aichat-tpl-arrow up" data-bot="${bot.id}" aria-label="Scroll up" title="Scroll up">▲</button>
                      <div class="aichat-tpl-list" id="tpl-list-${bot.id}" role="listbox" aria-label="Instruction templates">${tplItemsHTML || '<div class=\"aichat-tpl-empty\">No templates</div>'}</div>
                      <button type="button" class="aichat-tpl-arrow down" data-bot="${bot.id}" aria-label="Scroll down" title="Scroll down">▼</button>
                    </div>
                    <div class="aichat-tpl-side">
                      <div class="aichat-tpl-desc" id="tpl-desc-${bot.id}" aria-live="polite"></div>
                      <button type="button" class="button button-secondary aichat-tpl-load mt-2" data-id="${bot.id}" disabled><i class="bi bi-download"></i> Load</button>
                    </div>
                  </div>
                  <textarea class="form-control aichat-field mt-3" data-field="instructions" data-id="${bot.id}" id="inst-${bot.id}" rows="6">${escapeHtml(bot.instructions||'')}</textarea>
                </div>

                <div class="mt-3 d-flex align-items-center gap-2">
                  <span class="aichat-shortcode">
                    <code id="sc-${bot.id}">${shortcodeForBot(bot)}</code>
                    <button type="button" class="copy-btn" data-copy="#sc-${bot.id}"><i class="bi bi-clipboard"></i></button>
                  </span>
                  <span class="form-text-muted">Use this shortcode in posts/pages.</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Model -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="m-h-${bot.id}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m-b-${bot.id}">
                <i class="bi bi-cpu me-2"></i> Model
              </button>
            </h2>
            <div id="m-b-${bot.id}" class="accordion-collapse collapse">
              <div class="accordion-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="prov-${bot.id}">Provider</label>
                    <select id="prov-${bot.id}" class="form-select aichat-field" data-field="provider" data-id="${bot.id}">
                      <option value="openai" ${bot.provider==='openai'?'selected':''}>OpenAI</option>
                      <option value="anthropic" ${bot.provider==='anthropic'?'selected':''}>Claude</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="model-${bot.id}">Model</label>
                    <select id="model-${bot.id}" class="form-select aichat-field" data-field="model" data-id="${bot.id}">
                      ${modelsHTML}
                    </select>
                  </div>
                </div>

                <div class="row g-3 mt-1">
                  <div class="col-md-6">
                    <label class="form-label" for="temp-${bot.id}">Temperature</label>
                    <input id="temp-${bot.id}" type="number" step="0.01" min="0" max="2"
                           class="form-control aichat-field" data-field="temperature" data-id="${bot.id}" value="${Number(bot.temperature||0.7)}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="mx-${bot.id}">Max Tokens</label>
                    <input id="mx-${bot.id}" type="number" min="1"
                           class="form-control aichat-field" data-field="max_tokens" data-id="${bot.id}" value="${parseInt(bot.max_tokens||2048,10)}">
                  </div>
                </div>

                <div class="row g-3 mt-2">
                  <div class="col-md-6">
                    <label class="form-label d-block">Reasoning</label>
                    ${radio('reasoning','off',bot,'Off')}
                    ${radio('reasoning','fast',bot,'Fast')}
                    ${radio('reasoning','accurate',bot,'Accurate')}
                  </div>
                  <div class="col-md-6">
                    <label class="form-label d-block">Verbosity</label>
                    ${radio('verbosity','low',bot,'Low')}
                    ${radio('verbosity','medium',bot,'Medium')}
                    ${radio('verbosity','high',bot,'High')}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Context -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="c-h-${bot.id}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-b-${bot.id}">
                <i class="bi bi-diagram-3 me-2"></i> Context
              </button>
            </h2>
            <div id="c-b-${bot.id}" class="accordion-collapse collapse">
              <div class="accordion-body">
                <fieldset class="mb-0">
                  <legend class="fw-semibold mb-2" style="font-size:14px;">Context Source</legend>
                  <div class="d-flex flex-column gap-2">
                    <div class="form-check">
                      <input class="form-check-input ctx-mode" type="radio" name="ctx-mode-${bot.id}" id="ctx-emb-${bot.id}" value="embeddings" data-id="${bot.id}" ${bot.context_mode==='embeddings'?'checked':''}>
                      <label class="form-check-label fw-semibold" for="ctx-emb-${bot.id}">Use Embeddings</label>
                      <div class="aichat-inline mt-2" id="emb-wrap-${bot.id}" style="${bot.context_mode==='embeddings'?'':'display:none;'}">
                        <div class="form-floating">
                          <select class="form-select aichat-field" data-field="context_id" data-id="${bot.id}" id="emb-sel-${bot.id}">
                            ${embHTML}
                          </select>
                          <label for="emb-sel-${bot.id}">Embeddings Context</label>
                        </div>
                      </div>
                      <div class="form-text-muted">Select a pre-indexed context to ground answers.</div>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input ctx-mode" type="radio" name="ctx-mode-${bot.id}" id="ctx-page-${bot.id}" value="page" data-id="${bot.id}" ${bot.context_mode==='page'?'checked':''}>
                      <label class="form-check-label fw-semibold" for="ctx-page-${bot.id}">Use the content of the current page/post</label>
                      <div class="form-text-muted">Automatically feed visible page content to the assistant.</div>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input ctx-mode" type="radio" name="ctx-mode-${bot.id}" id="ctx-none-${bot.id}" value="none" data-id="${bot.id}" ${bot.context_mode==='none'?'checked':''}>
                      <label class="form-check-label fw-semibold" for="ctx-none-${bot.id}">No extra context</label>
                    </div>
                  </div>
                </fieldset>
              </div>
            </div>
          </div>

          <!-- Thresholds -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="t-h-${bot.id}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#t-b-${bot.id}">
                <i class="bi bi-sliders me-2"></i> Thresholds
              </button>
            </h2>
            <div id="t-b-${bot.id}" class="accordion-collapse collapse">
              <div class="accordion-body">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label" for="inmax-${bot.id}">Input Max Length</label>
                    <input id="inmax-${bot.id}" type="number" class="form-control aichat-field" data-field="input_max_length" data-id="${bot.id}" value="${parseInt(bot.input_max_length||512,10)}">
                    <div class="form-text">Maximum characters per user input.</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="mxmsg-${bot.id}">Max Messages</label>
                    <input id="mxmsg-${bot.id}" type="number" class="form-control aichat-field" data-field="max_messages" data-id="${bot.id}" value="${parseInt(bot.max_messages||20,10)}">
                    <div class="form-text">Historical messages sent to the model.</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="ctxmax-${bot.id}">Context Max Length</label>
                    <input id="ctxmax-${bot.id}" type="number" class="form-control aichat-field" data-field="context_max_length" data-id="${bot.id}" value="${parseInt(bot.context_max_length||4096,10)}">
                    <div class="form-text">Truncate external context below this length.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Appearance -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="a-h-${bot.id}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#a-b-${bot.id}">
                <i class="bi bi-palette me-2"></i> Appearance
              </button>
            </h2>
            <div id="a-b-${bot.id}" class="accordion-collapse collapse">
              <div class="accordion-body">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label" for="color-${bot.id}">Color</label>
                    <input id="color-${bot.id}" type="color" class="form-control form-control-color aichat-field"
                           data-field="ui_color" data-id="${bot.id}" value="${bot.ui_color||'#1a73e8'}">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="pos-${bot.id}">Position</label>
                    <select id="pos-${bot.id}" class="form-select aichat-field" data-field="ui_position" data-id="${bot.id}">
                      ${['br','bl','tr','tl'].map(p => `<option value="${p}" ${bot.ui_position===p?'selected':''}>${nicePos(p)}</option>`).join('')}
                    </select>
                  </div>
                  <div class="col-md-4">
                    <div class="form-check mt-4">
                      <input class="form-check-input aichat-field" type="checkbox" id="avaon-${bot.id}"
                             data-field="ui_avatar_enabled" data-id="${bot.id}" ${bot.ui_avatar_enabled? 'checked':''}>
                      <label class="form-check-label" for="avaon-${bot.id}">Avatar enabled</label>
                    </div>
                  </div>
                </div>

                <!-- Window control flags -->
                <div class="row g-3 mt-1">
                  <div class="col-md-3">
                    <div class="form-check mt-4">
                      <input class="form-check-input aichat-field" type="checkbox" id="clos-${bot.id}" data-field="ui_closable" data-id="${bot.id}" ${bot.ui_closable? 'checked':''}>
                      <label class="form-check-label" for="clos-${bot.id}">Closable</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-check mt-4">
                      <input class="form-check-input aichat-field" type="checkbox" id="mini-${bot.id}" data-field="ui_minimizable" data-id="${bot.id}" ${bot.ui_minimizable? 'checked':''}>
                      <label class="form-check-label" for="mini-${bot.id}">Minimizable</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-check mt-4">
                      <input class="form-check-input aichat-field" type="checkbox" id="drag-${bot.id}" data-field="ui_draggable" data-id="${bot.id}" ${bot.ui_draggable? 'checked':''}>
                      <label class="form-check-label" for="drag-${bot.id}">Draggable</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-check mt-4">
                      <input class="form-check-input aichat-field" type="checkbox" id="mindef-${bot.id}" data-field="ui_minimized_default" data-id="${bot.id}" ${bot.ui_minimized_default? 'checked':''}>
                      <label class="form-check-label" for="mindef-${bot.id}">Minimized (default)</label>
                    </div>
                  </div>
                </div>
                <!-- NUEVA FILA: Super minimized -->
                <div class="row g-3 mt-1">
                  <div class="col-md-3">
                    <div class="form-check mt-2">
                      <input class="form-check-input aichat-field" type="checkbox" id="supmindef-${bot.id}" data-field="ui_superminimized_default" data-id="${bot.id}" ${bot.ui_superminimized_default? 'checked':''}>
                      <label class="form-check-label" for="supmindef-${bot.id}">Super minimized (avatar)</label>
                    </div>
                  </div>
                  <div class="col-md-9">
                    <div class="form-text-muted" style="margin-top:14px;">If enabled, the widget starts as an avatar bubble. Opening it will show the full chat window.</div>
                  </div>
                </div>

                <!-- NUEVOS CAMPOS -->
                <div class="row g-3 mt-1">
                  <div class="col-md-6">
                    <label class="form-label" for="ph-${bot.id}">Placeholder</label>
                    <input id="ph-${bot.id}" type="text" class="form-control aichat-field"
                           data-field="ui_placeholder" data-id="${bot.id}" value="${escapeHtml(bot.ui_placeholder||'')}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="sendlbl-${bot.id}">Send button label</label>
                    <input id="sendlbl-${bot.id}" type="text" class="form-control aichat-field"
                           data-field="ui_button_send" data-id="${bot.id}" value="${escapeHtml(bot.ui_button_send||'')}">
                  </div>
                </div>

                <div class="mt-3" id="avatar-wrap-${bot.id}" style="${bot.ui_avatar_enabled?'':'display:none;'}">
                  <div class="mb-2 fw-semibold">Pick an avatar</div>
                  <div class="d-flex flex-wrap">${avatars}</div>
                  <div class="mt-2">
                    <label class="form-label" for="icon-${bot.id}">Custom Icon URL</label>
                    <input id="icon-${bot.id}" type="url" class="form-control aichat-field" data-field="ui_icon_url" data-id="${bot.id}" value="${escapeHtml(bot.ui_icon_url||'')}">
                  </div>
                </div>

                <div class="mt-3">
                  <label class="form-label" for="start-${bot.id}">Start Sentence</label>
                  <input id="start-${bot.id}" type="text" class="form-control aichat-field"
                         data-field="ui_start_sentence" data-id="${bot.id}" value="${escapeHtml(bot.ui_start_sentence||'')}">
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="x-h-${bot.id}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#x-b-${bot.id}">
                <i class="bi bi-tools me-2"></i> Actions
              </button>
            </h2>
            <div id="x-b-${bot.id}" class="accordion-collapse collapse">
              <div class="accordion-body d-flex justify-content-between align-items-center">
                <div class="d-flex gap-2">
                  <button type="button" class="button button-secondary aichat-action" data-action="duplicate" data-id="${bot.id}">
                    <i class="bi bi-files"></i> Duplicate
                  </button>
                  <button type="button" class="button button-secondary aichat-action" data-action="reset" data-id="${bot.id}">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                  </button>
                </div>
                <div>
                  <button type="button" class="button button-danger aichat-action" data-action="delete" data-id="${bot.id}" ${(bots.length<=1 || bot.slug==='default')?'disabled':''}>
                    <i class="bi bi-trash"></i> Delete
                  </button>
                </div>
              </div>
            </div>
          </div>

        </div>
      </form>
    `;

    $panel.html(html);
    updateModelTokenInfo(bot.id);
    refreshPreview(bot);
  }

  // -------------- Events ----------------
  function bindEvents(){
    $(document)
      .off('click.aichat', '#aichat-add-bot')
      .off('click.aichat', '#aichat-tab-strip .aichat-tab')
      .off('click.aichat', '#aichat-tabs-prev')
      .off('click.aichat', '#aichat-tabs-next')
      .off('click.aichat', '.copy-btn')
      .off('input.aichat change.aichat', '#aichat-panel .aichat-field')
  .off('change.aichat', '#aichat-panel .ctx-excl')
      .off('change.aichat', '#aichat-panel .ctx-mode')
      .off('click.aichat', '.aichat-tpl-arrow')
      .off('scroll.aichat', '.aichat-tpl-list');

    // Añadir bot
    $(document).on('click.aichat', '#aichat-add-bot', function(e){
      e.preventDefault();
      createBot();
    });

    // Activar pestaña
    $(document).on('click.aichat', '#aichat-tab-strip .aichat-tab', function(){
      activeId = $(this).data('id');
      highlightActiveTab();
      renderPanel(activeId);
    });

    // Scroll pestañas
    $(document).on('click.aichat', '#aichat-tabs-prev', function(){
      const rail = document.getElementById('aichat-tab-strip');
      rail && rail.scrollBy({ left: -150, behavior: 'smooth' });
    });
    $(document).on('click.aichat', '#aichat-tabs-next', function(){
      const rail = document.getElementById('aichat-tab-strip');
      rail && rail.scrollBy({ left:  150, behavior: 'smooth' });
    });

    // Copiar shortcode
    $(document).on('click.aichat', '.copy-btn', function(){
      const sel = $(this).data('copy');
      const el  = document.querySelector(sel);
      if (!el) return;
      const txt = el.textContent.trim();
      navigator.clipboard?.writeText(txt);
    });

    // Cambios de campo (autosave)
    $(document).on('input.aichat change.aichat', '#aichat-panel .aichat-field', function(){
      const $el   = $(this);
      const id    = $el.data('id');
      const field = $el.data('field');
      let val     = $el.val();

      if ($el.is(':checkbox')) {
        val = $el.is(':checked') ? 1 : 0;
      } else if ($el.is(':radio')) {
        if (!$el.is(':checked')) return;
        val = $el.val();
      } else {
        val = $el.val();
      }

      if (['temperature','max_tokens','input_max_length','max_messages','context_max_length'].includes(field)) {
        const num = Number(val);
        val = isFinite(num) ? num : val;
      }

      if (field === 'ui_avatar_enabled') {
        $(`#avatar-wrap-${id}`).toggle(!!val);
      }

      if (field === 'slug') {
        const code = `[aichat id="${(val||'default')}"]`;
        $(`#sc-${id}`).text(code);
      }

      // Cambio de provider: reconstruir modelos y enviar patch combinado
      if (field === 'provider') {
        const bot = findBot(id);
        if (bot) {
          bot.provider = val;
          rebuildModelSelect(id);
          updateBot(id, { provider: bot.provider, model: bot.model });
          schedulePreview(id);
          return;
        }
      }
      if (field === 'model') {
        const bot = findBot(id);
        if (bot) {
          bot.model = val;
          updateModelTokenInfo(id);
        }
      }

      const patch = {}; patch[field] = val;
      updateBot(id, patch);

      // Marcar visualmente el avatar activo
      if (field === 'ui_avatar_key') {
        $(`#avatar-wrap-${id} .aichat-avatar`).removeClass('active');
        $el.closest('.aichat-avatar').addClass('active');
      }

      // Preview inmediato para cualquier campo EXCEPTO slug (para evitar 404 mientras guarda)
      if (field !== 'slug') {
        schedulePreview(id);
      }
    });

    // Tras guardar en AJAX (incluye cambios de slug), refresca con los datos devueltos por el servidor
    $(document).ajaxSuccess(function(evt, xhr, settings){
      try {
        if (!settings || !settings.data || settings.data.indexOf('action=aichat_bot_update') === -1) return;
        const res = JSON.parse(xhr.responseText);
        if (res && res.success && res.data && res.data.bot) {
          refreshPreview(res.data.bot); // usa el slug ya consolidado en BD
        }
      } catch(e){}
    });

    // Cambio de contexto (radios exclusivos)
    $(document).on('change.aichat', '#aichat-panel .ctx-mode', function(){
      if (!this.checked) return;
      const id  = $(this).data('id');
      const val = this.value; // embeddings|page|none
      $(`#emb-wrap-${id}`).toggle(val === 'embeddings');
      updateBot(id, { context_mode: val });
    });

    // Mantener visibilidad de flechas
    const rail = document.getElementById('aichat-tab-strip');
    if (rail) {
      rail.addEventListener('scroll', updateArrows, {passive:true});
      window.addEventListener('resize', updateArrows, {passive:true});
    }

    // Acciones
    $(document).on('click.aichat', '#aichat-panel .aichat-action', function(){
      const id = $(this).data('id');
      const act = $(this).data('action');
      if (!id || !act) return;

      if (act === 'duplicate') {
        duplicateBot(id);
      } else if (act === 'reset') {
        if (confirm('Reset this bot to default settings?')) resetBot(id);
      } else if (act === 'delete') {
        const bot = findBot(id);
        if (!bot) return;
        if (bots.length<=1 || bot.slug==='default') return;
        if (confirm('Delete this bot? This action cannot be undone.')) deleteBot(id);
      }
    });

    // Plantillas: seleccionar
    $(document).on('click.aichat', '#aichat-panel .aichat-tpl-item', function(){
      const $it=$(this); const botId=$it.data('bot');
      $(`#tpl-list-${botId} .aichat-tpl-item`).removeClass('active');
      $it.addClass('active');
      const tplId=$it.data('id');
      const list=getInstructionTemplates();
      const tpl=list.find(t=>t.id===tplId);
      const $desc=$(`#tpl-desc-${botId}`);
      const $btn=$(`#tpl-panel-${botId} .aichat-tpl-load[data-id="${botId}"]`);
      if(tpl){
        $desc.text(tpl.description);
        $btn.prop('disabled', false).data('tplId', tpl.id);
      } else {
        $desc.text('');
        $btn.prop('disabled', true).removeData('tplId');
      }
    });
    // Plantillas: cargar
    $(document).on('click.aichat', '#aichat-panel .aichat-tpl-load', function(){
      const botId=$(this).data('id');
      const tplId=$(this).data('tplId');
      if(!tplId) return; const list=getInstructionTemplates();
      const tpl=list.find(t=>t.id===tplId); if(!tpl) return;
      const $ta=$(`#inst-${botId}`); if(!$ta.length) return;
      const current=($ta.val()||'').trim();
      if(current && current!==tpl.template){
        if(!confirm('This will replace the current instructions. Continue?')) return;
      }
      $ta.val(tpl.template).trigger('input');
    });

    // Flechas de navegación en pestañas
    $(document).on('mouseenter.aichat', '#aichat-tab-strip', function(){
      updateArrows();
    });

    // Flechas plantillas
    $(document).on('click.aichat', '.aichat-tpl-arrow', function(){
      const botId = $(this).data('bot');
      if($(this).hasClass('up')) scrollTpl(botId,'up'); else scrollTpl(botId,'down');
    });
    // Scroll manual lista plantillas
    $(document).on('scroll.aichat', '.aichat-tpl-list', function(){
      const m = this.id.match(/tpl-list-(\d+)/); if(m) updateTplScroll(m[1]);
    });
  }

  function updateArrows(){
    const rail = document.getElementById('aichat-tab-strip');
    const $prev = $('#aichat-tabs-prev');
    const $next = $('#aichat-tabs-next');
    if (!rail) { $prev.hide(); $next.hide(); return; }
    const canL = rail.scrollLeft > 5;
    const canR = (rail.scrollWidth - rail.clientWidth - rail.scrollLeft) > 5;
    $prev.toggle(canL);
    $next.toggle(canR);
  }

  // ===== Template list scroll helpers (no inline styles) =====
  function updateTplScroll(botId){
    const list = document.getElementById(`tpl-list-${botId}`);
    if(!list) return;
    const up = document.querySelector(`.aichat-tpl-arrow.up[data-bot="${botId}"]`);
    const down = document.querySelector(`.aichat-tpl-arrow.down[data-bot="${botId}"]`);
    if(!up||!down) return;
    const maxScroll = list.scrollHeight - list.clientHeight;
    const pos = list.scrollTop;
    up.disabled = pos <= 0;
    down.disabled = pos >= (maxScroll - 2);
    if(maxScroll <= 0){ up.classList.add('aichat-hidden'); down.classList.add('aichat-hidden'); }
    else { up.classList.remove('aichat-hidden'); down.classList.remove('aichat-hidden'); }
  }

  function scrollTpl(botId, dir){
    const list = document.getElementById(`tpl-list-${botId}`);
    if(!list) return;
    const item = list.querySelector('.aichat-tpl-item');
    const step = item ? (item.getBoundingClientRect().height + 4) * 2 : 100;
    list.scrollBy({ top: (dir==='up'?-step:step), behavior:'smooth'});
    setTimeout(()=> updateTplScroll(botId), 240);
  }

  // ------------- Init -------------------
  $(function(){
    bindEvents();
    loadBots();
  });

})(jQuery);
