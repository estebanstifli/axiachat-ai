<?php
/**
 * MCP (Model Context Protocol) Add-on Loader
 * 
 * Provides integration with external MCP servers following the official specification.
 * Allows connecting to remote tools, resources and prompts via JSON-RPC 2.0.
 * 
 * @package AIChat
 * @subpackage MCP
 * @see https://modelcontextprotocol.io/
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Add-on metadata
if ( ! function_exists('aichat_mcp_addon_info') ) {
    function aichat_mcp_addon_info() {
        return [
            'id'          => 'mcp',
            'name'        => __('MCP Server Connectors', 'axiachat-ai'),
            'description' => __('Connect to external MCP (Model Context Protocol) servers to access remote tools, resources and prompts via HTTP transport.', 'axiachat-ai'),
            'version'     => '1.0.0',
            'author'      => 'AxiaChat AI',
            'requires'    => '1.2.3', // Versión mínima del plugin
            'default'     => false,   // Desactivado por defecto
        ];
    }
}

// Check if add-on is enabled
if ( ! get_option( 'aichat_addon_mcp_enabled', false ) ) {
    if ( function_exists('aichat_log_debug') ) {
        aichat_log_debug('[MCP Add-on] Disabled - skipping load');
    }
    return; // No cargar nada si está desactivado
}

if ( function_exists('aichat_log_debug') ) {
    aichat_log_debug('[MCP Add-on] Loading...');
}

// Define constants
if ( ! defined('AICHAT_MCP_VERSION') ) {
    define('AICHAT_MCP_VERSION', '1.0.0');
}
if ( ! defined('AICHAT_MCP_DIR') ) {
    define('AICHAT_MCP_DIR', __DIR__ . '/');
}

// Load core components in order
require_once AICHAT_MCP_DIR . 'class-mcp-transport.php';
require_once AICHAT_MCP_DIR . 'transports/class-http-transport.php';
require_once AICHAT_MCP_DIR . 'class-mcp-client-manager.php';
require_once AICHAT_MCP_DIR . 'integration.php';

// Admin UI (solo en admin)
if ( is_admin() ) {
    require_once AICHAT_MCP_DIR . 'admin-settings.php';
    require_once AICHAT_MCP_DIR . 'admin-ajax.php';
    
    // Register MCP Servers submenu (only if add-on is enabled)
    add_action( 'admin_menu', 'aichat_mcp_register_menu', 15 );

    // Enqueue admin assets for MCP Servers page
    add_action( 'admin_enqueue_scripts', 'aichat_mcp_admin_enqueue_scripts' );
}

/**
 * Register MCP Servers admin menu
 */
function aichat_mcp_register_menu() {
    if ( ! get_option( 'aichat_addon_mcp_enabled', false ) ) {
        return;
    }
    
    add_submenu_page(
        'aichat-settings',
        __( 'MCP Servers', 'axiachat-ai' ),
        __( 'MCP Servers', 'axiachat-ai' ),
        'manage_options',
        'aichat-mcp-servers',
        'aichat_mcp_render_servers_page'
    );
}

/**
 * Enqueue MCP admin scripts only on the MCP Servers page.
 */
function aichat_mcp_admin_enqueue_scripts( $hook_suffix ) {
    // Only load on our MCP page under the AxiaChat menu
    if ( $hook_suffix !== 'ai-chat_page_aichat-mcp-servers' ) {
        return;
    }

    // Base handle reused from main plugin admin styles if needed
    wp_enqueue_script(
        'aichat-mcp-admin',
        plugins_url( 'assets/js/mcp-admin.js', dirname( dirname( __FILE__ ) ) ),
        [ 'jquery' ],
        defined( 'AICHAT_VERSION' ) ? AICHAT_VERSION : false,
        true
    );

    $ajax_url = admin_url( 'admin-ajax.php' );

    wp_localize_script(
        'aichat-mcp-admin',
        'aichatMcpData',
        [
            'ajaxUrl' => $ajax_url,
            'nonce'   => wp_create_nonce( 'aichat_mcp_nonce' ),
            'ajaxNonce' => wp_create_nonce( 'aichat_mcp_ajax' ),
            'i18n'    => [
                'selectServerPlaceholder'   => __( ' Select a server ', 'axiachat-ai' ),
                'selectServerFirst'        => __( ' Select a server first ', 'axiachat-ai' ),
                'loadingTools'             => __( 'Loading tools...', 'axiachat-ai' ),
                'noToolsAvailable'         => __( 'No tools available', 'axiachat-ai' ),
                'noParameters'             => __( 'This tool takes no parameters.', 'axiachat-ai' ),
                'parametersLabel'          => __( 'Parameters:', 'axiachat-ai' ),
                'executing'                => __( 'Executing...', 'axiachat-ai' ),
                'success'                  => __( 'Success', 'axiachat-ai' ),
                'error'                    => __( 'Error', 'axiachat-ai' ),
                'requestFailed'            => __( 'Request failed', 'axiachat-ai' ),
                'networkError'             => __( 'Network error', 'axiachat-ai' ),
                'connecting'               => __( 'Connecting...', 'axiachat-ai' ),
                'disabled'                 => __( 'Disabled', 'axiachat-ai' ),
                'errorUpdatingServer'      => __( 'Error updating server.', 'axiachat-ai' ),
                'addServer'                => __( 'Add MCP Server', 'axiachat-ai' ),
                'editServer'               => __( 'Edit MCP Server', 'axiachat-ai' ),
                'errorSavingServer'        => __( 'Error saving server.', 'axiachat-ai' ),
                'deleteConfirm'            => __( 'Are you sure you want to delete this server?', 'axiachat-ai' ),
                'testing'                  => __( 'Testing...', 'axiachat-ai' ),
                'connectionSuccessful'     => __( 'Connection successful!', 'axiachat-ai' ),
                'serverInformation'        => __( 'Server Information', 'axiachat-ai' ),
                'protocolVersion'          => __( 'Protocol Version', 'axiachat-ai' ),
                'serverName'               => __( 'Server Name', 'axiachat-ai' ),
                'serverVersion'            => __( 'Server Version', 'axiachat-ai' ),
                'capabilities'             => __( 'Capabilities', 'axiachat-ai' ),
                'availableTools'           => __( 'Available Tools', 'axiachat-ai' ),
                'connectionFailed'         => __( 'Connection failed', 'axiachat-ai' ),
                'unknownError'             => __( 'Unknown error', 'axiachat-ai' ),
                'failedToLoadTools'        => __( 'Failed to load tools.', 'axiachat-ai' ),
                'noToolsForServer'         => __( 'No tools found for this server.', 'axiachat-ai' ),
                'pleaseSelectServerFirst'  => __( 'Please select a server first.', 'axiachat-ai' ),
                'errorSaving'              => __( 'Error saving.', 'axiachat-ai' ),
            ],
        ]
    );
}

// Initialize MCP Manager EARLY to ensure initialize_servers() hook fires
add_action('plugins_loaded', function() {
    if ( class_exists('AIChat_MCP_Client_Manager') ) {
        AIChat_MCP_Client_Manager::instance();
        if ( function_exists('aichat_log_debug') ) {
            aichat_log_debug('[MCP Add-on] Client Manager initialized');
        }
    }
}, 10); // EARLY - before manager's internal initialize_servers() at priority 15

if ( function_exists('aichat_log_debug') ) {
    aichat_log_debug('[MCP Add-on] Loaded successfully');
}
