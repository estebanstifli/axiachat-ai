<?php
/**
 * Admin Page: Chatbots (tabs + panel; form se renderiza desde bots.js)
 */

if ( ! defined('ABSPATH') ) { exit; }

// Nota: los estilos/JS necesarios (bootstrap icons + bots.js) se encolan desde el hook en aichat.php.

function aichat_bots_settings_page() {
  // Carga opciones de contextos (para el selector de embeddings)
  global $wpdb;
  $ctx_table = $wpdb->prefix . 'aichat_contexts';
  $contexts  = $wpdb->get_results("SELECT id, name FROM $ctx_table ORDER BY id DESC", ARRAY_A);
  $embedding_options = array_map(function($r){
      return ['id' => (int)$r['id'], 'text' => $r['name']];
  }, $contexts);
  array_unshift($embedding_options, ['id'=>0,'text'=>'— None —']);

  // Prepara objeto JS (por si no has hecho wp_localize_script encolando bots.js)
  // Si ya fueron localizadas encolando el script (aichat.php) no sobreescribir; sólo fallback.
  $instruction_templates = function_exists('aichat_get_chatbot_templates') ? aichat_get_chatbot_templates() : [];
  if ( function_exists('aichat_log_debug') ) {
    aichat_log_debug('Bots page render templates', [ 'count' => is_array($instruction_templates)?count($instruction_templates):0 ]);
  }
  $ajax_boot = [
    'ajax_url'              => admin_url('admin-ajax.php'),
    'nonce'                 => wp_create_nonce('aichat_bots_nonce'),
    'embedding_options'     => $embedding_options,
    'instruction_templates' => $instruction_templates,
  ];
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Chatbots', 'aichat'); ?></h1>

    <style>
      :root{ --aichat-blue:#1a73e8; }
      /* Layout dos columnas: editor (izq) + preview (dcha) */
      .aichat-layout{ display:flex; gap:24px; align-items:flex-start; }
      .aichat-bot-wrapper{ width:50%; max-width:900px; margin:8px 0 24px 0; }
      @media (max-width: 991.98px){ .aichat-layout{ flex-direction:column; } .aichat-bot-wrapper{ width:100%; max-width:none; } }
      .aichat-preview-wrapper{ flex:1; min-width:320px; }
      .aichat-preview-card{ background:#fff; border:1px solid #e0e0e0; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,.06); }
      .aichat-preview-head{ padding:8px 12px; font-weight:600; border-bottom:1px solid #e0e0e0; background:#f8f9fa; border-top-left-radius:10px; border-top-right-radius:10px; }
      .aichat-preview-body{ height:640px; } /* alto del iframe */
      .aichat-preview-body iframe{ width:100%; height:100%; border:0; border-bottom-left-radius:10px; border-bottom-right-radius:10px; background:#fff; }

      /* Barra superior: prev [tabs rail] next + [+] */
      .aichat-tabs{ display:flex; align-items:flex-end; gap:8px; }

      .aichat-sbtn{
        display:inline-flex; align-items:center; justify-content:center;
        width:38px; height:38px; background:#fff; color:var(--aichat-blue);
        border:2px solid var(--aichat-blue); border-radius:10px;
        box-shadow:0 2px 6px rgba(0,0,0,.06); cursor:pointer; user-select:none;
      }
      .aichat-sbtn[disabled]{ opacity:.4; cursor:default; }
      .aichat-sbtn .bi{ font-size:1rem; }

      .aichat-tab-strip{
        position:relative; flex:1; min-width:0; display:flex; gap:8px;
        overflow-x:auto; overflow-y:hidden; scrollbar-width:none;
      }
      .aichat-tab-strip::-webkit-scrollbar{ display:none; }

      .aichat-tab{
        display:inline-flex; align-items:center; gap:.5rem;
        padding:8px 12px; background:#fff; color:var(--aichat-blue);
        font-weight:600; border:2px solid var(--aichat-blue);
        border-bottom:none; border-top-left-radius:10px; border-top-right-radius:10px;
        box-shadow:0 2px 6px rgba(0,0,0,.06); cursor:pointer; user-select:none;
        line-height:1.1; white-space:nowrap;
      }
      .aichat-tab:hover{ background:#f7fbff; }
      .aichat-tab.active{ background:var(--aichat-blue); color:#fff; }

      .aichat-new-btn{
        display:inline-flex; align-items:center; justify-content:center;
        padding:8px 12px; min-width:44px; height:38px;
        background:#fff; color:var(--aichat-blue);
        border:2px solid var(--aichat-blue); border-radius:10px;
        box-shadow:0 2px 6px rgba(0,0,0,.06); cursor:pointer; user-select:none;
      }
      .aichat-new-btn:hover{ background:#f7fbff; }
      .aichat-new-btn .bi{ font-size:1rem; }

      /* Panel con borde azul y esquinas inferiores redondeadas */
      .aichat-panel{
        width:100%; background:var(--aichat-blue);
        border:4px solid var(--aichat-blue);
        border-bottom-left-radius:10px; border-bottom-right-radius:10px;
        box-shadow:0 4px 8px rgba(0,0,0,.08);
      }
      .aichat-panel-body{ background:#fff; margin:8px; border-radius:8px; padding:10px; }

      /* Acordeón compacto */
      .aichat-accordion .accordion-item{ border:1px solid #e9ecef; border-radius:8px; overflow:hidden; }
      .aichat-accordion .accordion-item + .accordion-item{ margin-top:10px; }
      .aichat-accordion .accordion-button{ padding:.6rem .9rem; font-weight:600; }
      .aichat-accordion .accordion-body{ padding:.9rem; }

      .form-text-muted{ color:#6c757d; font-size:12px; }
      .aichat-inline{ display:flex; gap:12px; flex-wrap:wrap; }
      .aichat-inline .form-floating{ flex:1 1 220px; }

      /* grid de avatares */
      .aichat-avatars{ display:none; gap:10px; flex-wrap:wrap; margin-top:8px; }
      .aichat-avatar{
        width:50px; height:50px;                 /* 40px imagen + 2*4 padding + 2*1 borde */
        padding:4px; box-sizing:border-box;      /* separación entre imagen y borde */
        display:flex; align-items:center; justify-content:center;
        border:1px solid #e0e0e0; border-radius:8px; cursor:pointer; background:#fafafa;
      }
      .aichat-avatar img{
        width:100%; height:100%; display:block;  /* imagen ocupa el área interior */
        border-radius:6px;                        /* opcional: suaviza esquinas del contenido */
        object-fit:cover;
      }
      .aichat-avatar.active{ outline:2px solid rgba(25, 94, 197, 0.96); background:#fff; }

      .aichat-shortcode{ display:inline-flex; align-items:center; gap:6px; background:#f8f9fa;
        border:1px solid #e0e0e0; border-radius:6px; padding:6px 8px; font-family:Menlo,Consolas,monospace; font-size:12px; }
      .aichat-shortcode .copy-btn{ border:none; background:transparent; cursor:pointer; color:#0d6efd; }

     /* Fix: alinear checkboxes y radios (neutraliza floats de WP Admin) */
     #aichat-panel .form-check{
       display: inline-flex;
       align-items: center;
       gap: 8px;
     }
     #aichat-panel .form-check-input{
       float: none !important;
       margin: 0 8px 0 0;
       vertical-align: middle;
     }
     #aichat-panel .form-check-label{
       margin: 0;
     }
     /* Radios sueltos generados como <label class="me-3"><input class="form-check-input"> Texto */
     #aichat-panel label.me-3{
       display: inline-flex;
       align-items: center;
       gap: 8px;
       margin-right: .75rem; /* equivalente a me-3 si no hay Bootstrap */
     }
     #aichat-panel label.me-3 > .form-check-input{
       float: none !important;
       margin: 0 8px 0 0;
       vertical-align: middle;
     }
     /* Normaliza cualquier input por si WP aplica floats globales */
     #aichat-panel input[type="checkbox"], 
     #aichat-panel input[type="radio"]{
       float: none !important;
       margin: 0 8px 0 0;
       vertical-align: middle;
       position: relative;
       top: 0;
     }
     
    .aichat-model-token-info{
      font-size:11px;
      margin-top:4px;
      color:#555;
      opacity:0.95;
    }
    .aichat-model-token-info .recommended{
      color:#1a73e8;
      font-weight:600;
    }
    </style>

    <div class="aichat-layout">
      <div class="aichat-bot-wrapper">
        <div class="aichat-tabs">
          <button type="button" id="aichat-tabs-prev" class="aichat-sbtn" aria-label="<?php esc_attr_e('Scroll left','aichat'); ?>" style="display:none">
            <i class="bi bi-chevron-left"></i>
          </button>

          <div id="aichat-tab-strip" class="aichat-tab-strip" role="tablist" aria-label="<?php esc_attr_e('Chatbot tabs','aichat'); ?>"></div>

          <button type="button" id="aichat-tabs-next" class="aichat-sbtn" aria-label="<?php esc_attr_e('Scroll right','aichat'); ?>" style="display:none">
            <i class="bi bi-chevron-right"></i>
          </button>

          <button type="button" id="aichat-add-bot" class="aichat-new-btn" aria-label="<?php esc_attr_e('Create new chatbot','aichat'); ?>">
            <i class="bi bi-plus-lg"></i>
          </button>
        </div>

        <div class="aichat-panel">
          <div class="aichat-panel-body" id="aichat-panel">
            <!-- bots.js inyecta el formulario aquí -->
          </div>
        </div>
      </div>

      <div class="aichat-preview-wrapper">
        <div class="aichat-preview-card">
          <div class="aichat-preview-head">
            Preview
          </div>
          <div class="aichat-preview-body">
            <iframe id="aichat-preview" title="AIChat Preview" loading="lazy"></iframe>
          </div>
        </div>
      </div>
    </div>

    <script>
      window.aichat_bots_ajax = window.aichat_bots_ajax || <?php echo wp_json_encode($ajax_boot); ?>;
      // Añade la URL base para el iframe de preview
      window.aichat_bots_ajax.preview_url = <?php echo wp_json_encode( home_url('/?aichat_preview=1&bot=') ); ?>;
      console.log('[AIChat Bots] boot', window.aichat_bots_ajax);
    </script>
  </div>
<?php }
