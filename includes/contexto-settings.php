<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Página de settings para Configuración y Mantenimiento de Contextos
function aichat_contexto_settings_page() {
    global $wpdb;
    $contexts = $wpdb->get_results(
        "SELECT c.id, c.name, c.processing_progress, c.processing_status, c.created_at,
                (SELECT COUNT(*) FROM {$wpdb->prefix}aichat_chunks ch WHERE ch.id_context=c.id) AS chunk_count,
                (SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}aichat_chunks ch2 WHERE ch2.id_context=c.id) AS post_count
         FROM {$wpdb->prefix}aichat_contexts c",
        ARRAY_A
    );
    ?>
    <div class="wrap aichat-admin">
    <h1 class="mb-3"><?php echo esc_html( __( 'Context Settings', 'ai-chat' ) ); ?></h1>

        <!-- Pestañas -->
        <ul class="nav nav-tabs mb-4" id="context-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="context-tab" data-bs-toggle="tab" data-bs-target="#context"
                        type="button" role="tab" aria-controls="context" aria-selected="true">
                    <i class="bi bi-gear"></i> <?php esc_html_e('Context', 'ai-chat'); ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-create' ) ); ?>" role="tab">
                    <i class="bi bi-plus-circle"></i> <?php esc_html_e('Add New', 'ai-chat'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-pdf' ) ); ?>" role="tab">
                    <i class="bi bi-file-earmark-arrow-up"></i> <?php esc_html_e('Import PDF/Data', 'ai-chat'); ?>
                </a>
            </li>
        </ul>

        <!-- Contenido de las pestañas -->
        <div class="tab-content" id="context-tab-content">
            <div class="tab-pane fade show active" id="context" role="tabpanel" aria-labelledby="context-tab">

                <!-- Card: Existing Contexts -->
                <div class="card card100 shadow-sm border-0">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">
                            <i class="bi bi-layers"></i> <?php esc_html_e( 'Existing Contexts', 'ai-chat' ); ?>
                        </h5>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-create' ) ); ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus-lg"></i> <?php esc_html_e('Create New', 'ai-chat'); ?>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="aichat-contexts-table" class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?php esc_html_e('ID','ai-chat'); ?></th>
                                        <th><?php esc_html_e('Name','ai-chat'); ?></th>
                                        <th><?php esc_html_e('Chunks','ai-chat'); ?></th>
                                        <th><?php esc_html_e('Posts','ai-chat'); ?></th>
                                        <th><?php esc_html_e('Created / Status','ai-chat'); ?></th>
                                        <th class="w-15"><?php esc_html_e('Progress','ai-chat'); ?></th>
                                        <th class="text-end"><?php esc_html_e('Actions','ai-chat'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="aichat-contexts-body">
                            <?php if ( ! empty( $contexts ) ) : foreach( $contexts as $context ): ?>
                                <tr>
                                    <td class="text-muted small"><?php echo esc_html($context['id']); ?></td>
                                    <td>
                                        <span class="context-name fw-semibold" data-id="<?php echo esc_attr($context['id']); ?>">
                                            <i class="bi bi-folder2"></i> <?php echo esc_html($context['name']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?php echo (int)$context['chunk_count']; ?></td>
                                    <td class="text-muted small"><?php echo (int)$context['post_count']; ?></td>
                                    <td class="text-muted small"><?php echo esc_html($context['created_at']); ?> / <?php echo esc_html($context['processing_status']); ?></td>
                                    <td>
                                        <div class="progress" style="height:14px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo esc_attr($context['processing_progress']); ?>%;" aria-valuenow="<?php echo esc_attr($context['processing_progress']); ?>" aria-valuemin="0" aria-valuemax="100"><?php echo (int)$context['processing_progress']; ?>%</div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="button btn btn-sm btn-outline-secondary edit-context" data-id="<?php echo esc_attr($context['id']); ?>">
                                                <i class="bi bi-pencil-square"></i> <?php esc_html_e('Edit/Test','ai-chat'); ?>
                                            </button>
                                            <button type="button" class="button btn btn-sm btn-outline-danger delete-context" data-id="<?php echo esc_attr($context['id']); ?>">
                                                <i class="bi bi-trash"></i> <?php esc_html_e('Delete','ai-chat'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-inboxes"></i> <?php esc_html_e('No contexts yet. Create one in the “Add New” tab.','ai-chat'); ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white small text-muted">
                        <i class="bi bi-info-circle"></i> <?php esc_html_e('Progress updates automatically when indexing runs from Add New or via cron.','ai-chat'); ?>
                    </div>
                </div>

            </div><!-- /.tab-pane -->
            
                        <!-- Redesigned Context Edit / Test Panel -->
                        <div id="aichat-context-test-wrapper" class="mt-4" style="display:none;">
                            <div class="card card100 shadow-sm border-0">
                                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                                    <h5 class="mb-0" id="aichat-context-panel-title"><i class="bi bi-folder2"></i> <?php esc_html_e('Context','ai-chat'); ?> <span class="text-muted" id="aichat-context-panel-name"></span></h5>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="aichat-close-test" aria-label="Close">&times;</button>
                                </div>
                                <div class="card-body">
                                    <!-- Settings Section -->
                                    <div id="aichat-context-meta" class="mb-3" style="display:none;">
                                        <form id="aichat-context-rename-form" onsubmit="return false;">
                                            <!-- Name -->
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold mb-1" for="aichat-edit-context-name"><?php esc_html_e('Name','ai-chat'); ?></label>
                                                <input type="text" id="aichat-edit-context-name" class="form-control form-control-sm" />
                                            </div>
                                            <!-- AutoSync Toggle -->
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold mb-1 d-flex align-items-center gap-1" for="aichat-autosync-toggle">AutoSync <span class="text-muted small" data-bs-toggle="tooltip" title="<?php esc_attr_e('Keeps the context updated with site content changes','ai-chat'); ?>"><i class="bi bi-question-circle"></i></span></label>
                                                <div class="mb-1">
                                                    <label class="d-flex align-items-center gap-2 m-0">
                                                        <input type="checkbox" id="aichat-autosync-toggle" />
                                                        <span class="small"><?php esc_html_e('Enable','ai-chat'); ?></span>
                                                    </label>
                                                </div>
                                                <div class="small text-muted" style="max-width:520px;">
                                                    <?php esc_html_e('If enabled, modified items are periodically re-embedded to keep answers fresh.','ai-chat'); ?>
                                                </div>
                                            </div>
                                            <!-- AutoSync Mode -->
                                            <div class="mb-3" id="aichat-autosync-mode-wrapper" style="display:none;">
                                                <label class="form-label fw-semibold mb-1" for="aichat-autosync-mode"><?php esc_html_e('AutoSync Mode','ai-chat'); ?></label>
                                                <select id="aichat-autosync-mode" class="form-select form-select-sm">
                                                    <option value="updates"><?php esc_html_e('Only update existing','ai-chat'); ?></option>
                                                    <option value="updates_and_new"><?php esc_html_e('Update + add new','ai-chat'); ?></option>
                                                </select>
                                                <div class="form-text small" id="aichat-autosync-mode-help">
                                                    <?php esc_html_e('"Only update existing" re-embeds items already present. "Update + add new" also discovers and indexes newly published content from ALL sources.','ai-chat'); ?>
                                                </div>
                                                <div class="form-text small text-warning" id="aichat-autosync-mode-restricted" style="display:none;">
                                                    <?php esc_html_e('This context is limited; only updating existing content is allowed.','ai-chat'); ?>
                                                </div>
                                            </div>
                                            <!-- Save Button -->
                                            <div class="mb-2 d-flex justify-content-end">
                                                <button class="btn btn-sm btn-primary" id="aichat-save-context-name"><i class="bi bi-save"></i> <?php esc_html_e('Save Changes','ai-chat'); ?></button>
                                            </div>
                                        </form>
                                    </div>
                                    <hr class="my-4" />
                                    <!-- Semantic Test Section -->
                                    <div class="d-flex align-items-center mb-2">
                                        <h6 class="mb-0 me-2"><i class="bi bi-search"></i> <?php esc_html_e('Similarity Test','ai-chat'); ?></h6>
                                        <span class="small text-muted"><?php esc_html_e('Run a query to inspect top matching chunks.','ai-chat'); ?></span>
                                    </div>
                                    <form id="aichat-context-test-form" class="row g-2 align-items-center mb-3" onsubmit="return false;">
                                        <div class="col-md-7">
                                            <input type="text" class="form-control" id="aichat-test-query" placeholder="<?php echo esc_attr(__('Example: shipping costs for returns', 'ai-chat')); ?>" />
                                        </div>
                                        <div class="col-md-2">
                                            <select id="aichat-test-limit" class="form-select form-select-sm">
                                                <option value="5">Top 5</option>
                                                <option value="10" selected>Top 10</option>
                                                <option value="15">Top 15</option>
                                                <option value="20">Top 20</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 d-grid">
                                            <button id="aichat-run-test" class="btn btn-outline-primary"><i class="bi bi-search"></i> <?php esc_html_e('Run Search','ai-chat'); ?></button>
                                        </div>
                                    </form>
                                    <div id="aichat-test-status" class="small text-muted mb-2" style="display:none;"></div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0" id="aichat-test-results" style="display:none;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:70px;">Score</th>
                                                    <th><?php esc_html_e('Title', 'ai-chat'); ?></th>
                                                    <th><?php esc_html_e('Excerpt', 'ai-chat'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
        </div><!-- /.tab-content -->
    </div>
    <?php
}
