<?php
/**
 * Plugin Name:       AxiaChat AI
 * Plugin URI:        https://wpbotwriter.com/axiachat-ai
 * Description:       A customizable AI chatbot for WordPress with contextual embeddings, multi‑provider support and upcoming action rules.
 * Version:           1.1.4
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            estebandezafra
 * Author URI:        https://wpbotwriter.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       axiachat-ai
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Definir constantes del plugin
define( 'AICHAT_VERSION', '1.1.4' );
define( 'AICHAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AICHAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define('AICHAT_DEBUG', true);

// Nota: Eliminado load_plugin_textdomain manual.
// Para WordPress.org, las traducciones de 'axiachat-ai' se cargarán automáticamente
// desde wp-content/languages/plugins/ según el encabezado Text Domain.

// Debug helper: log only if AICHAT_DEBUG is defined and true.
if ( ! function_exists( 'aichat_log_debug' ) ) {
  /**
   * Conditional debug logger.
   * Adds unified prefix and safely encodes context.
   *
   * @param string $message  Short message (without prefix).
   * @param array  $context  Optional associative array (scalars preferred).
   */
  function aichat_log_debug( $message, array $context = [] ) {
    if ( ! ( defined( 'AICHAT_DEBUG' ) && AICHAT_DEBUG ) ) {
      return;
    }
    if ( ! empty( $context ) ) {
      $safe = [];
      foreach ( $context as $k => $v ) {
        if ( is_scalar( $v ) || $v === null ) {
          $safe[ $k ] = $v;
        } elseif ( $v instanceof WP_Error ) {
          $safe[ $k ] = 'WP_Error: ' . $v->get_error_message();
        } else {
          $safe[ $k ] = is_object( $v ) ? get_class( $v ) : gettype( $v );
        }
      }
      $json = wp_json_encode( $safe );
      if ( $json ) {
        $message .= ' | ' . $json;
      }
    }
    error_log( '[AIChat] ' . $message );
  }
}


// Include Composer autoloader
require_once AICHAT_PLUGIN_DIR . 'vendor/autoload.php';
//use Smalot\PdfParser\Parser;


require_once AICHAT_PLUGIN_DIR . 'includes/shortcode.php'; 

// Incluir archivos de clases principales
require_once AICHAT_PLUGIN_DIR . 'includes/class-aichat-core.php';
require_once AICHAT_PLUGIN_DIR . 'includes/class-aichat-ajax.php';
require_once AICHAT_PLUGIN_DIR . 'includes/settings.php';


require_once AICHAT_PLUGIN_DIR . 'includes/contexto-functions.php'; // Nuevo archivo para funciones de contexto

require_once AICHAT_PLUGIN_DIR . 'includes/contexto-settings.php'; // 1ª pestaña de contexto
require_once AICHAT_PLUGIN_DIR . 'includes/contexto-ajax-settings.php'; // 1ª pestaña de contexto (AJAX)

require_once AICHAT_PLUGIN_DIR . 'includes/contexto-create.php'; // 2ª pestaña de contexto (crear)
require_once AICHAT_PLUGIN_DIR . 'includes/contexto-ajax-create.php'; // 2ª pestaña de contexto (crear) AJAX

require_once AICHAT_PLUGIN_DIR . 'includes/contexto-pdf-template.php'; // 3ª pestaña de contexto (PDF)
require_once AICHAT_PLUGIN_DIR . 'includes/contexto-pdf-ajax.php'; // 3ª pestaña de contexto (PDF) AJAX

require_once AICHAT_PLUGIN_DIR . 'includes/aichat-cron.php'; // Nuevo archivo para tareas programadas

require_once AICHAT_PLUGIN_DIR . 'includes/bots.php'; // Nuevo archivo para la lógica de los bots
require_once AICHAT_PLUGIN_DIR . 'includes/bots_ajax.php'; // Nuevo archivo para la lógica AJAX de los bots

require_once AICHAT_PLUGIN_DIR . 'includes/moderation.php';
// Usage / cost tracking (added 1.2.0 dev)
if ( file_exists( AICHAT_PLUGIN_DIR . 'includes/usage-functions.php') ) {
  require_once AICHAT_PLUGIN_DIR . 'includes/usage-functions.php';
}
if ( file_exists( AICHAT_PLUGIN_DIR . 'includes/usage-ajax.php') ) {
  require_once AICHAT_PLUGIN_DIR . 'includes/usage-ajax.php';
}
if ( file_exists( AICHAT_PLUGIN_DIR . 'includes/usage.php') ) {
  require_once AICHAT_PLUGIN_DIR . 'includes/usage.php';
}

// Páginas de logs (listado y detalle)
require_once AICHAT_PLUGIN_DIR . 'includes/logs.php';
require_once AICHAT_PLUGIN_DIR . 'includes/logs-detail.php';

//Pagina de templates prompt
require_once AICHAT_PLUGIN_DIR . 'includes/templates-prompt.php';

// (Easy Config) include file if exists (will be created later)
if ( file_exists( AICHAT_PLUGIN_DIR . 'includes/easy-config.php' ) ) {
  require_once AICHAT_PLUGIN_DIR . 'includes/easy-config.php';
}








// Instanciar las clases principales (singleton) evitando duplicados
if ( class_exists('AIChat_Core') && method_exists('AIChat_Core','instance') ) {
  AIChat_Core::instance();
}
if ( class_exists('AIChat_Ajax') && method_exists('AIChat_Ajax','instance') ) {
  AIChat_Ajax::instance();
}

// Hook de activación del plugin
register_activation_hook( __FILE__, 'aichat_activation' );
function aichat_activation() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aichat_conversations';
    $chunks_table = $wpdb->prefix . 'aichat_chunks';
    $charset_collate = $wpdb->get_charset_collate();
    $contexts_table = $wpdb->prefix . 'aichat_contexts';

    // Crear tabla wp_aichat_contexts
    $sql_contexts = "CREATE TABLE IF NOT EXISTS $contexts_table (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    context_type ENUM('local', 'remoto') NOT NULL DEFAULT 'local',
                    remote_type VARCHAR(50) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    remote_api_key VARCHAR(255) DEFAULT NULL,
                    remote_endpoint VARCHAR(255) DEFAULT NULL,
                    processing_status VARCHAR(20) DEFAULT 'pending',
                    processing_progress INT DEFAULT 0,
          items_to_process LONGTEXT NULL,
          /* === AutoSync columnas (nuevas) === */
          autosync TINYINT(1) NOT NULL DEFAULT 0,
          autosync_mode ENUM('updates','updates_and_new') NOT NULL DEFAULT 'updates',
          autosync_post_types VARCHAR(255) DEFAULT NULL,
          autosync_last_scan DATETIME NULL
                ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_contexts );

    // Crear tabla wp_aichat_conversations
  $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        session_id VARCHAR(64) NOT NULL DEFAULT '',
        bot_slug VARCHAR(100) NOT NULL DEFAULT '',
    model VARCHAR(100) NULL,
    provider VARCHAR(40) NULL,
        page_id BIGINT(20) UNSIGNED DEFAULT 0,
    ip_address VARBINARY(16) NULL,
        message LONGTEXT NOT NULL,
        response LONGTEXT NOT NULL,
    prompt_tokens INT UNSIGNED NULL,
    completion_tokens INT UNSIGNED NULL,
    total_tokens INT UNSIGNED NULL,
    cost_micros BIGINT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_session_bot (session_id, bot_slug, id),
        KEY idx_user (user_id),
    KEY idx_page (page_id),
    KEY idx_model (model),
    KEY idx_created_at (created_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

  // Crear tabla wp_aichat_chunks (añadido updated_at y UNIQUE(post_id,id_context) para ON DUPLICATE KEY)
  $chunks_sql = "CREATE TABLE IF NOT EXISTS $chunks_table (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_context BIGINT UNSIGNED DEFAULT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT NOT NULL DEFAULT 0,
    type VARCHAR(20),
    title VARCHAR(255),
    content MEDIUMTEXT NOT NULL,
    embedding LONGTEXT,
    tokens INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uniq_post_ctx_chunk (post_id, id_context, chunk_index),
    KEY idx_context (id_context),
    KEY idx_post_context (post_id,id_context),
    CONSTRAINT fk_chunks_context FOREIGN KEY (id_context) REFERENCES $contexts_table(id) ON DELETE SET NULL
  ) $charset_collate;";

    dbDelta( $chunks_sql );

    // Tabla agregada diaria de uso/coste
    $usage_daily = $wpdb->prefix.'aichat_usage_daily';
    $usage_sql = "CREATE TABLE IF NOT EXISTS $usage_daily (
      date DATE NOT NULL,
      provider VARCHAR(40) NOT NULL DEFAULT 'openai',
      model VARCHAR(100) NOT NULL DEFAULT '',
      prompt_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
      completion_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
      total_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
      cost_micros BIGINT UNSIGNED NOT NULL DEFAULT 0,
      conversations BIGINT UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY(date, provider, model)
    ) $charset_collate;";
    dbDelta($usage_sql);

    // tabla de bots
    aichat_bots_maybe_create();

    // Insertar bot por defecto SOLO si no existe ninguno
    if ( ! get_option('aichat_default_bot_seeded') ) {
        aichat_bots_insert_default();
    } else {
        // Marcador existe: aún así validar que la tabla no esté vacía (caso limpieza manual)
        $table = $wpdb->prefix.'aichat_bots';
        $rows  = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($rows === 0) {
            delete_option('aichat_default_bot_seeded');
            aichat_bots_insert_default();
        }
    }

    // Opciones iniciales (no tocar si ya existen)
    add_option( 'aichat_openai_api_key', '' );
    add_option( 'aichat_chat_color', '#0073aa' );
    add_option( 'aichat_position', 'bottom-right' );
    // add_option('aichat_rag_enabled', false); // (deprecated si ya lo eliminaste)

  // Señal para redirigir a Easy Config tras activación (si no había bots previos)
  if ( ! get_option( 'aichat_easy_config_completed' ) ) {
    add_option( 'aichat_easy_config_do_redirect', 1 );
  }
}

// Upgrade silencioso para añadir columnas si plugin ya estaba activado previamente.
add_action('plugins_loaded', function(){
  global $wpdb; $t = $wpdb->prefix.'aichat_conversations';
  $cols = $wpdb->get_col("SHOW COLUMNS FROM $t",0);
  if($cols){
    $alter = [];
    if(!in_array('model',$cols)) $alter[] = 'ADD COLUMN model VARCHAR(100) NULL AFTER bot_slug';
    if(!in_array('provider',$cols)) $alter[] = 'ADD COLUMN provider VARCHAR(40) NULL AFTER model';
    if(!in_array('prompt_tokens',$cols)) $alter[] = 'ADD COLUMN prompt_tokens INT UNSIGNED NULL AFTER response';
    if(!in_array('completion_tokens',$cols)) $alter[] = 'ADD COLUMN completion_tokens INT UNSIGNED NULL AFTER prompt_tokens';
    if(!in_array('total_tokens',$cols)) $alter[] = 'ADD COLUMN total_tokens INT UNSIGNED NULL AFTER completion_tokens';
    if(!in_array('cost_micros',$cols)) $alter[] = 'ADD COLUMN cost_micros BIGINT NULL AFTER total_tokens';
    if($alter){
      $sql = 'ALTER TABLE '.$t.' '.implode(', ',$alter);
      $wpdb->query($sql);
    }
  }
  // Ensure daily usage table exists
  $usage_daily = $wpdb->prefix.'aichat_usage_daily';
  $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s", $usage_daily));
  if(!$exists){
    $charset = $wpdb->get_charset_collate();
    $wpdb->query("CREATE TABLE $usage_daily (
      date DATE NOT NULL,
      provider VARCHAR(40) NOT NULL DEFAULT 'openai',
      model VARCHAR(100) NOT NULL DEFAULT '',
      prompt_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
      completion_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
      total_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
      cost_micros BIGINT UNSIGNED NOT NULL DEFAULT 0,
      conversations BIGINT UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY(date, provider, model)
    ) $charset");
  }
});


function aichat_bots_maybe_create(){
  // Versión simplificada para el primer release: un único CREATE con todas las columnas.
  global $wpdb;
  $t = aichat_bots_table();
  $charset = $wpdb->get_charset_collate();
  require_once ABSPATH.'wp-admin/includes/upgrade.php';

  // Nota: evitamos 'IF NOT EXISTS' para que dbDelta pueda comparar y ajustar correctamente.
  $sql = "CREATE TABLE $t (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL DEFAULT '',
    slug VARCHAR(100) NOT NULL,
    type ENUM('text','voice_text') NOT NULL DEFAULT 'text',
    instructions LONGTEXT NULL,
    provider VARCHAR(32) NOT NULL DEFAULT 'openai',
  model VARCHAR(64) NOT NULL DEFAULT 'gpt-4o',
    temperature DECIMAL(3,2) NOT NULL DEFAULT 0.70,
    max_tokens INT NOT NULL DEFAULT 2048,
    reasoning ENUM('off','fast','accurate') NOT NULL DEFAULT 'off',
    verbosity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    context_mode ENUM('embeddings','page','none') NOT NULL DEFAULT 'embeddings',
    context_id BIGINT UNSIGNED NULL,
    input_max_length INT NOT NULL DEFAULT 512,
    max_messages INT NOT NULL DEFAULT 20,
    context_max_length INT NOT NULL DEFAULT 4096,
    ui_color VARCHAR(7) NOT NULL DEFAULT '#1a73e8',
    ui_position ENUM('br','bl','tr','tl') NOT NULL DEFAULT 'br',
    ui_avatar_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ui_avatar_key VARCHAR(32) DEFAULT NULL,
    ui_icon_url VARCHAR(255) DEFAULT NULL,
    ui_start_sentence VARCHAR(255) DEFAULT NULL,
    ui_placeholder VARCHAR(255) NOT NULL DEFAULT 'Write your question...',
    ui_button_send VARCHAR(64) NOT NULL DEFAULT 'Send',
    ui_closable TINYINT(1) NOT NULL DEFAULT 1,
    ui_minimizable TINYINT(1) NOT NULL DEFAULT 1,
    ui_draggable TINYINT(1) NOT NULL DEFAULT 1,
    ui_minimized_default TINYINT(1) NOT NULL DEFAULT 0,
  ui_superminimized_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY provider (provider),
    KEY context_id (context_id)
  ) $charset;";

  dbDelta($sql); // dbDelta hará los ajustes necesarios si ya existe.
}

// Upgrade routine for adding chunk_index if missing (run on admin_init lightweight)
add_action('admin_init', function(){
  global $wpdb; $table = $wpdb->prefix.'aichat_chunks';
  $cols = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'chunk_index'");
  if (empty($cols)) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN chunk_index INT NOT NULL DEFAULT 0 AFTER post_id");
  }
  // Adjust unique key if old one exists
  $indexes = $wpdb->get_results("SHOW INDEX FROM $table");
  $has_old_unique = false; $has_new_unique = false;
  foreach($indexes as $ix){
    if ($ix->Key_name === 'unique_post_context') $has_old_unique = true;
    if ($ix->Key_name === 'uniq_post_ctx_chunk') $has_new_unique = true;
  }
  if ($has_old_unique && ! $has_new_unique) {
    $wpdb->query("ALTER TABLE $table DROP INDEX unique_post_context");
    $wpdb->query("ALTER TABLE $table ADD UNIQUE KEY uniq_post_ctx_chunk (post_id,id_context,chunk_index)");
  }
});

// Hook de desactivación (vacío para no perder datos)
register_deactivation_hook( __FILE__, 'aichat_deactivation' );
function aichat_deactivation() {
    // No eliminamos datos en la desactivación
}

// Hook de desinstalación
register_uninstall_hook( __FILE__, 'aichat_uninstall' );
function aichat_uninstall() {
    // Eliminar solo las opciones, no la tabla
    delete_option( 'aichat_openai_api_key' );
    delete_option( 'aichat_chat_color' );
    delete_option( 'aichat_position' );
}

// Agregar menús y páginas
add_action( 'admin_menu', 'aichat_admin_menu' );
function aichat_admin_menu() {
  add_menu_page(
	__( 'AxiaChat AI Settings', 'axiachat-ai' ), // Título de la página
        'AxiaChat AI',          // Título del menú, no tiene sentido traducirlo
        'manage_options',                   // Capacidad requerida
        'aichat-settings',                  // Slug del menú
        'aichat_settings_page',             // Función de callback
        'dashicons-format-chat',            // Icono del menú
        80                                  // Posición en el menú
    );

  // Submenú Settings (primero) - evita que WP genere uno por defecto con el título original
  add_submenu_page(
    'aichat-settings',
  __( 'Settings', 'axiachat-ai' ),
  __( 'Settings', 'axiachat-ai' ),
    'manage_options',
    'aichat-settings',
    'aichat_settings_page'
  );

   
    // Submenú para Contexto 
    add_submenu_page(
        'aichat-settings', // Parent slug
  __( 'Context', 'axiachat-ai' ), // Título de la página
  __( 'Context', 'axiachat-ai' ), // Título del submenú
        'manage_options', // Capacidad
        'aichat-contexto-settings', // Slug
        'aichat_contexto_settings_page' // Callback for the page
    );

    // Submenú para bots
    add_submenu_page(
        'aichat-settings', // Parent slug
  __( 'Bots', 'axiachat-ai' ), // Título de la página
  __( 'Bots', 'axiachat-ai' ), // Título del submenú
        'manage_options', // Capacidad
        'aichat-bots-settings', // Slug
        'aichat_bots_settings_page' // Callback for the page
    );

  // Easy Config (wizard) – aparece solo si no se ha marcado como completado
  $show_easy = ! get_option('aichat_easy_config_completed');
  if ( $show_easy ) {
    add_submenu_page(
      'aichat-settings',
  __( 'Easy Config', 'axiachat-ai' ),
  __( 'Easy Config', 'axiachat-ai' ),
      'manage_options',
      'aichat-easy-config',
      function(){
        if ( function_exists('aichat_easy_config_page') ) {
          aichat_easy_config_page();
        } else {
          echo '<div class="wrap"><h1>Easy Config</h1><p>Loading...</p></div>';
        }
      }
    );
  }

  // Submenú para logs (listado principal)
  add_submenu_page(
    'aichat-settings',
  __( 'Logs', 'axiachat-ai' ),
  __( 'Logs', 'axiachat-ai' ),
    'manage_options',
    'aichat-logs',
    'aichat_logs_page'
  );



    add_submenu_page(
        null, // Sin menú padre
  __('Create Context', 'axiachat-ai'), // Título de la página
        '', // Título del menú (vacío)
        'manage_options', // Capacidad
        'aichat-contexto-create', // Slug de la página
        'aichat_contexto_create_page' // Función de callback
    );

    add_submenu_page(
        null, // Sin menú padre
  __('Import PDF/Data', 'axiachat-ai'), // Título de la página
        '', // Título del menú (vacío)
        'manage_options', // Capacidad
        'aichat-contexto-pdf', // Slug de la página
        'aichat_contexto_pdf_page' // Función de callback
    );

  add_submenu_page(
    'aichat-settings',
    'Usage / Cost','Usage / Cost','manage_options','aichat-usage','aichat_usage_admin_page',50
  );

  
  // Página oculta para detalle de conversación
  add_submenu_page(
    null,
  __( 'Conversation Detail', 'axiachat-ai' ),
    '',
    'manage_options',
    'aichat-logs-detail',
    'aichat_logs_detail_page'
  );

  add_action('admin_enqueue_scripts', function($hook){
    if ( ! isset($_GET['page']) ) return;
    $page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
  // Incluir también la página principal de ajustes para usar Bootstrap en el rediseño
  $needs_bootstrap = in_array( $page, [ 'aichat-settings','aichat-bots-settings','aichat-logs','aichat-logs-detail','aichat-contexto-settings','aichat-contexto-create','aichat-contexto-pdf' ], true );
  // Añadir easy config a la lista que necesita bootstrap (reutilizamos estilos)
  if ( $page === 'aichat-easy-config' ) {
    $needs_bootstrap = true;
  }
    if ( ! $needs_bootstrap ) return;

    // Registrar Bootstrap y Bootstrap Icons si no están
    // Enqueue wizard assets
    if ( $page === 'aichat-easy-config' ) {
      // CSS propio (se creará posteriormente)
      wp_enqueue_style('aichat-easy-config', AICHAT_PLUGIN_URL.'assets/css/easy-config.css', ['aichat-admin'], AICHAT_VERSION);
      wp_enqueue_script('aichat-easy-config', AICHAT_PLUGIN_URL.'assets/js/easy-config.js', ['jquery'], AICHAT_VERSION, true);
      wp_localize_script('aichat-easy-config','aichat_easycfg_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('aichat_easycfg'),
        'i18n' => [
          'discovering' => __('Scanning site content...', 'axiachat-ai'),
          'indexing'    => __('Indexing content...', 'axiachat-ai'),
          'creating_bot'=> __('Creating bot...', 'axiachat-ai'),
          'done'        => __('Completed', 'axiachat-ai'),
          'error'       => __('Error', 'axiachat-ai'),
        ]
      ]);
    }
    if ( ! wp_style_is( 'aichat-bootstrap', 'registered' ) ) {
      wp_register_style(
        'aichat-bootstrap',
        AICHAT_PLUGIN_URL . 'assets/vendor/bootstrap/css/bootstrap.min.css',
        [],
        '5.3.0'
      );
    }
    if ( ! wp_script_is( 'aichat-bootstrap', 'registered' ) ) {
      wp_register_script(
        'aichat-bootstrap',
        AICHAT_PLUGIN_URL . 'assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
        [ 'jquery' ],
        '5.3.0',
        true
      );
    }
    if ( ! wp_style_is( 'aichat-bootstrap-icons', 'registered' ) ) {
      wp_register_style(
        'aichat-bootstrap-icons',
        AICHAT_PLUGIN_URL . 'assets/vendor/bootstrap-icons/font/bootstrap-icons.css',
        [],
        '1.11.3'
      );
    }

    // Encolar comunes
    wp_enqueue_style('aichat-bootstrap');
    wp_enqueue_style('aichat-bootstrap-icons');
    // Nuevo: hoja de estilos admin consolidada
  wp_enqueue_style('aichat-admin', AICHAT_PLUGIN_URL.'assets/css/aichat-admin.css', ['aichat-bootstrap'], AICHAT_VERSION);    
    wp_enqueue_script('aichat-bootstrap');

    // Script específico de la página de ajustes (toggle mostrar/ocultar API keys)
    if ( $page === 'aichat-settings' && ! wp_script_is('aichat-settings-js','enqueued') ) {
      wp_enqueue_script(
        'aichat-settings-js',
        AICHAT_PLUGIN_URL . 'assets/js/settings.js',
        [],
        AICHAT_VERSION,
        true
      );
    }

    // Lógica específica para página de bots
    if ( $page === 'aichat-bots-settings' ) {
      wp_enqueue_script('aichat-bots-js', AICHAT_PLUGIN_URL.'assets/js/bots.js', ['jquery'], AICHAT_VERSION, true);

      global $wpdb;
      $contexts_table = $wpdb->prefix . 'aichat_contexts';
      $rows = $wpdb->get_results(
        "SELECT id, name FROM {$contexts_table} WHERE processing_status = 'completed' ORDER BY name ASC",
        ARRAY_A
      );
      $embedding_options = [];
      if ( is_array($rows) ) {
        foreach ( $rows as $r ) {
          $embedding_options[] = [ 'id'=>(int)$r['id'], 'text'=>$r['name'] ];
        }
      }
      array_unshift($embedding_options, ['id'=>0,'text'=>'— None —']);

      // Obtener plantillas de instrucciones
      if ( function_exists('aichat_get_chatbot_templates') ) {
        $instruction_templates = aichat_get_chatbot_templates();
        aichat_log_debug('Localizing instruction templates', [ 'count' => is_array($instruction_templates) ? count($instruction_templates) : 0 ]);
      } else {
        $instruction_templates = [];
        aichat_log_debug('Instruction templates function missing');
      }

      wp_localize_script('aichat-bots-js', 'aichat_bots_ajax', [
        'ajax_url'              => admin_url('admin-ajax.php'),
        'nonce'                 => wp_create_nonce('aichat_bots_nonce'),
        'embedding_options'     => $embedding_options,
        'instruction_templates' => $instruction_templates,
      ]);
    }

    // Añadir cadenas para test semántico (context settings)
    if ( $page === 'aichat-contexto-settings' || $page === 'aichat-settings' ) {
      if ( wp_script_is('aichat-settings-js','enqueued') ) {
        wp_localize_script('aichat-settings-js','aichat_settings_ajax', [
          'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('aichat_nonce'),
            'searching' => __('Searching embeddings...', 'axiachat-ai'),
            'no_results' => __('No results found for that query.', 'axiachat-ai'),
            'error_generic' => __('Unexpected error performing search.', 'axiachat-ai'),
        ]);
      }
      // NUEVO: Encolar JS específico para la pantalla de Context Settings si no se había encolado
      if ( $page === 'aichat-contexto-settings' && ! wp_script_is('aichat-contexto-settings','enqueued') ) {
        wp_enqueue_script(
          'aichat-contexto-settings',
          AICHAT_PLUGIN_URL . 'assets/js/contexto-settings.js',
          [ 'jquery' ],
          AICHAT_VERSION,
          true
        );
        // Localizar todas las cadenas y nonce que el script necesita (usa el mismo objeto global esperado: aichat_settings_ajax)
        wp_localize_script('aichat-contexto-settings','aichat_settings_ajax', [
          'ajax_url'        => admin_url('admin-ajax.php'),
          'nonce'           => wp_create_nonce('aichat_nonce'),
          // Labels botones/acciones
          'settings_label'  => __('Settings', 'axiachat-ai'),
          'similarity_label'=> __('Similarity', 'axiachat-ai'),
          'browse_label'    => __('Browse', 'axiachat-ai'),
          'run_autosync'    => __('Run AutoSync', 'axiachat-ai'),
          'delete_text'     => __('Delete', 'axiachat-ai'),
          'delete_confirm'  => __('Are you sure you want to delete this context?', 'axiachat-ai'),
          'updated_text'    => __('Updated', 'axiachat-ai'),
          'deleted_text'    => __('Deleted successfully.', 'axiachat-ai'),
          // Mensajes funcionales
          'no_contexts'     => __('No contexts found.', 'axiachat-ai'),
          'no_chunks'       => __('No chunks found', 'axiachat-ai'),
          'searching'       => __('Searching embeddings...', 'axiachat-ai'),
          'no_results'      => __('No results found for that query.', 'axiachat-ai'),
          'error_generic'   => __('Unexpected error performing search.', 'axiachat-ai'),
          'loading'         => __('Loading...', 'axiachat-ai'),
        ]);
      }
    }
  });

    

}

// para vista previa del bot en el front (shortcode)
add_action('template_redirect', function () {
  if (!isset($_GET['aichat_preview'])) return;
  if (!current_user_can('manage_options')) { status_header(403); exit; }

  $slug = sanitize_title($_GET['bot'] ?? 'default');

  status_header(200);
  nocache_headers();
  ?>
  <!doctype html>
  <html <?php language_attributes(); ?>>
    <head>
      <meta charset="<?php bloginfo('charset'); ?>">
      <?php wp_head(); ?>
      <style>
        html,body{height:100%;margin:0}
      </style>
    </head>
    <body>
      <?php echo do_shortcode('[aichat id="'.esc_attr($slug).'"]'); ?>
      <?php wp_footer(); ?>
    </body>
  </html>
  <?php
  exit;
});

// Redirect post-activation to the Easy Config wizard (one-time)
add_action('admin_init', function(){
  if ( ! current_user_can('manage_options') ) return;
  if ( ! get_option('aichat_easy_config_do_redirect') ) return;
  // Avoid redirect during AJAX / cron
  if ( wp_doing_ajax() || ( defined('DOING_CRON') && DOING_CRON ) ) return;
  delete_option('aichat_easy_config_do_redirect');
  wp_safe_redirect( admin_url('admin.php?page=aichat-easy-config') );
  exit;
});
// Acción para eliminar una conversación completa
add_action( 'admin_post_aichat_delete_conversation', 'aichat_handle_delete_conversation' );
function aichat_handle_delete_conversation() {
  if ( ! current_user_can('manage_options') ) {
  wp_die( esc_html__( 'Unauthorized', 'axiachat-ai' ) );
  }
  check_admin_referer( 'aichat_delete_conversation' );

  $session_id = isset($_POST['session_id']) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
  $bot_slug   = isset($_POST['bot_slug']) ? sanitize_title( wp_unslash( $_POST['bot_slug'] ) ) : '';

  if ( $session_id && $bot_slug ) {
    global $wpdb; 
    $table = $wpdb->prefix . 'aichat_conversations';
    $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE session_id=%s AND bot_slug=%s", $session_id, $bot_slug ) );
  }

  wp_safe_redirect( add_query_arg( [ 'page'=>'aichat-logs', 'deleted'=>1 ], admin_url('admin.php') ) );
  exit;
}

if ( ! function_exists('aichat_get_ip') ) {
  function aichat_get_ip() {
    foreach ( ['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k ) {
      if ( empty($_SERVER[$k]) ) continue;
      $raw = explode(',', $_SERVER[$k]);
      $ip  = trim($raw[0]);
      if ( filter_var($ip, FILTER_VALIDATE_IP) ) return $ip;
    }
    return '';
  }
}

// ========== EMBED (Script) Nonce endpoint & origin allowlist ==========
// Simple JSON endpoint: /?aichat_embed_nonce=1  --> { nonce:"..." }
// Only returns nonce if HTTP_ORIGIN is empty (same-origin) or allowed in stored option list.
add_action('init', function(){
  if ( ! isset($_GET['aichat_embed_nonce']) ) return; // bail if not requested
  nocache_headers();
  header('Content-Type: application/json; charset=utf-8');
  $origin = isset($_SERVER['HTTP_ORIGIN']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
  $raw_opt = get_option('aichat_embed_allowed_origins', '');
  if (is_string($raw_opt)) { $allowed = preg_split('/\r\n|\r|\n/', $raw_opt); } else { $allowed = (array)$raw_opt; }
  $allowed_norm = [];
  foreach($allowed as $o){ $o = trim($o); if ($o==='') continue; $allowed_norm[] = rtrim($o,'/'); }
  $ok = true;
  if ($origin) { $norm_origin = rtrim($origin,'/'); if (!in_array($norm_origin,$allowed_norm,true)) { $ok = false; } }
  if (! $ok) { echo wp_json_encode(['error'=>'origin_not_allowed']); exit; }
  if ($origin) { header('Access-Control-Allow-Origin: '.$origin); header('Vary: Origin'); }

  $nonce = wp_create_nonce('aichat_ajax');

  // Optional: bots list (?bots=slug1,slug2)
  $ui_map = [];
  if ( isset($_GET['bots']) ) {
    $list_raw = explode(',', sanitize_text_field( wp_unslash($_GET['bots']) ) );
    $slugs = [];
    foreach($list_raw as $s){ $s = sanitize_title($s); if($s!=='') $slugs[] = $s; }
    $slugs = array_unique($slugs);
    if ($slugs) {
      global $wpdb; $table = aichat_bots_table();
      // Prepare placeholders dynamically
      $placeholders = implode(',', array_fill(0, count($slugs), '%s'));
      $query = "SELECT slug, ui_color, ui_position, ui_avatar_enabled, ui_avatar_key, ui_icon_url, ui_start_sentence, ui_placeholder, ui_button_send, ui_closable, ui_minimizable, ui_draggable, ui_minimized_default, ui_superminimized_default FROM $table WHERE slug IN ($placeholders)";
      $rows = $wpdb->get_results( $wpdb->prepare($query, $slugs), ARRAY_A );
      if ($rows){
        foreach($rows as $row){
          $avatar_url = '';
            if ( ! empty($row['ui_avatar_enabled']) ) {
              if ( ! empty($row['ui_icon_url']) ) {
                $avatar_url = esc_url_raw($row['ui_icon_url']);
              } elseif ( ! empty($row['ui_avatar_key']) ) {
                $k = preg_replace('/[^a-z0-9_\-]/i','', $row['ui_avatar_key']);
                if ($k) { $avatar_url = trailingslashit( AICHAT_PLUGIN_URL ) . 'assets/images/' . $k . '.png'; }
              }
            }
          $ui_map[$row['slug']] = [
            'color' => $row['ui_color'],
            'position' => $row['ui_position'],
            'avatar_enabled' => (int)$row['ui_avatar_enabled'],
            'avatar_url' => $avatar_url,
            'start_sentence' => $row['ui_start_sentence'],
            'placeholder' => $row['ui_placeholder'],
            'button_send' => $row['ui_button_send'],
            'closable' => (int)$row['ui_closable'],
            'minimizable' => (int)$row['ui_minimizable'],
            'draggable' => (int)$row['ui_draggable'],
            'minimized_default' => (int)$row['ui_minimized_default'],
            'superminimized_default' => (int)$row['ui_superminimized_default'],
          ];
        }
      }
    }
  }
  echo wp_json_encode(['nonce'=>$nonce,'ui'=>$ui_map]);
  exit;
});


// Security filter: block unapproved external origins for main AJAX actions (defense in depth)
add_filter('init', function(){
  // Only apply on AJAX context after WP loaded vars
  if ( ! defined('DOING_AJAX') || ! DOING_AJAX ) return;
  if ( empty($_POST['action']) ) return;

  $origin = isset($_SERVER['HTTP_ORIGIN']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
  if ( ! $origin ) {
    // No header → same-site form/XHR (WordPress admin normal). Do not restrict.
    return;
  }

  // Compare against site home to allow first-party even if not listed (avoid breaking admin pages served from same domain).
  $site_base = rtrim( get_home_url(), '/' );
  $norm_origin = rtrim( $origin, '/' );
  if ( strtolower($norm_origin) === strtolower($site_base) ) {
    // First-party; no need to consult embed allowlist.
    return;
  }

  // Cross-origin: enforce allowlist
  $raw_opt = get_option('aichat_embed_allowed_origins', '');
  if ( is_string($raw_opt) ) {
    $allowed = preg_split('/\r\n|\r|\n/', $raw_opt);
  } else { $allowed = (array) $raw_opt; }
  $allowed_norm = [];
  foreach ( $allowed as $o ) {
    $o = trim($o);
    if ($o === '') continue;
    $allowed_norm[] = rtrim($o,'/');
  }
  if ( ! in_array( $norm_origin, $allowed_norm, true ) ) {
    wp_send_json_error( [ 'message' => 'Embedding origin not allowed' ], 403 );
  }
  // Allowed cross-origin: add CORS header
  header( 'Access-Control-Allow-Origin: ' . $origin );
  header( 'Vary: Origin' );
});

if ( ! function_exists('aichat_rate_limit_check') ) {
  /**
   * Devuelve WP_Error si excede límite (ráfagas + cooldown + bloqueos adaptativos)
   */
  function aichat_rate_limit_check( $session, $bot_slug ) {
    $ip = aichat_get_ip();
    if ( $ip === '' ) return true; // sin IP no aplicamos (o decide bloquear)

    $now      = time();
    $window   = 60;               // ventana 60s
    $max_hits = 10;               // máx 10 peticiones / 60s
    $cooldown = 1.5;              // min 1.5s entre peticiones

    $key = 'aichat_rl_'. md5($ip.$bot_slug);
    $data = get_transient( $key );
    if ( ! is_array($data) ) {
      $data = [ 'hits'=>0, 'start'=>$now, 'last'=>0 ];
    }

    // Bloqueo adaptativo (transient separado si IP fue castigada)
    if ( get_transient( 'aichat_block_'.$ip ) ) {
  return new WP_Error( 'aichat_blocked_ip_temp', __( 'Too many requests. Try later.', 'axiachat-ai' ) );
    }

    // Reinicia ventana
    if ( $now - $data['start'] > $window ) {
      $data = [ 'hits'=>0, 'start'=>$now, 'last'=>0 ];
    }

    // Cooldown
    if ( $data['last'] && ($now - $data['last']) < $cooldown ) {
  return new WP_Error( 'aichat_cooldown', __( 'Please slow down.', 'axiachat-ai' ) );
    }

    $data['hits']++;
    $data['last'] = $now;

    if ( $data['hits'] > $max_hits ) {
      // castigo temporal 15 min
      set_transient( 'aichat_block_'.$ip, 1, 15 * MINUTE_IN_SECONDS );
  return new WP_Error( 'aichat_rate_limited', __( 'Rate limit reached. Try again later.', 'axiachat-ai' ) );
    }

    set_transient( $key, $data, $window );
    return true;
  }
}

if ( ! function_exists('aichat_spam_signature_check') ) {
  /**
   * Detecta patrones básicos de spam
   */
  function aichat_spam_signature_check( $msg ) {
    $plain = mb_strtolower( trim( $msg ) );
    if ( $plain === '' ) return new WP_Error('aichat_empty','');

    // URLs excesivas
    if ( substr_count($plain,'http://') + substr_count($plain,'https://') > 3 ) {
  return new WP_Error('aichat_spam_links', __( 'Too many links.', 'axiachat-ai' ) );
    }
    // Repetición de mismo caracter
    if ( preg_match('/(.)\\1{20,}/u', $plain) ) {
  return new WP_Error('aichat_spam_repeat', __( 'Invalid pattern.', 'axiachat-ai' ) );
    }
    // Mensaje idéntico repetido (almacenamos hash breve)
    $hash = substr( md5( $plain ), 0, 12 );
    $k = 'aichat_lastmsg_'. ( is_user_logged_in() ? 'u'.get_current_user_id() : 'ip'.md5(aichat_get_ip()) );
    $last = get_transient($k);
    set_transient($k, $hash, 10 * MINUTE_IN_SECONDS);
    if ( $last && $last === $hash ) {
  return new WP_Error('aichat_dup', __( 'Duplicate message detected.', 'axiachat-ai' ) );
    }
    return true;
  }
}

if ( ! function_exists('aichat_record_moderation_block') ) {
  function aichat_record_moderation_block( $reason ) {
    $ip = aichat_get_ip();
    if ( $ip === '' ) return;
    $k = 'aichat_modfails_'.md5($ip);
    $c = (int)get_transient($k);
    $c++;
    set_transient($k,$c, 30 * MINUTE_IN_SECONDS);
    if ( $c >= 5 ) {
      set_transient('aichat_block_'.$ip,1, 30 * MINUTE_IN_SECONDS);
      aichat_log_debug('IP temporarily blocked for moderation failures', ['ip'=>$ip,'count'=>$c]);
    }
  }
}




