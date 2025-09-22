/**
 * AI Chat — Frontend (multi-instancia, siempre flotante)
 * - Lee config desde data-attrs: position, color, width, height, title, placeholder
 * - Siempre usa layout flotante (no depende de ui_layout en BD)
 * - Origen del bot: data-bot (shortcode/core) o AIChatGlobal.bot_slug (global)
 * - AJAX: usa AIChatVars.ajax_url y AIChatVars.nonce
 *
 * Requiere jQuery y que el PHP haya hecho wp_localize_script('AIChatVars', ...).
 */
'use strict';

(function($){
  // Activa logs si añades ?aichat_debug=1 a la URL
  var DEBUG = /(?:\?|&)aichat_debug=1(?:&|$)/.test(window.location.search);
  if (DEBUG) {
    console.log('[AIChat] JS loaded. jQuery=', typeof $, 'AIChatVars=', typeof window.AIChatVars, 'AIChatGlobal=', typeof window.AIChatGlobal);
  }

  $(function(){
    // ---------- 1) Comprobaciones iniciales ----------
    if (typeof AIChatVars === 'undefined' || !AIChatVars || !AIChatVars.ajax_url) {
      console.error('[AIChat] AIChatVars no definido o sin ajax_url.');
      return;
    }

    // ---------- 2) Localiza instancias ----------
    var $instances = $('.aichat-widget');
    if ($instances.length === 0) {
      var $legacy = $('#aichat-widget'); // compat muy antigua
      if ($legacy.length) {
        $legacy.addClass('aichat-widget');
        $instances = $('.aichat-widget');
      }
    }
    if (DEBUG) console.log('[AIChat] instancias encontradas:', $instances.length);
    if ($instances.length === 0) return;

    var uidCounter = 0;

    // ---------- 3) Inicializa cada instancia ----------
    $instances.each(function(idx){
      var $root = $(this);

      // Evita doble init si el script se ejecuta dos veces
      if ($root.data('aichatReady')) {
        if (DEBUG) console.log('[AIChat] instancia ya inicializada idx=', idx);
        return;
      }
      $root.data('aichatReady', 1);

      // Bot slug: data-bot → AIChatGlobal.bot_slug → null
      var botSlug = $root.data('bot') || (window.AIChatGlobal && AIChatGlobal.bot_slug) || null;

      // Datos de UI desde data-attrs (no hay ui_layout en BD; el bot es SIEMPRE flotante)
      var botType   = String($root.data('type') || 'text'); // 'text' | 'voice_text'
      var rawPos   = (String($root.data('position') || '')).toLowerCase();
      var position = normPos(rawPos || 'bottom-right');  // 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left'
      var color     = String($root.data('color') || '');
      var width     = parseInt($root.data('width'), 10)  || 0;
      var mHeight   = parseInt($root.data('height'), 10) || 0;
      var title       = $root.data('title') || 'AI Chat';
      var placeholder  = $root.data('placeholder') || 'Write your question...';
      var startSentence = $root.data('startSentence') || '';
      var sendLabel    = $root.data('buttonSend') || 'Send'; // nuevo
      // Ventana
      var closable      = !!parseInt($root.data('closable') || 0, 10);
      var minimizable   = !!parseInt($root.data('minimizable') || 0, 10);
      var draggable     = !!parseInt($root.data('draggable') || 0, 10);
      var minimizedDefault = !!parseInt($root.data('minimizedDefault') || 0, 10);


      // Avatar dataset
      var avatarEnabled = !!parseInt($root.data('avatarEnabled') || 0, 10);
      var avatarUrl     = String($root.data('avatarUrl') || '');

      if (DEBUG) console.log('[AIChat] init idx=', idx, { botSlug, rawPos, position, color, width, mHeight, title, avatarEnabled, avatarUrl });

      // Si no hay bot, muestra aviso para no romper layout
      if (!botSlug) {
        $root.html(
          '<div class="aichat-inner">' +
            '<div class="aichat-header">'+ escapeHtml(title) +'</div>' +
            '<div class="aichat-messages"></div>' +
            '<div class="aichat-inputbar"><em>Bot no configurado.</em></div>' +
          '</div>'
        );
        console.warn('[AIChat] Bot no configurado idx=', idx); 
        return;
      }

      // Construye UI si el contenedor está vacío (o no trae .aichat-inner)
      if ($root.children().length === 0 || !$root.find('.aichat-inner').length) {
        var uid = 'aichat-' + (++uidCounter);

        var headerCls = 'aichat-header' + (avatarEnabled && avatarUrl ? ' with-avatar' : '');
        var headerHtml;
        if (avatarEnabled && avatarUrl) {
          // Solo avatar + start sentence (sin nombre)
          headerHtml =
            '<img class="aichat-avatar-badge" src="'+ escapeHtml(avatarUrl) +'" alt="'+ escapeHtml(title) +'">' +
            (startSentence
              ? '<span class="aichat-header-text"><span class="aichat-start-sentence">'+ escapeHtml(startSentence) +'</span></span>'
              : '');
        } else {
          // Nombre (gris) + “: ” + start sentence (blanca) si existe
          headerHtml = 
            '<span class="aichat-header-text">' +
              (startSentence
                ? '<span class="aichat-header-title">'+ escapeHtml(title) +': </span><span class="aichat-start-sentence">'+ escapeHtml(startSentence) +'</span>'
                : '<span class="aichat-header-title">'+ escapeHtml(title) +'</span>') +
            '</span>';
        }
       // Controles (derecha)
       var controlsHtml = '<div class="aichat-header-controls">';
       if (minimizable) controlsHtml += '<button type="button" class="aichat-btn aichat-btn-minimize" aria-label="Minimize">−</button>';
       if (closable)    controlsHtml += '<button type="button" class="aichat-btn aichat-btn-close" aria-label="Close">×</button>';
       controlsHtml += '</div>';
       headerHtml += controlsHtml;

        var html =
          '<div class="aichat-inner" data-uid="'+uid+'">' +
            '<div class="'+headerCls+'" id="'+uid+'-header" aria-label="'+ escapeHtml(title) +'">'+ headerHtml +'</div>' +
            '<div class="aichat-messages" id="'+uid+'-messages" aria-live="polite"></div>' +
            '<div class="aichat-inputbar">' +
              '<input type="text" class="aichat-input" id="'+uid+'-input" placeholder="'+ escapeHtml(placeholder) +'" autocomplete="off" />' +
              (botType==='voice_text'
                ? '<button type="button" class="aichat-mic" id="'+uid+'-mic" aria-pressed="false" aria-label="Start voice input">'+
                    '<svg class="icon-mic" viewBox="0 0 16 16" aria-hidden="true">'+
                      '<path fill="currentColor" d="M8 11a3 3 0 0 0 3-3V4a3 3 0 1 0-6 0v4a3 3 0 0 0 3 3z"/>'+
                      '<path fill="currentColor" d="M5 8a.5.5 0 0 1 1 0 2 2 0 1 0 4 0 .5.5 0 0 1 1 0 3 3 0 0 1-2.5 2.959V13h1.5a.5.5 0 0 1 0 1H6a.5.5 0 0 1 0-1h1.5v-2.041A3 3 0 0 1 5 8z"/>'+
                    '</svg>'+
                    '<svg class="icon-stop" viewBox="0 0 16 16" aria-hidden="true">'+
                      '<rect x="4" y="4" width="8" height="8" fill="currentColor"></rect>'+
                    '</svg>'+
                    '<span class="screen-reader-text" style="position:absolute;left:-9999px;">Mic</span>'+
                  '</button>'
                : '') +
              '<button type="button" class="aichat-send" id="'+uid+'-send">'+ escapeHtml(sendLabel) +'</button>' +
            '</div>' +
          '</div>';

        $root.html(html);
        if (DEBUG) console.log('[AIChat] UI construida idx=', idx, 'uid=', uid);
      } else {
        if (DEBUG) console.log('[AIChat] usando UI existente idx=', idx);
      }

      // Referencias
      var $inner    = $root.find('.aichat-inner').first();
      var $messages = $inner.find('.aichat-messages').first();
      var $input    = $inner.find('.aichat-input').first();
      var $sendBtn  = $inner.find('.aichat-send').first();
      var $micBtn   = $inner.find('.aichat-mic').first();
     var $ttsStop  = $inner.find('.aichat-tts-stop').first();

     // ===== GDPR CONSENT COMO MENSAJE =====
     // Nueva lógica: en lugar de overlay que tapa header, insertamos una "burbuja" dentro del área de mensajes
     // Mantiene la cabecera (arrastrar, minimizar, cerrar) operativa. Inputs bloqueados hasta aceptar.
     try {
       if (window.AIChatGDPR && parseInt(AIChatGDPR.enabled,10) === 1) {
         var consentCookie = AIChatGDPR.cookie || 'aichat_gdpr_ok';
         var hasConsent = document.cookie.indexOf(consentCookie+'=1') !== -1;
         if (!hasConsent) {
           // Bloquear inputs inmediatamente (aunque el widget esté minimizado)
           $input.prop('disabled', true).attr('aria-disabled','true');
           $sendBtn.prop('disabled', true).attr('aria-disabled','true');
           if ($micBtn.length) $micBtn.prop('disabled', true).attr('aria-disabled','true');

           // Función para inyectar el bloque GDPR si aún no existe
           function injectGDPRMessage(){
             if ($messages.find('.aichat-gdpr-consent').length) return; // ya insertado
             var boxHtml = ''+
               '<div class="message bot-message aichat-gdpr-consent" role="group" aria-label="GDPR consent">'+
                 '<div class="aichat-gdpr-text" style="margin-bottom:8px;">'+ (AIChatGDPR.text || '') +'</div>'+
                 '<button type="button" class="aichat-gdpr-accept" style="background:#0073aa;color:#fff;border:0;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;">'+ escapeHtml(AIChatGDPR.button || 'OK') +'</button>'+
               '</div>';
             $messages.append(boxHtml);
             // Scroll al final cuando se muestre
             $messages.scrollTop($messages[0].scrollHeight);
           }

           // Si no está minimizado ahora, lo insertamos ya; si está minimizado esperamos al primer maximize
           if (!$inner.hasClass('is-minimized')) {
             injectGDPRMessage();
           } else {
             // Escuchar cambio de minimizado
             $inner.on('click.aichatGDPR', '.aichat-btn-minimize', function(){
               // El toggle ocurre después del click; usamos setTimeout corto
               setTimeout(function(){
                 if (!$inner.hasClass('is-minimized')) {
                   injectGDPRMessage();
                   $inner.off('click.aichatGDPR', '.aichat-btn-minimize');
                 }
               }, 30);
             });
           }

           // Delegar aceptación (aunque el mensaje aún no se haya inyectado, se manejará tras inyección)
           $messages.on('click', '.aichat-gdpr-accept', function(){
             var expDays = 365;
             var maxAge = expDays*24*60*60;
             document.cookie = consentCookie + '=1; Max-Age='+maxAge+'; Path=/; SameSite=Lax';
             $messages.find('.aichat-gdpr-consent').remove();
             $input.prop('disabled', false).removeAttr('aria-disabled').focus();
             $sendBtn.prop('disabled', false).removeAttr('aria-disabled');
             if ($micBtn.length) $micBtn.prop('disabled', false).removeAttr('aria-disabled');
           });
         }
       }
     } catch(e){ if (DEBUG) console.warn('[AIChat][GDPR] error consent message', e); }

     // Sesión y carga de historial
     var sessionId = getOrCreateSessionId();
     loadHistory($messages, botSlug, sessionId);

     // ----- Voz (STT/TTS) por instancia -----
     var recognition = null;
     var isMicActive = false;
     var supportsSTT = !!(window.SpeechRecognition || window.webkitSpeechRecognition);
     var supportsTTS = !!window.speechSynthesis;
     var ttsActive = false;
     var ttsWasMicActive = false;
     var ttsCancelledByUser = false;

     // Crea el overlay TTS (una vez por widget)
     var $inner = $root.find('.aichat-inner');
     var $ttsOverlay = $inner.find('.aichat-tts-overlay');
     if (!$ttsOverlay.length) {
       $ttsOverlay = $(
         '<div class="aichat-tts-overlay" aria-hidden="true">'+
           '<button type="button" class="aichat-tts-overlay-btn" aria-label="Detener lectura">'+
             '<svg viewBox="0 0 16 16" aria-hidden="true"><rect x="4" y="4" width="8" height="8" fill="currentColor"/></svg>'+
           '</button>'+
         '</div>'
       );
       $inner.append($ttsOverlay);
     }
     // Click en overlay: solo cancela TTS, no toca el micro
     $ttsOverlay.off('click.aichat').on('click.aichat', function(e){
       e.preventDefault(); e.stopPropagation();
       ttsCancelledByUser = true;
       try { window.speechSynthesis.cancel(); } catch(e){}
       ttsActive = false;
       $ttsOverlay.removeClass('show').attr('aria-hidden','true');
     });

     // Pre-carga voces (algunos navegadores necesitan getVoices() para inicializar)
     if (supportsTTS) {
       try { window.speechSynthesis.getVoices(); } catch(e){}
     }

     if (botType === 'voice_text') {
       if (!supportsSTT) {
         // Oculta el botón si no hay STT
         if ($micBtn && $micBtn.length) $micBtn.hide();
       } else {
         var Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
         recognition = new Rec();
         recognition.continuous = true;
         recognition.interimResults = false;
         recognition.lang = navigator.language || 'es-ES';
 
         recognition.onresult = function(event){
           var res = event.results[event.results.length - 1];
           var transcript = res[0].transcript || '';
           $input.val(transcript);
           if (res.isFinal) {
             $micBtn.trigger('click'); // detener grabación
             sendMessage($root, $messages, $input, $sendBtn, botSlug, voiceOpts, sessionId);
           }
         };
         recognition.onerror = function(){
           isMicActive = false;
           $micBtn.attr('aria-pressed','false').removeClass('is-recording').attr('aria-label','Start voice input');
           if ($micBtn.length) { $micBtn.find('.icon-stop').hide(); $micBtn.find('.icon-mic').show(); }
         };
         recognition.onend = function(){
           isMicActive = false;
           $micBtn.attr('aria-pressed','false').removeClass('is-recording').attr('aria-label','Start voice input');
           if ($micBtn.length) { $micBtn.find('.icon-stop').hide(); $micBtn.find('.icon-mic').show(); }
         };
 
         if ($micBtn && $micBtn.length) {
           $micBtn.on('click', function(){
             // aichatWarmupTTS();  // eliminado
             if (!isMicActive) {
               try {
                 recognition.start();
                 isMicActive = true;
                 $micBtn.attr('aria-pressed','true').addClass('is-recording').attr('aria-label','Stop voice input');
                 $micBtn.find('.icon-mic').hide(); $micBtn.find('.icon-stop').show();
               } catch(e){}
             } else {
               try { recognition.stop(); } catch(e){}
               isMicActive = false;
               $micBtn.attr('aria-pressed','false').removeClass('is-recording').attr('aria-label','Start voice input');
               $micBtn.find('.icon-stop').hide(); $micBtn.find('.icon-mic').show();
             }
           });
         }
       }
       // Si el navegador no soporta TTS oculta el botón de parar
       if (!supportsTTS && $ttsStop.length) $ttsStop.hide();
       if ($ttsStop.length) {
         $ttsStop.on('click', function(){
           if (!supportsTTS) return;
           try { window.speechSynthesis.cancel(); } catch(e){}
           ttsActive = false;
           $ttsStop.hide();
         });
       }
     }
 
     // Mic: alterna estado/ícono
     if ($micBtn && $micBtn.length) {
       $micBtn.on('click', function(){
         if (!isMicActive) {
           try { recognition.start(); } catch(e){}
           isMicActive = true;
           $micBtn.attr('aria-pressed','true').addClass('is-recording');
           // por si algún CSS externo interfiere
           $micBtn.find('.icon-mic').hide();
           $micBtn.find('.icon-stop').show();
         } else {
           try { recognition.stop(); } catch(e){}
           isMicActive = false;
           $micBtn.attr('aria-pressed','false').removeClass('is-recording');
           $micBtn.find('.icon-stop').hide();
           $micBtn.find('.icon-mic').show();
         }
       });
     }

     // Botón Stop (TTS): no reactivar micro al cancelar manualmente
     if ($ttsStop && $ttsStop.length) {
       $ttsStop.on('click', function(){
         ttsCancelledByUser = true;
         try { window.speechSynthesis.cancel(); } catch(e){}
         ttsActive = false;
         $ttsStop.hide();
       });
     }

     // TTS (no cambiar la lógica de voz que ya funciona)
     function speakResponse(text){
       if (!supportsTTS || !text) return;

       var plain = htmlToSpeechText(String(text));

       try {
         if (window.speechSynthesis.speaking || window.speechSynthesis.pending) {
           window.speechSynthesis.cancel();
         }
       } catch(e){}

       ttsWasMicActive = isMicActive;
       if (ttsWasMicActive && recognition) { try { recognition.stop(); } catch(e){} }

       var utter = new SpeechSynthesisUtterance(plain);
       utter.lang  = navigator.language || 'es-ES';
       utter.rate  = 1;
       utter.pitch = 1;

       // Usar overlay centrado (ocultar cualquier stop inline si existe)
       ttsActive = true;
       if ($ttsStop && $ttsStop.length) { $ttsStop.hide().off('click.aichat'); }
       if ($ttsOverlay && $ttsOverlay.length) {
         $ttsOverlay.addClass('show').attr('aria-hidden','false');
       }

       utter.onend = function(){
         ttsActive = false;
         if ($ttsOverlay && $ttsOverlay.length) {
           $ttsOverlay.removeClass('show').attr('aria-hidden','true');
         }
         // Solo reactivar micro si NO lo paró el usuario
         if (ttsWasMicActive && !ttsCancelledByUser && recognition) {
           try { recognition.start(); } catch(e){}
         }
         ttsWasMicActive = false;
         ttsCancelledByUser = false;
       };

       utter.onerror = function(){
         ttsActive = false;
         if ($ttsOverlay && $ttsOverlay.length) {
           $ttsOverlay.removeClass('show').attr('aria-hidden','true');
         }
         ttsWasMicActive = false;
         ttsCancelledByUser = false;
       };

       try { window.speechSynthesis.speak(utter); } catch(e){}
     }

     // Convierte HTML a texto para el TTS
     function htmlToSpeechText(html){
       var el = document.createElement('div');
       el.innerHTML = String(html);

       // Mejora pausas: <br> y cierre de <p> → punto y espacio
       el.querySelectorAll('br').forEach(function(br){
         br.replaceWith(document.createTextNode('. '));
       });
       el.querySelectorAll('p').forEach(function(p, idx, arr){
         if (p.lastChild && p.lastChild.nodeType === 3) {
           p.lastChild.textContent += (idx < arr.length - 1) ? '. ' : '';
         } else {
           p.appendChild(document.createTextNode(idx < arr.length - 1 ? '. ' : ''));
         }
       });

       var txt = el.textContent || el.innerText || '';
       return txt.replace(/\s+/g, ' ').trim();
     }
 
     var voiceOpts = (botType==='voice_text') ? {
       onBotResponse: function(text){ speakResponse(text); }
     } : null;

      // ---------- 4) Aplicar CONFIG de UI (siempre flotante) ----------
      $root.addClass('is-global'); // siempre flotante
      if (draggable) $root.addClass('is-draggable');
      // Posición → clases pos-*
      $root.removeClass('pos-bottom-right pos-bottom-left pos-top-right pos-top-left');
      $root.addClass('pos-' + position);

      // Ancho del contenedor (flotante)
      if (width > 0) {
        $root.css('width', width + 'px');
      }

      // Altura del área de mensajes
      if (mHeight > 0) {
        $messages.css('height', mHeight + 'px');
      }

      // Color de tema
      if (color) {
        $inner.find('.aichat-header').css('background-color', color);
        $inner.find('.aichat-send').css('background-color', color);
      }

      // Estado inicial minimizado
      if (minimizedDefault) $inner.addClass('is-minimized');

      // ---------- 5) Eventos ----------
      $micBtn.on('click', function(e){
        e.preventDefault(); e.stopPropagation();
        if (!isMicActive) {
          try { recognition.start(); } catch(e){}
          isMicActive = true;
          $micBtn.attr('aria-pressed','true').addClass('is-recording');
          $micBtn.find('.icon-mic').hide();
          $micBtn.find('.icon-stop').show();
        } else {
          try { recognition.stop(); } catch(e){}
          isMicActive = false;
          $micBtn.attr('aria-pressed','false').removeClass('is-recording');
          $micBtn.find('.icon-stop').hide();
          $micBtn.find('.icon-mic').show();
        }
      });

      $sendBtn.on('click', function(e){
        e.preventDefault(); e.stopPropagation();
        sendMessage($root, $messages, $input, $sendBtn, botSlug, voiceOpts, sessionId);
      });

      $input.on('keydown', function(e){
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault(); e.stopPropagation();
          sendMessage($root, $messages, $input, $sendBtn, botSlug, voiceOpts, sessionId);
        }
      });

      // Eventos de ventana
      if (minimizable) {
        $inner.on('click', '.aichat-btn-minimize', function(e){
          e.preventDefault();
          $inner.toggleClass('is-minimized');
        });
      }
      if (closable) {
        $inner.on('click', '.aichat-btn-close', function(e){
          e.preventDefault();
          $root.hide();
        });
      }

      // Arrastrable (header como “handle”), solo flotante
      if (draggable && $root.hasClass('is-global')) {
        makeDraggable($root, $inner.find('.aichat-header'));
      }

      if (DEBUG) console.log('[AIChat] instancia lista idx=', idx);
    });

    // ---------- helpers de envío y UI ----------

    function sendMessage($root, $messages, $input, $sendBtn, botSlug, opts, sessionId) {
        // Si ya está limitado, no permitir más envíos
        var limitedInfo = $root.data('aichatLimited');
        if (limitedInfo) {
          // Opcional: volver a mostrar el mensaje de límite (no duplicar demasiadas veces)
          return;
        }
      var message = ($input.val() || '').trim();
      if (!message) {
        if (/[^\s]/.test($input.val() || '')) $input.val(''); // limpia si eran espacios
        return;
      }

      appendUser($messages, message);
      $input.val('');

      var $typing = appendTyping($messages);
      lockInputs($input, $sendBtn, true);

      var payload = {
        action:   'aichat_process_message',
        nonce:    AIChatVars.nonce,
        bot_slug: botSlug,
        message:  message,
        page_id:  (AIChatVars && AIChatVars.page_id) ? parseInt(AIChatVars.page_id,10) : 0,
        session_id: sessionId,
        debug:    DEBUG ? 1 : 0
      };

      if (DEBUG) payload.debug = 1;

      $.ajax({
        url:    AIChatVars.ajax_url,
        method: 'POST',
        data:   payload
      })
      .done(function(response){
        if (DEBUG && response && response.data && response.data.debug) {
          console.info('[AIChat][debug]', response.data.debug);
        }
        $typing.remove();
        // Caso límite (success true con limited)
        if (response && response.success && response.data) {
          var msg = typeof response.data.message !== 'undefined' ? String(response.data.message) : '';
          var isLimited = !!response.data.limited || (response.data.limit_type && /daily_total|per_user/.test(response.data.limit_type));
          if (msg) appendBot($messages, msg);
          if (isLimited) {
            setLimited($root, msg, response.data.limit_type || 'unknown');
            return; // no continuar TTS
          }
          if (opts && typeof opts.onBotResponse === 'function' && msg) {
            try { opts.onBotResponse(msg); } catch(e){}
          }
        } else {
          // success = false (error) → puede ser daily_total_hidden o error normal
          var errMsg = (response && response.data && response.data.message) ? String(response.data.message) : 'Error desconocido.';
          var lt = response && response.data && response.data.limit_type ? response.data.limit_type : '';
          if (lt === 'daily_total_hidden') {
            // Ocultar completamente el widget
            setLimited($root, errMsg, lt, true);
            return;
          }
          appendError($messages, errMsg);
        }
      })
      .fail(function(jqXHR, textStatus, errorThrown){
        console.error('[AIChat] AJAX FAIL:', textStatus, errorThrown, jqXHR && jqXHR.status, jqXHR && jqXHR.responseText);
        $typing.remove();
        appendError($messages, 'Error de comunicación: ' + (errorThrown || textStatus || 'desconocido'));
      })
      .always(function(){
        lockInputs($input, $sendBtn, false);
        scrollToBottom($messages);
      });
    }

      // Marca el widget como limitado y desactiva inputs/mic
      function setLimited($root, message, limitType, hideAll){
        $root.data('aichatLimited', { message: message, type: limitType });
        $root.addClass('aichat-limited');
        var $input = $root.find('.aichat-input');
        var $send  = $root.find('.aichat-send');
        var $mic   = $root.find('.aichat-mic');
        $input.prop('disabled', true).attr('aria-disabled','true');
        $send.prop('disabled', true).attr('aria-disabled','true');
        if ($mic.length) $mic.prop('disabled', true).attr('aria-disabled','true');
        if (hideAll) {
          // Comportamiento hidden: ocultar widget completo
          $root.hide();
        }
      }

    function appendUser($messages, text) {
      $messages.append('<div class="message user-message">' + escapeHtml(text) + '</div>');
      scrollToBottom($messages);
    }

    function appendBot($messages, text) {
      // Confía en HTML sanitizado en servidor (wp_kses) → permite <a>, <strong>, etc.
      var html = '<div class="aichat-msg aichat-bot">'+ String(text) +'</div>';
       $messages.append(html);
     }

    function appendError($messages, text) {
      $messages.append('<div class="message bot-message error">' + escapeHtml(text) + '</div>');
      scrollToBottom($messages);
    }

    function appendTyping($messages) {
      var $el = $('<div class="message bot-message typing"><span class="dots">•••</span></div>');
      $messages.append($el);
      scrollToBottom($messages);
      return $el;
    }

    function scrollToBottom($messages) {
      var el = $messages.get(0);
      if (el) el.scrollTop = el.scrollHeight;
    }

    function lockInputs($input, $sendBtn, lock) {
      $input.prop('disabled', !!lock);
      $sendBtn.prop('disabled', !!lock);
    }

    function escapeHtml(str) {
      return $('<div>').text(String(str)).html();
    }

    // ---------- helpers de normalización ----------
    function normPos(v){
      if (!v) return 'bottom-right';
      // abreviaturas
      if (v === 'tr') return 'top-right';
      if (v === 'tl') return 'top-left';
      if (v === 'br') return 'bottom-right';
      if (v === 'bl') return 'bottom-left';
      // sinónimos
      var map = {
        'top-right'    : ['top-right','derecha-superior','superior-derecha'],
        'top-left'     : ['top-left','izquierda-superior','superior-izquierda'],
        'bottom-right' : ['bottom-right','derecha-inferior','inferior-derecha'],
        'bottom-left'  : ['bottom-left','izquierda-inferior','inferior-izquierda']
      };
      for (var k in map){ if (map[k].indexOf(v) >= 0) return k; }
      return 'bottom-right';
    }

    // Drag helper
    function makeDraggable($root, $handle){
      var dragging = false, sx=0, sy=0, sl=0, st=0;
      var $doc = $(document);
      $handle.css('cursor','move');
      $handle.on('mousedown.aichat touchstart.aichat', function(ev){
        var e = ev.type.startsWith('touch') ? ev.originalEvent.touches[0] : ev;
        dragging = true;
        $root.addClass('dragging');
        // fijar a top/left absolutos (position:fixed ya viene por CSS)
        var rect = $root.get(0).getBoundingClientRect();
        sl = rect.left; st = rect.top;
        sx = e.clientX; sy = e.clientY;
        ev.preventDefault();
      });
      $doc.on('mousemove.aichat touchmove.aichat', function(ev){
        if (!dragging) return;
        var e = ev.type.startsWith('touch') ? ev.originalEvent.touches[0] : ev;
        var dx = e.clientX - sx, dy = e.clientY - sy;
        var nl = sl + dx, nt = st + dy;
        // límites viewport
        var vw = window.innerWidth, vh = window.innerHeight;
        var w = $root.outerWidth(), h = $root.outerHeight();
        nl = Math.max(0, Math.min(vw - w, nl));
        nt = Math.max(0, Math.min(vh - h, nt));
        $root.css({ left: nl+'px', top: nt+'px', right: 'auto', bottom: 'auto' });
      });
      $doc.on('mouseup.aichat touchend.aichat touchcancel.aichat', function(){
        if (!dragging) return;
        dragging = false;
        $root.removeClass('dragging');
      });
    }
    // Carga historial del servidor y lo pinta
    function loadHistory($messages, botSlug, sessionId){
      $.ajax({
        url: AIChatVars.ajax_url,
        method: 'POST',
        data: { action: 'aichat_get_history', nonce: AIChatVars.nonce, bot_slug: botSlug, session_id: sessionId, limit: 50 }
      }).done(function(res){
        if (!res || !res.success || !Array.isArray(res.data.items)) return;
        res.data.items.forEach(function(it){
          appendUser($messages, String(it.q||''));    // usuario → escapado
          appendBot($messages, String(it.a||''));     // bot → HTML sanitizado
        });
        scrollToBottom($messages);
      });
    }

    // Cookie helpers
    function getOrCreateSessionId(){
      var key='aichat_sid', m=/(?:^|;)\s*aichat_sid=([^;]+)/.exec(document.cookie);
      if (m && m[1]) return decodeURIComponent(m[1]);
      var sid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('sid-' + Math.random().toString(36).slice(2) + Date.now());
      var exp = 60*60*24*30; // 30 días
      document.cookie = key + '=' + encodeURIComponent(sid) + '; Max-Age=' + exp + '; Path=/; SameSite=Lax';
      return sid;
    }

  });

})(jQuery);
