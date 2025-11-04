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
            'description' => __('Connect to external MCP (Model Context Protocol) servers to access remote tools, resources and prompts. Supports HTTP and STDIO transports.', 'axiachat-ai'),
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
require_once AICHAT_MCP_DIR . 'transports/class-stdio-transport.php';
require_once AICHAT_MCP_DIR . 'class-mcp-client-manager.php';
require_once AICHAT_MCP_DIR . 'integration.php';

// Admin UI (solo en admin)
if ( is_admin() ) {
    require_once AICHAT_MCP_DIR . 'admin-settings.php';
    require_once AICHAT_MCP_DIR . 'admin-ajax.php';
    
    // Register MCP Servers submenu (only if add-on is enabled)
    add_action( 'admin_menu', 'aichat_mcp_register_menu', 15 );
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
