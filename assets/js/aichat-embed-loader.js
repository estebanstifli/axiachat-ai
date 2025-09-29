/* AI Chat Embed Loader
 * Minimal external embed script.
 * Usage (external site):
 * <div id="aichat-embed" data-bot="default"></div>
 * <script async src="https://TU-DOMINIO/wp-content/plugins/ai-chat/assets/js/aichat-embed-loader.js"></script>
 */
(function(){
  'use strict';

  var ORIGIN = (function(){
    // Detect script src to infer plugin base automatically
    var current = document.currentScript || (function(){
      var scripts = document.getElementsByTagName('script');
      return scripts[scripts.length-1];
    })();
    if (!current || !current.src) return '';
    // Expected: https://domain/.../assets/js/aichat-embed-loader.js
    try {
      var u = new URL(current.src);
      return u.origin;
    } catch(e){ return ''; }
  })();

  if (!ORIGIN) return;

  // Derive plugin base dynamically from script src path (removing trailing /assets/js/aichat-embed-loader.js)
  var PLUGIN_BASE = (function(){
    var current = document.currentScript || (function(){
      var scripts = document.getElementsByTagName('script');
      return scripts[scripts.length-1];
    })();
    if (!current || !current.src) return ORIGIN; // fallback
    try {
      var full = current.src.split('?')[0].split('#')[0];
      // Remove last 3 segments if they are assets/js/aichat-embed-loader.js
      var parts = full.replace(/^[a-z]+:\/\//i,'').split('/');
      // Find index inside parts of 'assets'
      var idxAssets = parts.lastIndexOf('assets');
      if (idxAssets > 0) {
        // plugin base is protocol + everything before 'assets'
        var proto = full.match(/^[a-z]+:\/\//i);
        var pre = parts.slice(0, idxAssets).join('/');
        return (proto ? proto[0] : 'https://') + pre; // protocol preserved
      }
      // Fallback: strip /assets/js/aichat-embed-loader.js by regex
      return full.replace(/\/assets\/js\/aichat-embed-loader\.js$/,'');
    } catch(e){ return ORIGIN; }
  })();
  var AJAX_URL    = ORIGIN + '/wp-admin/admin-ajax.php';
  var NONCE_ENDPOINT = ORIGIN + '/?aichat_embed_nonce=1';

  // Select targets
  var targets = [].slice.call(document.querySelectorAll('#aichat-embed, [data-aichat-embed]'));
  if (!targets.length) return;

  // Spinner / pending placeholders
  targets.forEach(function(t){
    if (!t.getAttribute('data-aichat-embed')) t.setAttribute('data-aichat-embed','1');
    if (!t.innerHTML.trim()) {
      t.innerHTML = '<div style="font:14px system-ui,Arial;opacity:.7;">Loading AI Chat…</div>';
    }
  });

  // Fetch nonce + optional UI map once (pass bot slugs)
  function fetchNonce(slugs){
    var url = NONCE_ENDPOINT;
    if (slugs && slugs.length){
      url += (url.indexOf('?')===-1 ? '?' : '&') + 'bots=' + encodeURIComponent(slugs.join(','));
    }
    return fetch(url, { credentials: 'omit' })
      .then(function(r){ return r.json(); })
      .catch(function(){ return {}; });
  }

  function ensureCore(onReady){
    if (window.__AIChatCoreLoaded) { onReady(); return; }
    var s = document.createElement('script');
    s.src = PLUGIN_BASE + '/assets/js/aichat-frontend.js';
    s.async = true;
    s.onload = function(){ window.__AIChatCoreLoaded = true; onReady(); };
    document.head.appendChild(s);
  }

  // Shadow DOM + roots queue
  window.AIChatEmbedRoots = window.AIChatEmbedRoots || [];

  // Collect unique bot slugs
  var botSlugs = Array.from(new Set(targets.map(function(t){ return t.getAttribute('data-bot')||'default'; })));
  fetchNonce(botSlugs).then(function(data){
    if (!data || !data.nonce){
      targets.forEach(function(t){ t.innerHTML = '<div style="color:#b00;font:14px system-ui;">Embed error (nonce)</div>'; });
      return;
    }

    // Prepare global vars once
    window.AIChatVars = window.AIChatVars || {};
    window.AIChatVars.ajax_url = AJAX_URL;
    window.AIChatVars.nonce    = data.nonce;
    if (typeof window.AIChatVars.page_id === 'undefined') window.AIChatVars.page_id = 0;

    var uiMap = (data && data.ui) ? data.ui : {};
    targets.forEach(function(t){
        var bot = t.getAttribute('data-bot') || 'default';
        var cfg = uiMap[bot] || null;
        try {
          var shadow = t.attachShadow ? t.attachShadow({mode:'open'}) : null;
          var host = shadow || t; // fallback without shadow
          var link = document.createElement('link');
          link.rel = 'stylesheet';
          link.href = PLUGIN_BASE + '/assets/css/aichat-frontend.css';
          host.appendChild(link);
          var wrap = document.createElement('div');
          wrap.className = 'aichat-widget';
          wrap.setAttribute('data-bot', bot);
          if (cfg){
            if (cfg.color) wrap.setAttribute('data-color', cfg.color);
            if (cfg.position) wrap.setAttribute('data-position', cfg.position);
            if (cfg.placeholder) wrap.setAttribute('data-placeholder', cfg.placeholder);
            if (cfg.start_sentence) wrap.setAttribute('data-start-sentence', cfg.start_sentence);
            if (cfg.button_send) wrap.setAttribute('data-button-send', cfg.button_send);
            if (cfg.avatar_enabled) wrap.setAttribute('data-avatar-enabled', '1');
            if (cfg.avatar_url) wrap.setAttribute('data-avatar-url', cfg.avatar_url);
            if (cfg.closable) wrap.setAttribute('data-closable', '1');
            if (cfg.minimizable) wrap.setAttribute('data-minimizable', '1');
            if (cfg.draggable) wrap.setAttribute('data-draggable', '1');
            if (cfg.minimized_default) wrap.setAttribute('data-minimized-default', '1');
            if (cfg.superminimized_default) wrap.setAttribute('data-superminimized-default', '1');
          }
          host.appendChild(wrap);
          window.AIChatEmbedRoots.push(wrap);
          t.__aichatEmbedReady = true;
          t.innerHTML = '';
        } catch(e){
          t.innerHTML = '<div style="color:#b00;font:14px system-ui;">Shadow DOM unsupported</div>';
        }
    });
    ensureCore(function(){ /* Core will auto-init including our pushed roots with UI data attributes */ });
  });
})();
