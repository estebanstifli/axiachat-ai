(function($){
  const rootSel = '#aichat-easy-config-root';
  function render(state){
    const $root = $(rootSel);
    if(!$root.length) return;
    let html = '';
    html += '<div class="aichat-easy-steps">';
    html += stepIndicators(state.step);
    html += '<div class="aichat-easy-panel">'+panelContent(state)+'</div>';
    html += '</div>';
    $root.html(html);
    bind(state);
  }
  function stepIndicators(cur){
  const steps = ['welcome','discover','apikey','index','finish'];
    return '<ol class="aichat-easy-progress">'+steps.map((s,i)=>{
      const idx=i+1; const cls = (i<cur)?'done':(i===cur?'active':'');
      return '<li class="'+cls+'"><span>'+(idx)+'</span></li>';}).join('')+'</ol>';
  }
  function panelContent(state){
    switch(state.step){
      case 0: return '<h2>Welcome</h2><p>This wizard will configure AI Chat quickly.</p><button class="button button-primary" data-action="next">Start</button>';
      case 1: return discoveryView(state);
      case 2: return apiKeyView(state);
      case 3: return indexingView(state);
      case 4: return finishView(state);
    }
    return '';
  }
  function discoveryView(state){
    if(!state.discover){return '<p>'+esc(aichat_easycfg_ajax.i18n.discovering)+'</p>';}
    const mode = state.discover.mode||'smart';
    const items = state.discover.items||[];
    // Ensure selection map
    if(!state.selected){ state.selected = {}; items.forEach(it=> state.selected[it.id]=true ); }
    const list = items.map(it=>{
      const checked = state.selected[it.id] ? 'checked' : '';
      return '<li><label><input type="checkbox" data-item-id="'+it.id+'" '+checked+'> '+esc(it.title||('('+it.type+' '+it.id+')'))+' <span class="aichat-item-type">['+esc(it.type)+']</span></label></li>';
    }).join('');
    return '<h2>Content Discovered</h2>'+
      '<p>'+state.discover.total+' items found ('+esc(mode)+'). Select which to index:</p>'+
      '<ul class="aichat-discover-list">'+list+'</ul>'+
      '<p><button class="button" data-action="toggle-all">'+(allSelected(state)?'Unselect All':'Select All')+'</button></p>'+
      '<button class="button button-primary" data-action="next" '+(countSelected(state)===0?'disabled':'')+'>Continue ('+countSelected(state)+' selected)</button>'+
      '<button class="button" data-action="back">Back</button>';
  }
  function indexingView(state){
    const total = state.discover?state.discover.ids.length:0;
    const done = state.indexedCount||0;
    const percent = total? Math.round(done/total*100):0;
    return '<h2>Indexing</h2><p>'+esc(aichat_easycfg_ajax.i18n.indexing)+'</p>'+
      '<div class="aichat-progress"><div style="width:'+percent+'%"></div></div>'+
      '<p>'+done+' / '+total+' ('+percent+'%)</p>';
  }
  // botView removed (auto-link implemented after indexing)
  function apiKeyView(state){
    const hasKey = !!state.hasApiKey;
    let html = '<h2>OpenAI API Key</h2>'+
      '<p>The OpenAI API key is required to use "Use Embeddings" context mode and answer questions.</p>';
    if(hasKey){
      html += '<p class="notice notice-success">An API key is already configured.</p>'+
        '<p><button class="button button-primary" data-action="next">Continue</button></p>'+
        '<button class="button" data-action="back">Back</button>';
    } else {
      html += '<p class="notice notice-warning">No key detected. Add one to continue.</p>'+
        '<p><input type="text" style="width:420px" placeholder="sk-..." value="" id="aichat-ec-apikey" /> <button class="button" data-action="save-key">Save Key</button></p>'+
        '<p><button class="button button-primary" data-action="next" disabled>Continue</button></p>'+
        '<button class="button" data-action="back">Back</button>';
    }
    return html;
  }
  function finishView(state){
    return '<h2>Congratulations!</h2>'+
      '<p>Your AI Chat bot is now installed and linked to your indexed content.</p>'+
      '<p>You can fine‑tune advanced settings anytime in: <strong>Settings</strong>, <strong>Contexts</strong>, and <strong>Bots</strong>.</p>'+
  '<p><a class="button button-primary" href="admin.php?page=aichat-bots-settings">Go to Bots</a> '+
  '<a class="button" href="admin.php?page=aichat-settings">Settings</a> '+
  // Corrected contexts settings link slug to actual submenu slug 'aichat-contexto-settings'
  '<a class="button" href="admin.php?page=aichat-contexto-settings">Contexts</a></p>';
  }
  function esc(s){return (''+s).replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));}

  function bind(state){
    const $root=$(rootSel);
    $root.find('[data-action="next"]').on('click',()=>{
      if(state.step===0){ state.step=1; render(state); discover(state); return; }
      if(state.step===1){ // after discovery -> API key step
        if(state.discover && state.discover.ids){
          const filtered = state.discover.ids.filter(id=> !!state.selected[id]);
          state.discover.ids = filtered;
        }
        fetchStatus(state, ()=>{ state.step=2; render(state); }); return; }
      if(state.step===2 && state.hasApiKey){ // proceed to indexing
        state.step=3; render(state); startIndexing(state); return; }
      if(state.step===3 && state.indexDone){ ensureBotLinked(state, ()=>{ state.step=4; render(state); }); return; }
    });
    $root.find('[data-action="save-key"]').on('click',()=>{
      const key = $('#aichat-ec-apikey').val().trim();
      $.post(aichat_easycfg_ajax.ajax_url,{action:'aichat_easycfg_save_api_key',nonce:getNonce(),api_key:key},function(r){
        if(r && r.success){
          state.hasApiKey = (r.data.saved?1:0);
          render(state);
        }
      });
    });
  function fetchStatus(state, cb){
    $.post(aichat_easycfg_ajax.ajax_url,{action:'aichat_easycfg_status',nonce:getNonce()},function(r){
      if(r && r.success){ state.hasApiKey = r.data.has_api_key?1:0; }
      if(typeof cb==='function') cb();
    });
  }
    // Checkbox changes
    $root.find('.aichat-discover-list input[type="checkbox"]').on('change', function(){
      const id = parseInt($(this).data('item-id'),10); if(!state.selected) state.selected={};
      state.selected[id] = this.checked;
      // Rerender to update counts/buttons
      render(state);
    });
    $root.find('[data-action="toggle-all"]').on('click', function(){
      const items = (state.discover && state.discover.items) ? state.discover.items : [];
      const everything = allSelected(state);
      state.selected = {}; // reset
      items.forEach(it=>{ state.selected[it.id] = !everything; });
      render(state);
    });
    $root.find('[data-action="back"]').on('click',()=>{
      if(state.step>0){ state.step--; render(state);} });
    // no manual create bot button now
  }

  function discover(state){
    $.post(aichat_easycfg_ajax.ajax_url,{action:'aichat_easycfg_discover',mode:'smart',nonce:getNonce()},function(r){
      if(r && r.success){ state.discover=r.data; } else { state.discover={total:0,ids:[]}; }
      render(state);
    });
  }
  function countSelected(state){ if(!state.selected) return 0; return Object.values(state.selected).filter(v=>v).length; }
  function allSelected(state){
    const items = (state.discover && state.discover.items)? state.discover.items:[];
    if(!items.length) return false;
    let all=true; items.forEach(it=>{ if(!state.selected || !state.selected[it.id]) all=false; });
    return all;
  }

  function startIndexing(state){
    state.indexedCount=0; state.indexDone=false;
    if(!state.contextId){ // create context first
      $.post(aichat_easycfg_ajax.ajax_url,{action:'aichat_easycfg_create_context',nonce:getNonce(),name:'Easy Config Context'},function(r){
        if(r && r.success){ state.contextId=r.data.context_id; batchIndex(state); } else { console.error(r); }
      });
    } else {
      batchIndex(state);
    }
  }
  function batchIndex(state){
    const ids = state.discover.ids; const batchSize=10;
    const slice = ids.slice(state.indexedCount, state.indexedCount+batchSize);
  if(!slice.length){
    state.indexDone=true;
    render(state);
    // Auto-advance: once indexing finished, ensure bot linked then finish
    ensureBotLinked(state, ()=>{ state.step=4; render(state); });
    return;
  }
    $.post(aichat_easycfg_ajax.ajax_url,{action:'aichat_easycfg_index_batch',nonce:getNonce(),context_id:state.contextId,ids:slice},function(r){
      state.indexedCount += slice.length;
      render(state);
      batchIndex(state);
    });
  }

  function ensureBotLinked(state, cb){
    if(state.botLinked){ if(typeof cb==='function') cb(); return; }
    if(!state.contextId){ if(typeof cb==='function') cb(); return; }
    $.post(aichat_easycfg_ajax.ajax_url,{action:'aichat_easycfg_create_bot',nonce:getNonce(),context_id:state.contextId},function(r){
      if(r && r.success){
        state.botLinked=true;
        // Auto-save global settings so user doesn't need to visit the Settings screen.
        // We rely on existing options names: aichat_global_bot_enabled, aichat_global_bot_slug
        // Silent post; no UI blocking.
        if(!state.globalSaved){
          $.post(aichat_easycfg_ajax.ajax_url, {
            action: 'aichat_easycfg_save_global_bot',
            nonce: getNonce(),
            bot_slug: 'default'
          }, function(resp){ state.globalSaved = true; });
        }
      }
      if(typeof cb==='function') cb();
    });
  }

  function getNonce(){ return $(rootSel).data('nonce'); }

  $(function(){
    const initState={step:0};
    render(initState);
  });
})(jQuery);
