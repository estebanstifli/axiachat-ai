<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Página de settings para Configuración y Mantenimiento de Contextos
function aichat_contexto_settings_page() {
    global $wpdb;
    $contexts = $wpdb->get_results(
        "SELECT id, name, processing_progress FROM {$wpdb->prefix}aichat_contexts",
        ARRAY_A
    );
    ?>
    <div class="wrap aichat-admin">
        <h1 class="mb-3"><?php echo esc_html( __( 'Context Settings', 'aichat' ) ); ?></h1>

        <!-- Pestañas -->
        <ul class="nav nav-tabs mb-4" id="context-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="context-tab" data-bs-toggle="tab" data-bs-target="#context"
                        type="button" role="tab" aria-controls="context" aria-selected="true">
                    <i class="bi bi-gear"></i> <?php esc_html_e('Context', 'aichat'); ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-create' ) ); ?>" role="tab">
                    <i class="bi bi-plus-circle"></i> <?php esc_html_e('Add New', 'aichat'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-pdf' ) ); ?>" role="tab">
                    <i class="bi bi-file-earmark-arrow-up"></i> <?php esc_html_e('Import PDF/Data', 'aichat'); ?>
                </a>
            </li>
        </ul>

        <!-- Contenido de las pestañas -->
        <div class="tab-content" id="context-tab-content">
            <div class="tab-pane fade show active" id="context" role="tabpanel" aria-labelledby="context-tab">

                <!-- Card: Existing Contexts -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">
                            <i class="bi bi-layers"></i> <?php esc_html_e( 'Existing Contexts', 'aichat' ); ?>
                        </h5>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-create' ) ); ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus-lg"></i> <?php esc_html_e('Create New', 'aichat'); ?>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="aichat-contexts-table" class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" id="id"><?php esc_html_e( 'ID', 'aichat' ); ?></th>
                                        <th scope="col" id="name"><?php esc_html_e( 'Name', 'aichat' ); ?></th>
                                        <th scope="col" id="progress" class="w-25"><?php esc_html_e( 'Progress', 'aichat' ); ?></th>
                                        <th scope="col" id="actions" class="text-end"><?php esc_html_e( 'Actions', 'aichat' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="aichat-contexts-body">
                                    <?php if ( ! empty( $contexts ) ) : ?>
                                        <?php foreach ($contexts as $context) : ?>
                                            <tr>
                                                <td class="text-muted"><?php echo esc_html($context['id']); ?></td>
                                                <td>
                                                    <span class="context-name fw-semibold" data-id="<?php echo esc_attr($context['id']); ?>">
                                                        <i class="bi bi-folder2"></i>
                                                        <?php echo esc_html($context['name']); ?>
                                                    </span>
                                                    <input type="text"
                                                           class="form-control form-control-sm edit-name mt-2"
                                                           style="display:none;"
                                                           data-id="<?php echo esc_attr($context['id']); ?>"
                                                           value="<?php echo esc_attr($context['name']); ?>">
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 16px;">
                                                        <div class="progress-bar"
                                                             role="progressbar"
                                                             style="width: <?php echo esc_attr($context['processing_progress']); ?>%;"
                                                             aria-valuenow="<?php echo esc_attr($context['processing_progress']); ?>"
                                                             aria-valuemin="0" aria-valuemax="100">
                                                            <?php echo (int)$context['processing_progress']; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group" role="group" aria-label="Actions">
                                                        <!-- mantenemos las clases originales para no romper tu JS -->
                                                        <button type="button" class="button btn btn-sm btn-outline-secondary edit-context" data-id="<?php echo esc_attr($context['id']); ?>">
                                                            <i class="bi bi-pencil-square"></i> <?php esc_html_e( 'Edit', 'aichat' ); ?>
                                                        </button>
                                                        <button type="button" class="button btn btn-sm btn-outline-danger delete-context" data-id="<?php echo esc_attr($context['id']); ?>">
                                                            <i class="bi bi-trash"></i> <?php esc_html_e( 'Delete', 'aichat' ); ?>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="bi bi-inboxes"></i>
                                                <?php esc_html_e( 'No contexts yet. Create one in the “Add New” tab.', 'aichat' ); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white small text-muted">
                        <i class="bi bi-info-circle"></i>
                        <?php esc_html_e( 'Progress updates automatically when indexing runs from Add New or via cron.', 'aichat' ); ?>
                    </div>
                </div>

            </div><!-- /.tab-pane -->
        </div><!-- /.tab-content -->
    </div>
    <?php
}
