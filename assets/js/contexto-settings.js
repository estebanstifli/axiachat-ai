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
            data: {
                action: 'aichat_load_contexts',
                nonce: aichat_settings_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var tbody = $('#aichat-contexts-body');
                    tbody.empty();
                    $.each(response.data.contexts, function(index, context) {
                        tbody.append(
                            '<tr>' +
                                '<td>' + context.id + '</td>' +
                                '<td>' +
                                    '<span class="context-name" data-id="' + context.id + '">' + context.name + '</span>' +
                                    '<input type="text" class="edit-name" style="display:none;" data-id="' + context.id + '" value="' + context.name + '">' +
                                '</td>' +
                                '<td>' +
                                    '<div class="progress">' +
                                        '<div class="progress-bar" role="progressbar" style="width: ' + (context.processing_progress || 0) + '%;" aria-valuenow="' + (context.processing_progress || 0) + '" aria-valuemin="0" aria-valuemax="100"></div>' +
                                    '</div>' +
                                '</td>' +
                                '<td>' +
                                    '<button class="button edit-context" data-id="' + context.id + '">' + aichat_settings_ajax.edit_text + '</button> ' +
                                    '<button class="button delete-context" data-id="' + context.id + '">' + aichat_settings_ajax.delete_text + '</button>' +
                                '</td>' +
                            '</tr>'
                        );
                    });
                    $('#aichat-message').text('').hide();
                    startProgressUpdates(); // Iniciar actualizaciones de progreso después de cargar
                } else {
                    $('#aichat-message').text(response.data.message).show();
                }
            },
            error: function(xhr, status, error) {
                $('#aichat-message').text('Error loading contexts: ' + error).show();
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

    // Editar nombre
    $(document).on('click', '.edit-context', function() {
        var id = $(this).data('id');
        var nameSpan = $('.context-name[data-id="' + id + '"]');
        var editInput = $('.edit-name[data-id="' + id + '"]');
        console.log('Edit clicked for ID:', id);
        nameSpan.hide();
        editInput.show().focus();
    });

    $(document).on('blur', '.edit-name', function() {
        var id = $(this).data('id');
        var newName = $(this).val();
        var nameSpan = $('.context-name[data-id="' + id + '"]');
        var editInput = $(this);
        console.log('Blur event for ID:', id, 'New Name:', newName);

        if (newName === nameSpan.text()) {
            console.log('No change detected, reverting.');
            editInput.hide();
            nameSpan.show();
            return;
        }

        console.log('Sending AJAX for ID:', id, 'with name:', newName);
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'aichat_update_context_name',
                nonce: aichat_settings_ajax.nonce,
                id: id,
                name: newName
            },
            success: function(response) {
                console.log('AJAX Success:', response);
                if (response.success) {
                    nameSpan.text(newName).show();
                    editInput.hide();
                    loadContexts();
                    $('#aichat-message').text(aichat_settings_ajax.updated_text).css('color', 'green').show().fadeOut(3000);
                } else {
                    $('#aichat-message').text(response.data.message).show();
                    editInput.hide();
                    nameSpan.show();
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error (update):', xhr.status, xhr.statusText, xhr.responseText);
                $('#aichat-message').text('Error updating name: ' + error).show();
                editInput.hide();
                nameSpan.show();
            }
        });
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
});