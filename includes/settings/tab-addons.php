<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add-ons tab markup for AI Chat settings.
 * Uses variables prepared in parent scope: $connect_option, $connect_install_url,
 * $connect_install_required, $ai_tools_enabled_flag, $connect_active,
 * $connect_installed, $connect_version.
 */
?>
<div class="tab-pane" id="aichat-tab-addons" role="tabpanel" aria-labelledby="aichat-tab-link-addons" aria-hidden="true">
    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-purple text-white d-flex align-items-center" style="background:#6f42c1;">
                    <i class="bi bi-puzzle-fill me-2"></i><strong><?php echo esc_html__( 'Add-ons', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <div class="aichat-checkbox-row mb-3">
                        <input type="hidden" name="aichat_addon_ai_tools_enabled" value="0" />
                        <label for="aichat_addon_ai_tools_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_addon_ai_tools_enabled" name="aichat_addon_ai_tools_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_addon_ai_tools_enabled' ), 1 ); ?> />
                            <span><?php echo esc_html__( 'Enable AI Tools (tools & macros system)', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0"><?php echo esc_html__( 'When enabled, exposes the AI Tools menus and allows bots to call registered tools/macros.', 'axiachat-ai' ); ?></div>
                    </div>
                    <?php $ai_tools_enabled_flag = (int) aichat_get_setting( 'aichat_addon_ai_tools_enabled' ); ?>
                    <div class="aichat-checkbox-row mb-0">
                        <input type="hidden" name="aichat_tools_ssa_enabled" value="0" />
                        <label for="aichat_tools_ssa_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_tools_ssa_enabled" name="aichat_tools_ssa_enabled" value="1" <?php checked( (int) get_option( 'aichat_tools_ssa_enabled', 0 ), 1 ); ?> <?php disabled( ! $ai_tools_enabled_flag ); ?> />
                            <span><?php echo esc_html__( 'Enable Simply Schedule Appointments (SSA) tools', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0">
                            <?php
                            if ( ! $ai_tools_enabled_flag ) {
                                echo esc_html__( 'Requires AI Tools enabled. Turn on AI Tools above to activate SSA integration.', 'axiachat-ai' );
                            } else {
                                echo esc_html__( 'Registers tools/macros to list services, check availability and create bookings. Requires the SSA plugin.', 'axiachat-ai' );
                            }
                            ?>
                        </div>
                    </div>
                    <hr class="my-3" />
                    <div class="aichat-checkbox-row mb-0">
                        <input type="hidden" name="aichat_addon_mcp_enabled" value="0" />
                        <label for="aichat_addon_mcp_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_addon_mcp_enabled" name="aichat_addon_mcp_enabled" value="1" <?php checked( (int) get_option( 'aichat_addon_mcp_enabled', 0 ), 1 ); ?> <?php disabled( ! $ai_tools_enabled_flag ); ?> />
                            <span><?php echo esc_html__( 'Enable MCP (Model Context Protocol)', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0">
                            <?php
                            if ( ! $ai_tools_enabled_flag ) {
                                echo esc_html__( 'Requires AI Tools enabled. Turn on AI Tools above to activate MCP integration.', 'axiachat-ai' );
                            } else {
                                echo esc_html__( 'Allows bots to connect to external MCP servers (Sentry, Notion, GitHub, etc.) and expose their tools. Configure servers in AI Chat → MCP Servers.', 'axiachat-ai' );
                            }
                            ?>
                        </div>
                    </div>
                    <hr class="my-3" />
                    <div class="aichat-checkbox-row mb-0">
                        <input type="hidden" name="aichat_addon_connect_enabled" value="0" />
                        <label for="aichat_addon_connect_enabled" class="aichat-checkbox-label">
                            <input type="checkbox"
                                id="aichat_addon_connect_enabled"
                                name="aichat_addon_connect_enabled"
                                value="1"
                                <?php checked( $connect_option, 1 ); ?>
                                <?php disabled( ! $ai_tools_enabled_flag ); ?>
                                data-guide-url="<?php echo esc_url( $connect_install_url ); ?>"
                                data-guide-required="<?php echo esc_attr( $connect_install_required ); ?>"
                                data-guide-message="<?php echo esc_attr__( 'Visit the installation guide for Andromeda Connect (WhatsApp & Telegram)?', 'axiachat-ai' ); ?>"
                            />
                            <span><?php echo esc_html__( 'Enable WhatsApp & Telegram (Andromeda Connect)', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0">
                            <?php
                            if ( ! $ai_tools_enabled_flag ) {
                                echo esc_html__( 'Requires AI Tools enabled. Turn on AI Tools above to manage external channel connectors.', 'axiachat-ai' );
                            } elseif ( $connect_active ) {
                                echo esc_html__( 'Andromeda Connect is active. WhatsApp and Telegram routing are available.', 'axiachat-ai' );
                                if ( $connect_version ) {
                                    echo ' ' . sprintf( esc_html__( '(Version %s detected)', 'axiachat-ai' ), esc_html( $connect_version ) );
                                }
                            } elseif ( $connect_installed ) {
                                echo esc_html__( 'Andromeda Connect files are present. Activate the plugin from Plugins → Installed Plugins or enable it here after activation.', 'axiachat-ai' );
                                if ( $connect_version ) {
                                    echo ' ' . sprintf( esc_html__( '(Version %s detected)', 'axiachat-ai' ), esc_html( $connect_version ) );
                                }
                            } else {
                                printf(
                                    '%s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                                    esc_html__( 'Companion plugin for WhatsApp & Telegram integration.', 'axiachat-ai' ),
                                    esc_url( 'https://github.com/estebanstifli/aichat-connect' ),
                                    esc_html__( 'Click to view installation guide', 'axiachat-ai' )
                                );
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
