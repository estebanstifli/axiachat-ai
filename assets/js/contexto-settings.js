jQuery(document).ready(function($) {
    // Toggle RAG
    $('#aichat_rag_enabled').on('change', function() {
        var enabled = $(this).is(':checked') ? 1 : 0;
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'aichat_toggle_rag',
                nonce: aichat_settings_ajax.nonce,
                enabled: enabled
            },
            success: function(response) {
                if (response.success) {
                    $('#aichat-message').text('RAG status updated.').css('color', 'green').show().fadeOut(3000);
                } else {
                    $('#aichat-message').text('Error updating RAG status.').show();
                }
            },
            error: function(xhr, status, error) {
                $('#aichat-message').text('Error: ' + error).show();
                console.log('AJAX Error:', xhr.responseText);
            }
        });
    });

    // Actualizar Active Context via AJAX
    $('#aichat_active_context').on('change', function() {
        var contextId = $(this).val();
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'aichat_update_active_context',
                nonce: aichat_settings_ajax.nonce,
                context_id: contextId
            },
            success: function(response) {
                if (response.success) {
                    $('#aichat-message').text('Active context updated.').css('color', 'green').show().fadeOut(3000);
                } else {
                    $('#aichat-message').text('Error updating active context.').show();
                }
            },
            error: function(xhr, status, error) {
                $('#aichat-message').text('Error: ' + error).show();
                console.log('AJAX Error:', xhr.responseText);
            }
        });
    });

    // Cargar contextos al iniciar
    function loadContexts() {
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: { action: 'aichat_load_contexts', nonce: aichat_settings_ajax.nonce },
            success: function(response) {
                if (!response.success) {
                    $('#aichat-message').text(response.data && response.data.message ? response.data.message : 'Error loading contexts').show();
                    return;
                }
                var tbody = $('#aichat-contexts-body');
                tbody.empty();
                var list = response.data.contexts || [];
                if(list.length===0){
                    tbody.append('<tr><td colspan="7" class="text-center py-4 text-muted">'+(aichat_settings_ajax.no_contexts || 'No contexts')+'</td></tr>');
                } else {
                    $.each(list, function(i,context){
                        var progress = parseInt(context.processing_progress || 0,10);
                        var createdStatus = (context.created_at ? context.created_at : '') + ' / ' + (context.processing_status || '');
                        tbody.append(
                            '<tr>'+
                              '<td class="text-muted small">'+context.id+'</td>'+
                              '<td><span class="context-name fw-semibold" data-id="'+context.id+'"><i class="bi bi-folder2"></i> '+escapeHtml(context.name||'')+'</span></td>'+
                              '<td class="text-muted small">'+(context.chunk_count||0)+'</td>'+
                              '<td class="text-muted small">'+(context.post_count||0)+'</td>'+
                              '<td class="text-muted small">'+escapeHtml(createdStatus)+'</td>'+
                              '<td><div class="progress" style="height:14px;"><div class="progress-bar" role="progressbar" style="width:'+progress+'%;" aria-valuenow="'+progress+'" aria-valuemin="0" aria-valuemax="100">'+progress+'%</div></div></td>'+
                              '<td class="text-end"><div class="btn-group" role="group">'+
                                  '<button class="button btn btn-sm btn-outline-secondary edit-context" data-id="'+context.id+'"><i class="bi bi-pencil-square"></i> '+(aichat_settings_ajax.edit_test || 'Edit/Test')+'</button> '+
                                  '<button class="button btn btn-sm btn-outline-danger delete-context" data-id="'+context.id+'"><i class="bi bi-trash"></i> '+aichat_settings_ajax.delete_text+'</button>'+
                              '</div></td>'+
                            '</tr>'
                        );
                    });
                }
                $('#aichat-message').text('').hide();
                startProgressUpdates();
            },
            error: function(xhr, status, error){
                $('#aichat-message').text('Error loading contexts: '+error).show();
                console.log('AJAX Error (loadContexts):', xhr.responseText);
            }
        });
    }

    // Función para actualizar el progreso
    function updateProgress() {
        $('table#aichat-contexts-table .progress-bar').each(function() {
            var $progressBar = $(this);
            var contextId = $progressBar.closest('tr').find('.context-name').data('id');
            $.ajax({
                url: aichat_settings_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'aichat_update_progress',
                    nonce: aichat_settings_ajax.nonce,
                    context_id: contextId
                },
                success: function(response) {
                    if (response.success) {
                        var progress = response.data.progress || 0;
                        $progressBar.css('width', progress + '%').attr('aria-valuenow', progress);
                    } else {
                        console.log('Error in AJAX response for context ' + contextId + ': ', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error for context ' + contextId + ': ' + error);
                    // Mantener el valor anterior si falla
                    $progressBar.css('width', $progressBar.attr('aria-valuenow') + '%');
                }
            });
        });
    }

    // Iniciar actualizaciones de progreso
    function startProgressUpdates() {
        updateProgress(); // Primera actualización inmediata
        setInterval(updateProgress, 10000); // Actualizar cada 10 segundos
    }

    // Cargar contextos al iniciar
    loadContexts();

    // Edit/Test: abrir panel meta + búsqueda
    $(document).on('click', '.edit-context', function() {
        var id = $(this).data('id');
        $('#aichat-context-test-wrapper').data('context-id', id);
        // Título provisional mientras llega meta
        $('#aichat-context-panel-name').text('#'+id);
        $('#aichat-test-results').hide();
        $('#aichat-test-status').hide();
        $('#aichat-context-meta').hide();
        $('#aichat-context-test-wrapper').slideDown(150);
        fetchContextMeta(id);
    });

    // Guardar nombre (botón dentro del panel)
    $(document).on('click', '#aichat-save-context-name', function(){
        var id = $('#aichat-context-test-wrapper').data('context-id');
        var newName = $('#aichat-edit-context-name').val().trim();
        var autosyncEnabled = $('#aichat-autosync-toggle').is(':checked') ? 1 : 0;
        var autosyncMode = $('#aichat-autosync-mode').val();
        if(!id) return false;
        if(newName===''){ alert('Name required'); return false; }
        var btn = $(this).prop('disabled', true);
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: { action: 'aichat_update_context_name', nonce: aichat_settings_ajax.nonce, id: id, name: newName, autosync: autosyncEnabled, autosync_mode: autosyncMode },
            success: function(r){
                if(r.success){
                    // Actualizar en la tabla visible
                    $('.context-name[data-id="'+id+'"]').text(newName);
                    $('#aichat-message').text(aichat_settings_ajax.updated_text || 'Updated').css('color','green').show().fadeOut(2500);
                    loadContexts();
                } else {
                    alert(r.data && r.data.message ? r.data.message : 'Error');
                }
            },
            error: function(){ alert('Request failed'); },
            complete: function(){ btn.prop('disabled', false); }
        });
        return false;
    });

    function fetchContextMeta(id){
        $('#aichat-context-meta').hide();
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: { action: 'aichat_get_context_meta', nonce: aichat_settings_ajax.nonce, id: id },
            success: function(resp){
                if(resp.success){
                    var c = resp.data.context;
                    $('#aichat-edit-context-name').val(c.name||'');
                    // Actualizar título principal: "Context <Name> (#ID)"
                    var displayName = c.name ? c.name : ('#'+c.id);
                    $('#aichat-context-panel-name').text(displayName + ' (#'+c.id+')');
                    // Autosync handling
                    var enabled = parseInt(c.autosync,10) === 1;
                    $('#aichat-autosync-toggle').prop('checked', enabled);
                    var postTypesRaw = c.autosync_post_types || '';
                    var isAllContext = /ALL_POSTS|ALL_PAGES|ALL_PRODUCTS/.test(postTypesRaw);
                    if(enabled){
                        $('#aichat-autosync-mode-wrapper').show();
                    } else {
                        $('#aichat-autosync-mode-wrapper').hide();
                    }
                    if (c.autosync_mode){
                        $('#aichat-autosync-mode').val(c.autosync_mode);
                    }
                    if(!isAllContext){
                        // Force updates only
                        $('#aichat-autosync-mode').val('updates');
                        $('#aichat-autosync-mode option[value="updates_and_new"]').prop('disabled', true);
                        $('#aichat-autosync-mode-restricted').show();
                    } else {
                        $('#aichat-autosync-mode option[value="updates_and_new"]').prop('disabled', false);
                        $('#aichat-autosync-mode-restricted').hide();
                    }
                    $('#aichat-context-meta').fadeIn(120);
                } else {
                    $('#aichat-meta-created').text('Error');
                }
            },
            error: function(){ $('#aichat-meta-created').text('Error'); }
        });
    }

    // Habilitar/deshabilitar select de modo cuando se cambia el switch
    $(document).on('change','#aichat-autosync-toggle', function(){
        var en = $(this).is(':checked');
        if(en){
            $('#aichat-autosync-mode-wrapper').slideDown(120);
        } else {
            $('#aichat-autosync-mode-wrapper').slideUp(120);
        }
    });

    // Borrar contexto
    $(document).on('click', '.delete-context', function() {
        if (confirm(aichat_settings_ajax.delete_confirm)) {
            var id = $(this).data('id');
            var row = $(this).closest('tr');
            console.log('Delete confirmed for ID:', id);

            $.ajax({
                url: aichat_settings_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'aichat_delete_context',
                    nonce: aichat_settings_ajax.nonce,
                    id: id
                },
                success: function(response) {
                    console.log('AJAX Success (delete):', response);
                    if (response.success) {
                        row.remove();
                        loadContexts();
                        $('#aichat-message').text(aichat_settings_ajax.deleted_text).css('color', 'green').show().fadeOut(3000);
                    } else {
                        $('#aichat-message').text(response.data.message).show();
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error (delete):', xhr.status, xhr.statusText, xhr.responseText);
                    $('#aichat-message').text('Error deleting context: ' + error).show();
                }
            });
        }
    });

    // Cerrar tarjeta de test
    $(document).on('click', '#aichat-close-test', function(){
        $('#aichat-context-test-wrapper').slideUp(120);
        $('#aichat-test-results').hide();
        $('#aichat-test-status').hide();
    });

    // Ejecutar búsqueda semántica de prueba
    $(document).on('click', '#aichat-run-test', function(){
        var ctxId = $('#aichat-context-test-wrapper').data('context-id') || 0;
        var q = $('#aichat-test-query').val().trim();
        var limit = $('#aichat-test-limit').val();
        if(!ctxId){
            alert('Select a context first (edit).');
            return false;
        }
        if(!q){
            $('#aichat-test-query').focus();
            return false;
        }
        var $status = $('#aichat-test-status');
        $status.text(aichat_settings_ajax.searching || 'Searching...').show();
        $('#aichat-test-results').hide();
        $('#aichat-test-results tbody').empty();
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'aichat_search_context_chunks',
                nonce: aichat_settings_ajax.nonce,
                context_id: ctxId,
                q: q,
                limit: limit
            },
            success: function(resp){
                if(resp.success){
                    var rows = resp.data.results || [];
                    if(rows.length === 0){
                        $status.text(aichat_settings_ajax.no_results || 'No results');
                        return;
                    }
                    $status.text(rows.length + ' result(s).');
                    var tbody = $('#aichat-test-results tbody');
                    $.each(rows, function(i,r){
                        var score = (r.score !== undefined) ? r.score.toFixed(4) : '';
                        var cls = (i===0) ? 'table-success' : '';
                        tbody.append('<tr class="'+cls+'">'
                          +'<td><code>'+score+'</code></td>'
                          +'<td>'+escapeHtml(r.title || '')+'</td>'
                          +'<td><small>'+escapeHtml(r.excerpt || '')+'</small></td>'
                          +'</tr>');
                    });
                    $('#aichat-test-results').fadeIn(120);
                } else {
                    $status.text(resp.data && resp.data.message ? resp.data.message : (aichat_settings_ajax.error_generic || 'Error'));
                }
            },
            error: function(xhr){
                $status.text('Error: '+xhr.status);
            }
        });
        return false;
    });

    // Helper para escapar HTML (simple)
    function escapeHtml(str){
        return (str||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]||c;});
    }
});