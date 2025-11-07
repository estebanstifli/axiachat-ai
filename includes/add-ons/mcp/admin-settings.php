<?php
/**
 * MCP Add-on: Admin settings page
 * Renders UI for managing MCP servers (add, edit, test, delete).
 *
 * @package AIChat
 * @subpackage MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render MCP Servers admin page
 */
function aichat_mcp_render_servers_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions.', 'axiachat-ai' ) );
    }

    // Get saved servers (array of server configs)
    $servers = get_option( 'aichat_mcp_servers', [] );
    if ( ! is_array( $servers ) ) {
        $servers = [];
    }

    ?>
    <div class="wrap aichat-mcp-wrap">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-admin-plugins" style="color:#6f42c1"></span>
            <?php echo esc_html__( 'MCP Servers', 'axiachat-ai' ); ?>
        </h1>
        <a href="#" id="aichat-mcp-add-server" class="page-title-action"><?php echo esc_html__( 'Add Server', 'axiachat-ai' ); ?></a>
        <p class="description">
            <?php echo esc_html__( 'Configure external MCP servers to expose their tools to your AI bots.', 'axiachat-ai' ); ?>
        </p>

        <!-- Navigation Tabs -->
        <nav class="nav-tab-wrapper wp-clearfix" style="margin-top:20px;">
            <a href="#tab-servers" class="nav-tab nav-tab-active" id="tab-servers-link">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php echo esc_html__( 'Servers', 'axiachat-ai' ); ?>
            </a>
            <a href="#tab-test-tools" class="nav-tab" id="tab-test-tools-link">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php echo esc_html__( 'Test Tools', 'axiachat-ai' ); ?>
            </a>
            <a href="#tab-manage-tools" class="nav-tab" id="tab-manage-tools-link">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php echo esc_html__( 'Enable/Disable Tools', 'axiachat-ai' ); ?>
            </a>
        </nav>

        <!-- Tab: Servers List -->
        <div id="tab-servers" class="aichat-mcp-tab-content">
        <?php if ( empty( $servers ) ): ?>
            <div class="notice notice-info inline" style="margin-top:20px;">
                <p><strong><?php echo esc_html__( 'No MCP servers configured yet.', 'axiachat-ai' ); ?></strong></p>
                <p><?php echo esc_html__( 'Click "Add Server" to connect to an external MCP server (Sentry, Notion, GitHub, local tools, etc.).', 'axiachat-ai' ); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
                <thead>
                    <tr>
                        <th style="width:20%;"><?php echo esc_html__( 'Name', 'axiachat-ai' ); ?></th>
                        <th style="width:10%;"><?php echo esc_html__( 'Transport', 'axiachat-ai' ); ?></th>
                        <th style="width:30%;"><?php echo esc_html__( 'Endpoint', 'axiachat-ai' ); ?></th>
                        <th style="width:10%;"><?php echo esc_html__( 'Enabled', 'axiachat-ai' ); ?></th>
                        <th style="width:15%;"><?php echo esc_html__( 'Status', 'axiachat-ai' ); ?></th>
                        <th style="width:15%;"><?php echo esc_html__( 'Actions', 'axiachat-ai' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $servers as $server_id => $server ): ?>
                        <tr data-server-id="<?php echo esc_attr( $server_id ); ?>">
                            <td><strong><?php echo esc_html( $server['name'] ?? $server_id ); ?></strong></td>
                            <td>
                                <?php
                                $transport_type = strtolower( $server['transport'] ?? 'http' );
                                if ( $transport_type === 'http' ) {
                                    echo esc_html__( 'HTTP', 'axiachat-ai' );
                                } else {
                                    echo esc_html__( 'STDIO (deprecated)', 'axiachat-ai' );
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ( $transport_type === 'http' ) {
                                    echo esc_html( $server['url'] ?? '' );
                                } else {
                                    echo esc_html__( 'Update required: provide an HTTP endpoint.', 'axiachat-ai' );
                                }
                                ?>
                            </td>
                            <td>
                                <?php $is_enabled = isset( $server['enabled'] ) ? (bool) $server['enabled'] : true; ?>
                                <label class="aichat-mcp-toggle">
                                    <input type="checkbox" class="aichat-mcp-toggle-enabled" 
                                           data-server-id="<?php echo esc_attr( $server_id ); ?>"
                                           <?php checked( $is_enabled ); ?> />
                                    <span class="aichat-mcp-toggle-slider"></span>
                                </label>
                            </td>
                            <td>
                                <span class="aichat-mcp-status" data-server-id="<?php echo esc_attr( $server_id ); ?>">
                                    <span class="dashicons dashicons-minus" style="color:#999;"></span>
                                    <?php echo esc_html__( 'Unknown', 'axiachat-ai' ); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button button-small aichat-mcp-test" data-server-id="<?php echo esc_attr( $server_id ); ?>">
                                    <?php echo esc_html__( 'Test', 'axiachat-ai' ); ?>
                                </button>
                                <button type="button" class="button button-small aichat-mcp-edit" data-server-id="<?php echo esc_attr( $server_id ); ?>">
                                    <?php echo esc_html__( 'Edit', 'axiachat-ai' ); ?>
                                </button>
                                <button type="button" class="button button-small button-link-delete aichat-mcp-delete" data-server-id="<?php echo esc_attr( $server_id ); ?>">
                                    <?php echo esc_html__( 'Delete', 'axiachat-ai' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>

        <!-- Tab: Test Tools -->
        <div id="tab-test-tools" class="aichat-mcp-tab-content" style="display:none;">
            <div style="max-width:900px;margin:20px 0;">
                <div class="card">
                    <div class="card-header" style="background:#f6f7f7;padding:15px;border-bottom:1px solid #dcdcde;">
                        <h2 style="margin:0;font-size:14px;">
                            <span class="dashicons dashicons-admin-tools" style="color:#2271b1;"></span>
                            <?php echo esc_html__( 'Test MCP Tools', 'axiachat-ai' ); ?>
                        </h2>
                    </div>
                    <div style="padding:20px;">
                        <!-- Server Selection -->
                        <div style="margin-bottom:20px;">
                            <label for="aichat-mcp-test-server-select" style="display:block;margin-bottom:8px;font-weight:600;">
                                <?php echo esc_html__( 'Select MCP Server', 'axiachat-ai' ); ?>
                            </label>
                            <select id="aichat-mcp-test-server-select" class="regular-text" style="width:100%;max-width:400px;">
                                <option value=""><?php echo esc_html__( '— Select a server —', 'axiachat-ai' ); ?></option>
                            </select>
                        </div>

                        <!-- Tool Selection -->
                        <div style="margin-bottom:20px;">
                            <label for="aichat-mcp-test-tool-select" style="display:block;margin-bottom:8px;font-weight:600;">
                                <?php echo esc_html__( 'Select Tool', 'axiachat-ai' ); ?>
                            </label>
                            <select id="aichat-mcp-test-tool-select" class="regular-text" style="width:100%;max-width:400px;" disabled>
                                <option value=""><?php echo esc_html__( '— Select a server first —', 'axiachat-ai' ); ?></option>
                            </select>
                        </div>

                        <!-- Tool Description -->
                        <div id="aichat-mcp-test-tool-description" style="margin-bottom:20px;padding:10px;background:#f0f6fc;border-left:3px solid #2271b1;display:none;">
                            <strong><?php echo esc_html__( 'Description:', 'axiachat-ai' ); ?></strong>
                            <p id="aichat-mcp-test-tool-desc-text" style="margin:5px 0 0 0;"></p>
                        </div>

                        <!-- Dynamic Form -->
                        <div id="aichat-mcp-test-tool-form" style="margin-bottom:20px;"></div>

                        <!-- Run Button -->
                        <div style="margin-bottom:20px;">
                            <button type="button" class="button button-primary" id="aichat-mcp-test-tool-run" disabled>
                                <span class="dashicons dashicons-controls-play" style="margin-top:3px;"></span>
                                <?php echo esc_html__( 'Execute Tool', 'axiachat-ai' ); ?>
                            </button>
                            <span id="aichat-mcp-test-tool-status" style="margin-left:10px;color:#666;"></span>
                        </div>

                        <!-- Result Display -->
                        <div>
                            <label style="display:block;margin-bottom:8px;font-weight:600;">
                                <?php echo esc_html__( 'Result', 'axiachat-ai' ); ?>
                            </label>
                            <pre id="aichat-mcp-test-tool-result" style="background:#0d1117;color:#c9d1d9;padding:15px;border-radius:6px;overflow:auto;max-height:400px;white-space:pre-wrap;word-wrap:break-word;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.5;"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Enable/Disable Tools -->
    <div id="tab-manage-tools" class="aichat-mcp-tab-content" style="display:none;">
        <div class="card" style="margin-top:20px;max-width:none;">
            <h2><?php echo esc_html__( 'Enable/Disable MCP Tools', 'axiachat-ai' ); ?></h2>
            <p class="description">
                <?php echo esc_html__( 'Control which tools from each MCP server are available to your AI bots. Disabled tools will not be sent to the AI model.', 'axiachat-ai' ); ?>
            </p>

            <div style="margin-top:20px;">
                <!-- Server Selection -->
                <div style="margin-bottom:20px;">
                    <label for="aichat-mcp-manage-server-select" style="display:block;margin-bottom:8px;font-weight:600;">
                        <?php echo esc_html__( 'Select MCP Server', 'axiachat-ai' ); ?>
                    </label>
                    <select id="aichat-mcp-manage-server-select" class="regular-text" style="min-width:350px;">
                        <option value=""><?php echo esc_html__( '-- Select a server --', 'axiachat-ai' ); ?></option>
                        <?php foreach ( $servers as $sid => $srv ): ?>
                            <?php if ( isset( $srv['enabled'] ) && $srv['enabled'] ): ?>
                                <option value="<?php echo esc_attr( $sid ); ?>">
                                    <?php echo esc_html( $srv['name'] ?? $sid ); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Loading Indicator -->
                <div id="aichat-mcp-manage-loading" style="display:none;padding:20px;text-align:center;">
                    <span class="spinner is-active" style="float:none;"></span>
                    <p><?php echo esc_html__( 'Loading tools...', 'axiachat-ai' ); ?></p>
                </div>

                <!-- Tools List -->
                <div id="aichat-mcp-manage-tools-container" style="display:none;">
                    <div style="margin-bottom:15px;">
                        <button type="button" class="button" id="aichat-mcp-enable-all">
                            <span class="dashicons dashicons-yes"></span>
                            <?php echo esc_html__( 'Enable All', 'axiachat-ai' ); ?>
                        </button>
                        <button type="button" class="button" id="aichat-mcp-disable-all">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php echo esc_html__( 'Disable All', 'axiachat-ai' ); ?>
                        </button>
                    </div>

                    <div id="aichat-mcp-manage-tools-list" style="max-height:500px;overflow-y:auto;border:1px solid #ddd;padding:15px;background:#fff;">
                        <!-- Tools will be loaded here via AJAX -->
                    </div>

                    <div style="margin-top:20px;">
                        <button type="button" class="button button-primary button-large" id="aichat-mcp-save-tools-status">
                            <span class="dashicons dashicons-saved"></span>
                            <?php echo esc_html__( 'Save Changes', 'axiachat-ai' ); ?>
                        </button>
                        <span id="aichat-mcp-save-status" style="margin-left:15px;"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Server Modal -->
    <div id="aichat-mcp-modal" style="display:none;">
        <div class="aichat-mcp-modal-backdrop"></div>
        <div class="aichat-mcp-modal-content">
            <div class="aichat-mcp-modal-header">
                <h2 id="aichat-mcp-modal-title"><?php echo esc_html__( 'Add MCP Server', 'axiachat-ai' ); ?></h2>
                <button type="button" class="aichat-mcp-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="aichat-mcp-modal-body">
                <form id="aichat-mcp-server-form">
                    <input type="hidden" id="mcp-server-id" name="server_id" value="" />
                    <input type="hidden" name="transport" value="http" />

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="mcp-name"><?php echo esc_html__( 'Server Name', 'axiachat-ai' ); ?></label></th>
                            <td>
                                <input type="text" id="mcp-name" name="name" class="regular-text" required />
                                <p class="description"><?php echo esc_html__( 'Descriptive name (e.g., "Sentry Tools", "Notion Database").', 'axiachat-ai' ); ?></p>
                            </td>
                        </tr>
                    </table>

                    <div id="mcp-http-config">
                        <h3><?php echo esc_html__( 'HTTP Configuration', 'axiachat-ai' ); ?></h3>
                        <p class="description"><?php echo esc_html__( 'Only HTTP/HTTPS MCP servers are supported.', 'axiachat-ai' ); ?></p>
                        <div id="mcp-unsupported-transport" class="notice notice-warning" style="display:none;margin:0 0 15px;">
                            <p><strong><?php echo esc_html__( 'STDIO connections have been removed.', 'axiachat-ai' ); ?></strong> <?php echo esc_html__( 'Update this server with an HTTP/HTTPS endpoint to continue using it.', 'axiachat-ai' ); ?></p>
                        </div>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="mcp-url"><?php echo esc_html__( 'Server URL', 'axiachat-ai' ); ?></label></th>
                                <td>
                                    <input type="url" id="mcp-url" name="url" class="regular-text" placeholder="https://api.example.com/mcp" />
                                    <p class="description"><?php echo esc_html__( 'Full URL to the MCP endpoint.', 'axiachat-ai' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="mcp-auth-type"><?php echo esc_html__( 'Authentication', 'axiachat-ai' ); ?></label></th>
                                <td>
                                    <select id="mcp-auth-type" name="auth_type" class="regular-text">
                                        <option value="none"><?php echo esc_html__( 'None', 'axiachat-ai' ); ?></option>
                                        <option value="bearer"><?php echo esc_html__( 'Bearer Token', 'axiachat-ai' ); ?></option>
                                        <option value="api_key"><?php echo esc_html__( 'API Key (custom header)', 'axiachat-ai' ); ?></option>
                                        <option value="custom"><?php echo esc_html__( 'Custom Headers', 'axiachat-ai' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr id="mcp-auth-token-row" style="display:none;">
                                <th scope="row"><label for="mcp-auth-token"><?php echo esc_html__( 'Token / API Key', 'axiachat-ai' ); ?></label></th>
                                <td>
                                    <input type="password" id="mcp-auth-token" name="auth_token" class="regular-text" autocomplete="off" />
                                </td>
                            </tr>
                            <tr id="mcp-auth-header-row" style="display:none;">
                                <th scope="row"><label for="mcp-auth-header"><?php echo esc_html__( 'Header Name', 'axiachat-ai' ); ?></label></th>
                                <td>
                                    <input type="text" id="mcp-auth-header" name="auth_header" class="regular-text" placeholder="X-API-Key" />
                                </td>
                            </tr>
                            <tr id="mcp-custom-headers-row" style="display:none;">
                                <th scope="row"><label for="mcp-custom-headers"><?php echo esc_html__( 'Custom Headers', 'axiachat-ai' ); ?></label></th>
                                <td>
                                    <textarea id="mcp-custom-headers" name="custom_headers" class="large-text" rows="4" placeholder='{"X-Custom-Header": "value"}'></textarea>
                                    <p class="description"><?php echo esc_html__( 'JSON object with custom headers.', 'axiachat-ai' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="aichat-mcp-modal-footer">
                        <button type="button" class="button button-secondary aichat-mcp-modal-close"><?php echo esc_html__( 'Cancel', 'axiachat-ai' ); ?></button>
                        <button type="submit" class="button button-primary"><?php echo esc_html__( 'Save Server', 'axiachat-ai' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Test Results Modal -->
    <div id="aichat-mcp-test-modal" style="display:none;">
        <div class="aichat-mcp-modal-backdrop"></div>
        <div class="aichat-mcp-modal-content">
            <div class="aichat-mcp-modal-header">
                <h2><?php echo esc_html__( 'Test Connection', 'axiachat-ai' ); ?></h2>
                <button type="button" class="aichat-mcp-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="aichat-mcp-modal-body">
                <div id="aichat-mcp-test-results"></div>
            </div>
            <div class="aichat-mcp-modal-footer">
                <button type="button" class="button button-secondary aichat-mcp-modal-close"><?php echo esc_html__( 'Close', 'axiachat-ai' ); ?></button>
            </div>
        </div>
    </div>

    <style>
        .aichat-mcp-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 100000;
        }
        .aichat-mcp-modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
            z-index: 100001;
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .aichat-mcp-modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .aichat-mcp-modal-header h2 {
            margin: 0;
            font-size: 1.3em;
        }
        .aichat-mcp-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            color: #666;
        }
        .aichat-mcp-modal-body {
            padding: 25px;
        }
        .aichat-mcp-modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #ddd;
            text-align: right;
        }
        .aichat-mcp-status .dashicons {
            vertical-align: middle;
            margin-right: 4px;
        }
        /* Toggle switch */
        .aichat-mcp-toggle {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .aichat-mcp-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .aichat-mcp-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }
        .aichat-mcp-toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        .aichat-mcp-toggle input:checked + .aichat-mcp-toggle-slider {
            background-color: #46b450;
        }
        .aichat-mcp-toggle input:checked + .aichat-mcp-toggle-slider:before {
            transform: translateX(20px);
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        const modal = $('#aichat-mcp-modal');
        const testModal = $('#aichat-mcp-test-modal');
        const form = $('#aichat-mcp-server-form');

        // === TAB NAVIGATION ===
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.aichat-mcp-tab-content').hide();
            $(target).show();
        });

        // === TEST TOOLS TAB ===
        let mcpTools = {}; // {server_id: [{name, description, schema}, ...]}

        // Load servers into select
        function loadMCPServers() {
            $.post(ajaxurl, {
                action: 'aichat_mcp_list_servers_for_test',
                nonce: '<?php echo esc_js( wp_create_nonce( 'aichat_mcp_nonce' ) ); ?>'
            }, function(response) {
                if (response.success && response.data.servers) {
                    const select = $('#aichat-mcp-test-server-select');
                    select.html('<option value=""><?php echo esc_js( __( '— Select a server —', 'axiachat-ai' ) ); ?></option>');
                    
                    $.each(response.data.servers, function(serverId, serverName) {
                        select.append($('<option>', {
                            value: serverId,
                            text: serverName
                        }));
                    });
                }
            });
        }

        // Load tools for selected server
        $('#aichat-mcp-test-server-select').on('change', function() {
            const serverId = $(this).val();
            const toolSelect = $('#aichat-mcp-test-tool-select');
            const runButton = $('#aichat-mcp-test-tool-run');
            
            toolSelect.prop('disabled', true).html('<option><?php echo esc_js( __( 'Loading tools...', 'axiachat-ai' ) ); ?></option>');
            runButton.prop('disabled', true);
            $('#aichat-mcp-test-tool-description').hide();
            $('#aichat-mcp-test-tool-form').empty();
            $('#aichat-mcp-test-tool-result').text('');
            
            if (!serverId) {
                toolSelect.html('<option><?php echo esc_js( __( '— Select a server first —', 'axiachat-ai' ) ); ?></option>');
                return;
            }
            
            $.post(ajaxurl, {
                action: 'aichat_mcp_list_tools',
                nonce: '<?php echo esc_js( wp_create_nonce( 'aichat_mcp_nonce' ) ); ?>',
                server_id: serverId
            }, function(response) {
                if (response.success && response.data.tools) {
                    mcpTools[serverId] = response.data.tools;
                    toolSelect.html('<option value=""><?php echo esc_js( __( '— Select a tool —', 'axiachat-ai' ) ); ?></option>');
                    
                    $.each(response.data.tools, function(i, tool) {
                        toolSelect.append($('<option>', {
                            value: tool.name,
                            text: tool.name
                        }));
                    });
                    
                    toolSelect.prop('disabled', false);
                } else {
                    toolSelect.html('<option><?php echo esc_js( __( 'No tools available', 'axiachat-ai' ) ); ?></option>');
                }
            });
        });

        // Build form when tool selected
        $('#aichat-mcp-test-tool-select').on('change', function() {
            const serverId = $('#aichat-mcp-test-server-select').val();
            const toolName = $(this).val();
            const runButton = $('#aichat-mcp-test-tool-run');
            
            $('#aichat-mcp-test-tool-form').empty();
            $('#aichat-mcp-test-tool-description').hide();
            $('#aichat-mcp-test-tool-result').text('');
            runButton.prop('disabled', true);
            
            if (!toolName || !mcpTools[serverId]) return;
            
            const tool = mcpTools[serverId].find(t => t.name === toolName);
            if (!tool) return;
            
            // Show description
            if (tool.description) {
                $('#aichat-mcp-test-tool-desc-text').text(tool.description);
                $('#aichat-mcp-test-tool-description').show();
            }
            
            // Build form from schema
            const schema = tool.inputSchema || tool.schema || {};
            const properties = schema.properties || {};
            const required = schema.required || [];
            
            if (Object.keys(properties).length === 0) {
                $('#aichat-mcp-test-tool-form').html('<p style="color:#666;font-style:italic;"><?php echo esc_js( __( 'This tool takes no parameters.', 'axiachat-ai' ) ); ?></p>');
                runButton.prop('disabled', false);
                return;
            }
            
            let formHTML = '<div style="background:#f9f9f9;padding:15px;border-radius:4px;">';
            formHTML += '<strong><?php echo esc_js( __( 'Parameters:', 'axiachat-ai' ) ); ?></strong><br><br>';
            
            $.each(properties, function(propName, propDef) {
                const isRequired = required.includes(propName);
                const propType = propDef.type || 'string';
                const propDesc = propDef.description || '';
                
                formHTML += '<div style="margin-bottom:15px;">';
                formHTML += '<label style="display:block;margin-bottom:5px;font-weight:500;">';
                formHTML += propName;
                if (isRequired) formHTML += ' <span style="color:#d63638;">*</span>';
                formHTML += '</label>';
                
                if (propDesc) {
                    formHTML += '<p style="margin:0 0 5px 0;font-size:12px;color:#666;">' + propDesc + '</p>';
                }
                
                if (propType === 'boolean') {
                    formHTML += '<select class="mcp-test-param" data-param="' + propName + '" style="width:100%;max-width:300px;">';
                    formHTML += '<option value="true">true</option>';
                    formHTML += '<option value="false">false</option>';
                    formHTML += '</select>';
                } else if (propType === 'number' || propType === 'integer') {
                    formHTML += '<input type="number" class="mcp-test-param" data-param="' + propName + '" style="width:100%;max-width:300px;" />';
                } else {
                    formHTML += '<input type="text" class="mcp-test-param" data-param="' + propName + '" style="width:100%;max-width:600px;" />';
                }
                
                formHTML += '</div>';
            });
            
            formHTML += '</div>';
            $('#aichat-mcp-test-tool-form').html(formHTML);
            runButton.prop('disabled', false);
        });

        // Execute tool
        $('#aichat-mcp-test-tool-run').on('click', function() {
            const serverId = $('#aichat-mcp-test-server-select').val();
            const toolName = $('#aichat-mcp-test-tool-select').val();
            
            if (!serverId || !toolName) return;
            
            // Collect parameters
            const params = {};
            $('.mcp-test-param').each(function() {
                const paramName = $(this).data('param');
                let val = $(this).val();
                
                // Convert types
                if ($(this).is('select') && val === 'true') val = true;
                if ($(this).is('select') && val === 'false') val = false;
                if ($(this).attr('type') === 'number') val = parseFloat(val);
                
                if (val !== '') params[paramName] = val;
            });
            
            const statusEl = $('#aichat-mcp-test-tool-status');
            const resultEl = $('#aichat-mcp-test-tool-result');
            const runButton = $(this);
            
            runButton.prop('disabled', true);
            statusEl.html('<span class="dashicons dashicons-update spin" style="color:#2271b1;"></span> <?php echo esc_js( __( 'Executing...', 'axiachat-ai' ) ); ?>');
            resultEl.text('');
            
            $.post(ajaxurl, {
                action: 'aichat_mcp_run_tool',
                nonce: '<?php echo esc_js( wp_create_nonce( 'aichat_mcp_nonce' ) ); ?>',
                server_id: serverId,
                tool_name: toolName,
                arguments: JSON.stringify(params)
            }, function(response) {
                runButton.prop('disabled', false);
                
                if (response.success) {
                    statusEl.html('<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> <?php echo esc_js( __( 'Success', 'axiachat-ai' ) ); ?>');
                    resultEl.text(JSON.stringify(response.data, null, 2));
                } else {
                    statusEl.html('<span class="dashicons dashicons-dismiss" style="color:#d63638;"></span> <?php echo esc_js( __( 'Error', 'axiachat-ai' ) ); ?>');
                    resultEl.text(JSON.stringify(response.data || response, null, 2));
                }
            }).fail(function(xhr) {
                runButton.prop('disabled', false);
                statusEl.html('<span class="dashicons dashicons-dismiss" style="color:#d63638;"></span> <?php echo esc_js( __( 'Request failed', 'axiachat-ai' ) ); ?>');
                resultEl.text(xhr.responseText || '<?php echo esc_js( __( 'Network error', 'axiachat-ai' ) ); ?>');
            });
        });

        // Load servers on tab open
        $('#tab-test-tools-link').on('click', function() {
            if ($('#aichat-mcp-test-server-select option').length === 1) {
                loadMCPServers();
            }
        });

        // Toggle enabled/disabled
        $('.aichat-mcp-toggle-enabled').on('change', function() {
            const serverId = $(this).data('server-id');
            const enabled = $(this).is(':checked');
            
            $.post(ajaxurl, {
                action: 'aichat_mcp_toggle_server',
                server_id: serverId,
                enabled: enabled ? 1 : 0,
                _wpnonce: '<?php echo esc_js( wp_create_nonce( 'aichat_mcp_ajax' ) ); ?>'
            }, function(response) {
                if (response.success) {
                    // Update status
                    const statusEl = $(`.aichat-mcp-status[data-server-id="${serverId}"]`);
                    if (enabled) {
                        statusEl.html('<span class="dashicons dashicons-update spin"></span> <?php echo esc_js( __( 'Connecting...', 'axiachat-ai' ) ); ?>');
                        // Auto-test connection after enabling
                        setTimeout(function() {
                            $(`.aichat-mcp-test[data-server-id="${serverId}"]`).click();
                        }, 500);
                    } else {
                        statusEl.html('<span class="dashicons dashicons-minus" style="color:#999;"></span> <?php echo esc_js( __( 'Disabled', 'axiachat-ai' ) ); ?>');
                    }
                } else {
                    alert(response.data || '<?php echo esc_js( __( 'Error updating server.', 'axiachat-ai' ) ); ?>');
                    // Revert toggle
                    $(this).prop('checked', !enabled);
                }
            }.bind(this));
        });

        // Auth type change
        $('#mcp-auth-type').on('change', function() {
            const authType = $(this).val();
            $('#mcp-auth-token-row, #mcp-auth-header-row, #mcp-custom-headers-row').hide();

            if (authType === 'bearer') {
                $('#mcp-auth-token-row').show();
            } else if (authType === 'api_key') {
                $('#mcp-auth-token-row, #mcp-auth-header-row').show();
            } else if (authType === 'custom') {
                $('#mcp-custom-headers-row').show();
            }
        });

        // Add server
        $('#aichat-mcp-add-server').on('click', function(e) {
            e.preventDefault();
            form[0].reset();
            $('#mcp-server-id').val('');
            $('#aichat-mcp-modal-title').text('<?php echo esc_js( __( 'Add MCP Server', 'axiachat-ai' ) ); ?>');
            $('#mcp-auth-type').trigger('change');
            $('#mcp-unsupported-transport').hide();
            modal.fadeIn(200);
        });

        // Edit server
        $('.aichat-mcp-edit').on('click', function() {
            const serverId = $(this).data('server-id');
            $.post(ajaxurl, {
                action: 'aichat_mcp_get_server',
                server_id: serverId,
                _wpnonce: '<?php echo esc_js( wp_create_nonce( 'aichat_mcp_ajax' ) ); ?>'
            }, function(response) {
                if (response.success) {
                    const server = response.data;
                    $('#mcp-server-id').val(serverId);
                    $('#mcp-name').val(server.name || '');
                    
                    // HTTP fields
                    $('#mcp-url').val(server.url || '');
                    $('#mcp-auth-type').val(server.auth_type || 'none').trigger('change');
                    $('#mcp-auth-token').val(server.auth_token || '');
                    $('#mcp-auth-header').val(server.auth_header || '');
                    $('#mcp-custom-headers').val(server.custom_headers || '');

                    if (server.transport && server.transport !== 'http') {
                        $('#mcp-unsupported-transport').show();
                    } else {
                        $('#mcp-unsupported-transport').hide();
                    }
                    
                    $('#aichat-mcp-modal-title').text('<?php echo esc_js( __( 'Edit MCP Server', 'axiachat-ai' ) ); ?>');
                    modal.fadeIn(200);
                }
            });
        });

        // Save server
        form.on('submit', function(e) {
            e.preventDefault();
            const data = $(this).serializeArray();
            const isNewServer = !$('#mcp-server-id').val();
            data.push({name: 'action', value: 'aichat_mcp_save_server'});
            data.push({name: '_wpnonce', value: '<?php echo esc_js( wp_create_nonce( 'aichat_mcp_ajax' ) ); ?>'});

            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    // If new server, reload and auto-test
                    if (isNewServer) {
                        window.location.href = window.location.href + '&test=' + response.data.server_id;
                    } else {
                        location.reload();
                    }
                } else {
                    alert(response.data || '<?php echo esc_js( __( 'Error saving server.', 'axiachat-ai' ) ); ?>');
                }
            });
        });

        // Delete server
        $('.aichat-mcp-delete').on('click', function() {
            if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this server?', 'axiachat-ai' ) ); ?>')) {
                return;
            }
            const serverId = $(this).data('server-id');
            $.post(ajaxurl, {
                action: 'aichat_mcp_delete_server',
                server_id: serverId,
                _wpnonce: '<?php echo esc_js( wp_create_nonce( 'aichat_mcp_ajax' ) ); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        });

        // Test connection
        $('.aichat-mcp-test').on('click', function() {
            const serverId = $(this).data('server-id');
            const statusEl = $(`.aichat-mcp-status[data-server-id="${serverId}"]`);
            
            statusEl.html('<span class="dashicons dashicons-update spin"></span> <?php echo esc_js( __( 'Testing...', 'axiachat-ai' ) ); ?>');
            
            $.post(ajaxurl, {
                action: 'aichat_mcp_test_server',
                server_id: serverId,
                _wpnonce: '<?php echo esc_js( wp_create_nonce( 'aichat_mcp_ajax' ) ); ?>'
            }, function(response) {
                if (response.success) {
                    statusEl.html('<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> <?php echo esc_js( __( 'Connected', 'axiachat-ai' ) ); ?>');
                    const data = response.data;
                    let html = '<div class="notice notice-success inline"><p><strong><?php echo esc_js( __( 'Connection successful!', 'axiachat-ai' ) ); ?></strong></p></div>';
                    html += '<h3><?php echo esc_js( __( 'Server Information', 'axiachat-ai' ) ); ?></h3>';
                    html += '<table class="widefat"><tbody>';
                    html += '<tr><th style="width:30%;"><?php echo esc_js( __( 'Protocol Version', 'axiachat-ai' ) ); ?></th><td>' + (data.protocol_version || '—') + '</td></tr>';
                    html += '<tr><th><?php echo esc_js( __( 'Server Name', 'axiachat-ai' ) ); ?></th><td>' + (data.server_name || '—') + '</td></tr>';
                    html += '<tr><th><?php echo esc_js( __( 'Server Version', 'axiachat-ai' ) ); ?></th><td>' + (data.server_version || '—') + '</td></tr>';
                    html += '<tr><th><?php echo esc_js( __( 'Capabilities', 'axiachat-ai' ) ); ?></th><td>' + (data.capabilities || '—') + '</td></tr>';
                    html += '</tbody></table>';
                    
                    if (data.tools && data.tools.length > 0) {
                        html += '<h3><?php echo esc_js( __( 'Available Tools', 'axiachat-ai' ) ); ?> (' + data.tools.length + ')</h3>';
                        html += '<ul style="column-count:2;">';
                        data.tools.forEach(tool => {
                            html += '<li><strong>' + tool.name + '</strong>';
                            if (tool.description) {
                                html += '<br><span style="color:#666;font-size:12px;">' + tool.description + '</span>';
                            }
                            html += '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    $('#aichat-mcp-test-results').html(html);
                    testModal.fadeIn(200);
                } else {
                    statusEl.html('<span class="dashicons dashicons-dismiss" style="color:#dc3232;"></span> <?php echo esc_js( __( 'Failed', 'axiachat-ai' ) ); ?>');
                    let html = '<div class="notice notice-error inline"><p><strong><?php echo esc_js( __( 'Connection failed', 'axiachat-ai' ) ); ?></strong></p>';
                    html += '<p>' + (response.data || '<?php echo esc_js( __( 'Unknown error', 'axiachat-ai' ) ); ?>') + '</p></div>';
                    $('#aichat-mcp-test-results').html(html);
                    testModal.fadeIn(200);
                }
            });
        });

        // Close modals
        $('.aichat-mcp-modal-close, .aichat-mcp-modal-backdrop').on('click', function() {
            modal.fadeOut(200);
            testModal.fadeOut(200);
        });

        // Spinner CSS
        $('<style>.dashicons.spin { animation: spin 1s linear infinite; } @keyframes spin { 100% { transform: rotate(360deg); } }</style>').appendTo('head');
        
        // ========== Enable/Disable Tools Tab ==========
        
        // Load tools when server is selected
        $('#aichat-mcp-manage-server-select').on('change', function() {
            const serverId = $(this).val();
            
            if (!serverId) {
                $('#aichat-mcp-manage-tools-container').hide();
                return;
            }

            $('#aichat-mcp-manage-loading').show();
            $('#aichat-mcp-manage-tools-container').hide();

            $.post(ajaxurl, {
                action: 'aichat_mcp_get_server_tools_status',
                nonce: '<?php echo esc_js( wp_create_nonce( 'aichat_mcp_nonce' ) ); ?>',
                server_id: serverId
            }, function(response) {
                $('#aichat-mcp-manage-loading').hide();
                
                if (response.success && response.data.tools) {
                    renderToolsCheckboxes(response.data.tools);
                    $('#aichat-mcp-manage-tools-container').show();
                } else {
                    alert('<?php echo esc_js( __( 'Failed to load tools.', 'axiachat-ai' ) ); ?>');
                }
            });
        });

        function renderToolsCheckboxes(tools) {
            let html = '';
            
            if (tools.length === 0) {
                html = '<p><?php echo esc_js( __( 'No tools found for this server.', 'axiachat-ai' ) ); ?></p>';
            } else {
                tools.forEach(function(tool) {
                    const checked = tool.enabled ? 'checked' : '';
                    html += '<div style="padding:10px;border-bottom:1px solid #f0f0f0;">';
                    html += '<label style="display:flex;align-items:center;cursor:pointer;">';
                    html += '<input type="checkbox" class="mcp-tool-checkbox" data-tool="' + tool.local_name + '" ' + checked + ' style="margin:0 10px 0 0;" />';
                    html += '<div>';
                    html += '<strong style="font-size:14px;">' + tool.local_name + '</strong>';
                    if (tool.description) {
                        html += '<br><span class="description" style="font-size:12px;color:#666;">' + tool.description + '</span>';
                    }
                    html += '</div>';
                    html += '</label>';
                    html += '</div>';
                });
            }
            
            $('#aichat-mcp-manage-tools-list').html(html);
        }

        // Enable All
        $('#aichat-mcp-enable-all').on('click', function() {
            $('.mcp-tool-checkbox').prop('checked', true);
        });

        // Disable All
        $('#aichat-mcp-disable-all').on('click', function() {
            $('.mcp-tool-checkbox').prop('checked', false);
        });

        // Save tools status
        $('#aichat-mcp-save-tools-status').on('click', function() {
            const serverId = $('#aichat-mcp-manage-server-select').val();
            
            if (!serverId) {
                alert('<?php echo esc_js( __( 'Please select a server first.', 'axiachat-ai' ) ); ?>');
                return;
            }

            const toolsStatus = {};
            $('.mcp-tool-checkbox').each(function() {
                const toolName = $(this).data('tool');
                toolsStatus[toolName] = $(this).is(':checked') ? 1 : 0;
            });

            const statusEl = $('#aichat-mcp-save-status');
            statusEl.html('<span class="spinner is-active" style="float:none;"></span>');

            $.post(ajaxurl, {
                action: 'aichat_mcp_save_tools_status',
                nonce: '<?php echo esc_js( wp_create_nonce( 'aichat_mcp_nonce' ) ); ?>',
                server_id: serverId,
                tools_status: JSON.stringify(toolsStatus)
            }, function(response) {
                if (response.success) {
                    statusEl.html('<span class="dashicons dashicons-yes" style="color:#46b450;"></span> ' + response.data.message);
                    setTimeout(function() {
                        statusEl.html('');
                    }, 3000);
                } else {
                    statusEl.html('<span class="dashicons dashicons-dismiss" style="color:#dc3232;"></span> <?php echo esc_js( __( 'Error saving.', 'axiachat-ai' ) ); ?>');
                }
            });
        });
        
        // Auto-test if coming from save (new server)
        const urlParams = new URLSearchParams(window.location.search);
        const testServerId = urlParams.get('test');
        if (testServerId) {
            setTimeout(function() {
                $(`.aichat-mcp-test[data-server-id="${testServerId}"]`).click();
                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname + '?page=aichat-mcp-servers');
            }, 500);
        }
    });
    </script>
    <?php
}
