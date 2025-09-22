<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function aichat_contexto_create_page() {
    $select_posts_mode    = get_option( 'aichat_select_posts_mode', '' );
    $select_pages_mode    = get_option( 'aichat_select_pages_mode', '' );
    $select_products_mode = get_option( 'aichat_select_products_mode', '' );
    $select_uploaded_mode = get_option( 'aichat_select_uploaded_mode', '' );
    ?>
    <div class="wrap">
        
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-diagram-3 fs-3 me-2 text-primary"></i>
            <h1 class="m-0"><?php echo esc_html( __( 'Create Context', 'aichat' ) ); ?></h1>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="create-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-settings' ) ); ?>" role="tab">
                    <i class="bi bi-gear me-1"></i> Context
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="add-new-tab" data-bs-toggle="tab" data-bs-target="#add-new" type="button" role="tab" aria-controls="add-new" aria-selected="true">
                    <i class="bi bi-plus-circle me-1"></i> Add New
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-pdf' ) ); ?>" role="tab">
                    <i class="bi bi-filetype-pdf me-1"></i> Import PDF/Data
                </a>
            </li>
        </ul>

        <div class="tab-content" id="create-tab-content">
            <div class="tab-pane fade show active" id="add-new" role="tabpanel" aria-labelledby="add-new-tab">

                <!-- Card: Basic Settings -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-sliders me-2 text-secondary"></i><?php esc_html_e( 'Basic Settings', 'aichat' ); ?>
                        </h5>

                        <div class="row g-3">
                            <!-- Context Name -->
                            <div class="col-12 col-lg-6">
                                <label for="context-name" class="form-label fw-semibold">
                                    <i class="bi bi-tag me-1"></i><?php esc_html_e( 'Context Name', 'aichat' ); ?>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-pencil-square"></i></span>
                                    <input type="text" id="context-name" name="context_name" class="form-control" placeholder="<?php esc_attr_e( 'Enter context name', 'aichat' ); ?>" required>
                                </div>
                                <div class="form-text"><?php esc_html_e( 'If left empty, a default name (e.g., Default1) will be assigned.', 'aichat' ); ?></div>
                            </div>

                            <!-- Context Type -->
                            <div class="col-12 col-lg-6">
                                <label for="context-type" class="form-label fw-semibold">
                                    <i class="bi bi-hdd-network me-1"></i><?php esc_html_e( 'Context Type', 'aichat' ); ?>
                                </label>
                                <select id="context-type" name="context_type" class="form-select">
                                    <option value="local"><?php esc_html_e( 'Local', 'aichat' ); ?></option>
                                    <option value="remoto"><?php esc_html_e( 'Remote', 'aichat' ); ?></option>
                                </select>
                                <div class="form-text"><?php esc_html_e( 'Choose where to store the context data.', 'aichat' ); ?></div>
                            </div>
                        </div>

                        <!-- Remote config (la muestra tu JS según selección) -->
                        <div id="remote-config-fields" class="border rounded p-3 mt-3" style="display:none;">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label for="remote-type" class="form-label fw-semibold">
                                        <i class="bi bi-cloud-arrow-up me-1"></i><?php esc_html_e( 'Remote Type', 'aichat' ); ?>
                                    </label>
                                    <select id="remote-type" name="remote_type" class="form-select">
                                        <option value="pinecone"><?php esc_html_e( 'Pinecone', 'aichat' ); ?></option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="remote-api-key" class="form-label fw-semibold">
                                        <i class="bi bi-key me-1"></i><?php esc_html_e( 'API Key', 'aichat' ); ?>
                                    </label>
                                    <input type="text" id="remote-api-key" name="remote_api_key" class="form-control" placeholder="<?php esc_attr_e( 'Enter Pinecone API Key', 'aichat' ); ?>">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="remote-endpoint" class="form-label fw-semibold">
                                        <i class="bi bi-link-45deg me-1"></i><?php esc_html_e( 'Endpoint', 'aichat' ); ?>
                                    </label>
                                    <input type="text" id="remote-endpoint" name="remote_endpoint" class="form-control" placeholder="<?php esc_attr_e( 'Enter Pinecone Endpoint', 'aichat' ); ?>" value="https://controller.pinecone.io">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Sources -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-check2-square me-2 text-secondary"></i><?php esc_html_e( 'Select Sources', 'aichat' ); ?>
                        </h5>

                        <!-- POSTS -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-file-text me-2 text-primary"></i>
                                <span class="fw-semibold"><?php esc_html_e( 'Posts', 'aichat' ); ?></span>
                            </div>
                            <div class="d-flex gap-3 mb-2">
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_posts_mode" value="all" <?php checked( $select_posts_mode, 'all' ); ?>>
                                    <span>All Posts</span>
                                </label>
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_posts_mode" value="custom" <?php checked( $select_posts_mode, 'custom' ); ?>>
                                    <span>Custom Posts</span>
                                </label>
                            </div>

                            <div class="accordion" id="aichat-post-accordion" style="<?php echo $select_posts_mode === 'custom' ? '' : 'display:none;'; ?>">
                                <div class="accordion-item" data-post-type="post">
                                    <h2 class="accordion-header" id="heading-post">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-post" aria-expanded="false" aria-controls="collapse-post">
                                            <?php esc_html_e( 'Posts', 'aichat' ); ?>
                                        </button>
                                    </h2>
                                    <div id="collapse-post" class="accordion-collapse collapse" aria-labelledby="heading-post" data-bs-parent="#aichat-post-accordion">
                                        <div class="accordion-body">
                                            <ul class="nav nav-tabs mb-3" id="tabs-post" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" id="recent-tab-post" data-bs-toggle="tab" data-bs-target="#recent-post" type="button" role="tab" aria-controls="recent-post" aria-selected="true">
                                                        <i class="bi bi-clock-history me-1"></i><?php esc_html_e( 'Most Recent', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="all-tab-post" data-bs-toggle="tab" data-bs-target="#all-post" type="button" role="tab" aria-controls="all-post" aria-selected="false">
                                                        <i class="bi bi-card-list me-1"></i><?php esc_html_e( 'View All', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="search-tab-post" data-bs-toggle="tab" data-bs-target="#search-post" type="button" role="tab" aria-controls="search-post" aria-selected="false">
                                                        <i class="bi bi-search me-1"></i><?php esc_html_e( 'Search', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                            </ul>
                                            <div class="tab-content" id="tabs-content-post">
                                                <div class="tab-pane fade show active" id="recent-post" role="tabpanel" aria-labelledby="recent-tab-post">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#recent-items-post"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="recent-items-post" class="aichat-items"></div>
                                                </div>
                                                <div class="tab-pane fade" id="all-post" role="tabpanel" aria-labelledby="all-tab-post">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#all-items-post"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="all-items-post" class="aichat-items"></div>
                                                    <div id="all-pagination-post" class="aichat-pagination mt-2"></div>
                                                </div>
                                                <div class="tab-pane fade" id="search-post" role="tabpanel" aria-labelledby="search-tab-post">
                                                    <div class="input-group mb-2">
                                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                        <input type="text" id="search-input-post" class="form-control aichat-search-input" placeholder="<?php esc_attr_e('Search…', 'aichat'); ?>">
                                                    </div>
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#search-items-post"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="search-items-post" class="aichat-items"></div>
                                                    <div id="search-pagination-post" class="aichat-pagination mt-2"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PAGES -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                                <span class="fw-semibold"><?php esc_html_e( 'Pages', 'aichat' ); ?></span>
                            </div>
                            <div class="d-flex gap-3 mb-2">
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_pages_mode" value="all" <?php checked( $select_pages_mode, 'all' ); ?>>
                                    <span>All Pages</span>
                                </label>
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_pages_mode" value="custom" <?php checked( $select_pages_mode, 'custom' ); ?>>
                                    <span>Custom Pages</span>
                                </label>
                            </div>

                            <div class="accordion" id="aichat-page-accordion" style="<?php echo $select_pages_mode === 'custom' ? '' : 'display:none;'; ?>">
                                <div class="accordion-item" data-post-type="page">
                                    <h2 class="accordion-header" id="heading-page">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-page" aria-expanded="false" aria-controls="collapse-page">
                                            <?php esc_html_e( 'Pages', 'aichat' ); ?>
                                        </button>
                                    </h2>
                                    <div id="collapse-page" class="accordion-collapse collapse" aria-labelledby="heading-page" data-bs-parent="#aichat-page-accordion">
                                        <div class="accordion-body">
                                            <ul class="nav nav-tabs mb-3" id="tabs-page" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" id="recent-tab-page" data-bs-toggle="tab" data-bs-target="#recent-page" type="button" role="tab" aria-controls="recent-page" aria-selected="true">
                                                        <i class="bi bi-clock-history me-1"></i><?php esc_html_e( 'Most Recent', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="all-tab-page" data-bs-toggle="tab" data-bs-target="#all-page" type="button" role="tab" aria-controls="all-page" aria-selected="false">
                                                        <i class="bi bi-card-list me-1"></i><?php esc_html_e( 'View All', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="search-tab-page" data-bs-toggle="tab" data-bs-target="#search-page" type="button" role="tab" aria-controls="search-page" aria-selected="false">
                                                        <i class="bi bi-search me-1"></i><?php esc_html_e( 'Search', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                            </ul>
                                            <div class="tab-content" id="tabs-content-page">
                                                <div class="tab-pane fade show active" id="recent-page" role="tabpanel" aria-labelledby="recent-tab-page">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#recent-items-page"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="recent-items-page" class="aichat-items"></div>
                                                </div>
                                                <div class="tab-pane fade" id="all-page" role="tabpanel" aria-labelledby="all-tab-page">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#all-items-page"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="all-items-page" class="aichat-items"></div>
                                                    <div id="all-pagination-page" class="aichat-pagination mt-2"></div>
                                                </div>
                                                <div class="tab-pane fade" id="search-page" role="tabpanel" aria-labelledby="search-tab-page">
                                                    <div class="input-group mb-2">
                                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                        <input type="text" id="search-input-page" class="form-control aichat-search-input" placeholder="<?php esc_attr_e('Search…', 'aichat'); ?>">
                                                    </div>
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#search-items-page"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="search-items-page" class="aichat-items"></div>
                                                    <div id="search-pagination-page" class="aichat-pagination mt-2"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PRODUCTS -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-bag-check me-2 text-primary"></i>
                                <span class="fw-semibold"><?php esc_html_e( 'Products', 'aichat' ); ?></span>
                            </div>
                            <div class="d-flex gap-3 mb-2">
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_products_mode" value="all" <?php checked( $select_products_mode, 'all' ); ?>>
                                    <span>All Products</span>
                                </label>
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_products_mode" value="custom" <?php checked( $select_products_mode, 'custom' ); ?>>
                                    <span>Custom Products</span>
                                </label>
                            </div>

                            <div class="accordion" id="aichat-product-accordion" style="<?php echo ($select_products_mode === 'custom' && class_exists('WooCommerce')) ? '' : 'display:none;'; ?>">
                                <div class="accordion-item" data-post-type="product">
                                    <h2 class="accordion-header" id="heading-product">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-product" aria-expanded="false" aria-controls="collapse-product">
                                            <?php esc_html_e( 'Products', 'aichat' ); ?>
                                        </button>
                                    </h2>
                                    <div id="collapse-product" class="accordion-collapse collapse" aria-labelledby="heading-product" data-bs-parent="#aichat-product-accordion">
                                        <div class="accordion-body">
                                            <ul class="nav nav-tabs mb-3" id="tabs-product" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" id="recent-tab-product" data-bs-toggle="tab" data-bs-target="#recent-product" type="button" role="tab" aria-controls="recent-product" aria-selected="true">
                                                        <i class="bi bi-clock-history me-1"></i><?php esc_html_e( 'Most Recent', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="all-tab-product" data-bs-toggle="tab" data-bs-target="#all-product" type="button" role="tab" aria-controls="all-product" aria-selected="false">
                                                        <i class="bi bi-card-list me-1"></i><?php esc_html_e( 'View All', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="search-tab-product" data-bs-toggle="tab" data-bs-target="#search-product" type="button" role="tab" aria-controls="search-product" aria-selected="false">
                                                        <i class="bi bi-search me-1"></i><?php esc_html_e( 'Search', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                            </ul>
                                            <div class="tab-content" id="tabs-content-product">
                                                <div class="tab-pane fade show active" id="recent-product" role="tabpanel" aria-labelledby="recent-tab-product">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#recent-items-product"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="recent-items-product" class="aichat-items"></div>
                                                </div>
                                                <div class="tab-pane fade" id="all-product" role="tabpanel" aria-labelledby="all-tab-product">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#all-items-product"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="all-items-product" class="aichat-items"></div>
                                                    <div id="all-pagination-product" class="aichat-pagination mt-2"></div>
                                                </div>
                                                <div class="tab-pane fade" id="search-product" role="tabpanel" aria-labelledby="search-tab-product">
                                                    <div class="input-group mb-2">
                                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                        <input type="text" id="search-input-product" class="form-control aichat-search-input" placeholder="<?php esc_attr_e('Search…', 'aichat'); ?>">
                                                    </div>
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#search-items-product"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="search-items-product" class="aichat-items"></div>
                                                    <div id="search-pagination-product" class="aichat-pagination mt-2"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- UPLOADED FILES (aichat_upload) -->
                        <div class="mb-2">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-cloud-upload me-2 text-primary"></i>
                                <span class="fw-semibold"><?php esc_html_e( 'Uploaded Files', 'aichat' ); ?></span>
                                <a class="btn btn-sm btn-outline-secondary ms-2" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-pdf' ) ); ?>">
                                    <i class="bi bi-upload me-1"></i><?php esc_html_e('Open Import PDF/Data', 'aichat'); ?>
                                </a>
                            </div>
                            <div class="d-flex gap-3 mb-2">
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_uploaded_mode" value="all" <?php checked( $select_uploaded_mode, 'all' ); ?>>
                                    <span>All Uploaded Files</span>
                                </label>
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_uploaded_mode" value="custom" <?php checked( $select_uploaded_mode, 'custom' ); ?>>
                                    <span>Custom Uploaded Files</span>
                                </label>
                            </div>

                            <div class="accordion" id="aichat-uploaded-accordion" style="<?php echo $select_uploaded_mode === 'custom' ? '' : 'display:none;'; ?>">
                                <div class="accordion-item" data-post-type="aichat_upload">
                                    <h2 class="accordion-header" id="heading-uploaded">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-aichat_upload" aria-expanded="false" aria-controls="collapse-aichat_upload">
                                            <?php esc_html_e( 'Uploaded Files', 'aichat' ); ?>
                                        </button>
                                    </h2>
                                    <div id="collapse-aichat_upload" class="accordion-collapse collapse" aria-labelledby="heading-uploaded" data-bs-parent="#aichat-uploaded-accordion">
                                        <div class="accordion-body">
                                            <ul class="nav nav-tabs mb-3" id="tabs-aichat_upload" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" id="recent-tab-aichat_upload" data-bs-toggle="tab" data-bs-target="#recent-aichat_upload" type="button" role="tab" aria-controls="recent-aichat_upload" aria-selected="true">
                                                        <i class="bi bi-clock-history me-1"></i><?php esc_html_e( 'Most Recent', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="all-tab-aichat_upload" data-bs-toggle="tab" data-bs-target="#all-aichat_upload" type="button" role="tab" aria-controls="all-aichat_upload" aria-selected="false">
                                                        <i class="bi bi-card-list me-1"></i><?php esc_html_e( 'View All', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="search-tab-aichat_upload" data-bs-toggle="tab" data-bs-target="#search-aichat_upload" type="button" role="tab" aria-controls="search-aichat_upload" aria-selected="false">
                                                        <i class="bi bi-search me-1"></i><?php esc_html_e( 'Search', 'aichat' ); ?>
                                                    </button>
                                                </li>
                                            </ul>

                                            <div class="tab-content" id="tabs-content-aichat_upload">
                                                <div class="tab-pane fade show active" id="recent-aichat_upload" role="tabpanel" aria-labelledby="recent-tab-aichat_upload">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#recent-items-aichat_upload"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="recent-items-aichat_upload" class="aichat-items"></div>
                                                </div>

                                                <div class="tab-pane fade" id="all-aichat_upload" role="tabpanel" aria-labelledby="all-tab-aichat_upload">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#all-items-aichat_upload"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="all-items-aichat_upload" class="aichat-items"></div>
                                                    <div id="all-pagination-aichat_upload" class="aichat-pagination mt-2"></div>
                                                </div>

                                                <div class="tab-pane fade" id="search-aichat_upload" role="tabpanel" aria-labelledby="search-tab-aichat_upload">
                                                    <div class="input-group mb-2">
                                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                        <input type="text" id="search-input-aichat_upload" class="form-control aichat-search-input" placeholder="<?php esc_attr_e('Search…', 'aichat'); ?>">
                                                    </div>
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#search-items-aichat_upload"> <?php esc_html_e('Select All These', 'aichat'); ?>
                                                    </label>
                                                    <div id="search-items-aichat_upload" class="aichat-items"></div>
                                                    <div id="search-pagination-aichat_upload" class="aichat-pagination mt-2"></div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-text"><?php esc_html_e('Select the parent items (each uploaded file). Internal logic will process their chunks automatically.', 'aichat'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Card: Process -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-cpu me-2 text-secondary"></i><?php esc_html_e( 'CREATE CONTEXT', 'aichat' ); ?>
                        </h5>

                        <div class="alert alert-info d-flex align-items-center py-2" role="alert">
                            <i class="bi bi-info-circle me-2"></i>
                            <div id="aichat-selection-summary">No selections yet.</div>
                        </div>

                        <button type="button" id="aichat-process-context" class="button button-primary btn btn-primary">
                            <i class="bi bi-play-circle me-1"></i><?php esc_html_e( 'PROCESS', 'aichat' ); ?>
                        </button>

                        <div class="progress mt-3" style="height: 22px;">
                            <div class="progress-bar" id="aichat-progress-bar" role="progressbar"
                                 style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                0%
                            </div>
                        </div>

                        <div id="aichat-index-log" class="mt-3 p-2 border rounded bg-light" style="height: 240px; overflow-y: auto; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 12px;"></div>
                    </div>
                </div>

            </div><!-- /tab-pane -->
        </div><!-- /tab-content -->
    </div><!-- /wrap -->
    <?php
}
