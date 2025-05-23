/**
 * WizTrivia Admin JavaScript
 * Version: 2.2.0
 * Date: 2025-05-23 07:24:54
 * User: cyberkarhub
 */

jQuery(document).ready(function($) {
    
    // Initialize color pickers
    if ($.fn.wpColorPicker) {
        $('.wiztrivia-color-picker').wpColorPicker({
            change: function(event, ui) {
                // Update color preview
                const $preview = $(this).closest('tr').find('.wiztrivia-color-preview');
                if ($preview.length) {
                    $preview.css('background-color', ui.color.toString());
                }
            }
        });
    }
    
    // Initialize media uploader for images
    $('.wiztrivia-upload-button').click(function(e) {
        e.preventDefault();
        var button = $(this);
        var field = button.prev('.wiztrivia-image-url');
        var preview = button.next('.wiztrivia-image-preview');
        
        var frame = wp.media({
            title: 'Select or Upload Media',
            button: {
                text: 'Use this media'
            },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            field.val(attachment.url);
            
            // Update preview
            if (attachment.type === 'image') {
                if (preview.find('img').length === 0) {
                    preview.html('<img src="' + attachment.url + '" alt="Preview" style="max-width: 200px; max-height: 100px; margin-top: 10px;" />');
                } else {
                    preview.find('img').attr('src', attachment.url);
                }
            }
        });
        
        frame.open();
    });
    
    // ---------- Questions Management ----------
    
    // Generate questions form toggle
    $('#wiztrivia-generate-questions').click(function() {
        $('.wiztrivia-generate-form').slideDown();
    });
    
    $('#wiztrivia-cancel-generate').click(function() {
        $('.wiztrivia-generate-form').slideUp();
    });
    
    // Generate questions
    $('#wiztrivia-submit-generate').click(function() {
        var topic = $('#wiztrivia-topic').val();
        var sourceLinks = $('#wiztrivia-source-links').val();
        var count = $('#wiztrivia-question-count').val();
        
        if (!topic) {
            alert('Please enter a topic');
            return;
        }
        
        $('#wiztrivia-generate-spinner').css('visibility', 'visible');
        $('#wiztrivia-submit-generate').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wiztrivia_generate_questions',
                topic: topic,
                source_links: sourceLinks,
                count: count,
                security: wiztriviaParams.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Questions generated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            },
            complete: function() {
                $('#wiztrivia-generate-spinner').css('visibility', 'hidden');
                $('#wiztrivia-submit-generate').prop('disabled', false);
            }
        });
    });
    
    // Load questions
    function loadQuestions() {
        if (!$('#wiztrivia-questions-list').length) {
            return;
        }
        
        $('#wiztrivia-questions-list').html('<tr><td colspan="6">Loading questions...</td></tr>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wiztrivia_get_questions',
                security: wiztriviaParams.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    if (response.data.length > 0) {
                        var html = '';
                        $.each(response.data, function(i, question) {
                            html += '<tr>';
                            html += '<td><input type="checkbox" class="wiztrivia-question-checkbox" value="' + question.id + '" /></td>';
                            html += '<td>' + question.question + '</td>';
                            html += '<td>' + (question.topic || 'N/A') + '</td>';
                            html += '<td>' + (question.difficulty || 'N/A') + '</td>';
                            html += '<td>' + (question.source ? '<a href="' + question.source + '" target="_blank">View Source</a>' : 'N/A') + '</td>';
                            html += '<td>';
                            html += '<button type="button" class="button-link wiztrivia-edit-question" data-id="' + question.id + '">Edit</button> | ';
                            html += '<button type="button" class="button-link wiztrivia-delete-question" data-id="' + question.id + '">Delete</button>';
                            html += '</td>';
                            html += '</tr>';
                        });
                        $('#wiztrivia-questions-list').html(html);
                    } else {
                        $('#wiztrivia-questions-list').html('<tr><td colspan="6">No questions found. Click "Generate New Questions" to create some.</td></tr>');
                    }
                } else {
                    $('#wiztrivia-questions-list').html('<tr><td colspan="6">Error loading questions. Please refresh the page.</td></tr>');
                }
            },
            error: function() {
                $('#wiztrivia-questions-list').html('<tr><td colspan="6">Server error. Please refresh the page.</td></tr>');
            }
        });
    }
    
    // Initial load
    if ($('#wiztrivia-questions-list').length) {
        loadQuestions();
    }
    
    // Single question delete
    $(document).on('click', '.wiztrivia-delete-question', function() {
        var id = $(this).data('id');
        
        if (!confirm('Are you sure you want to delete this question?')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wiztrivia_delete_question',
                id: id,
                security: wiztriviaParams.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadQuestions();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            }
        });
    });
    
    // Edit question
    $(document).on('click', '.wiztrivia-edit-question', function() {
        var id = $(this).data('id');
        
        // Open edit modal or redirect to edit page
        alert('Edit functionality will be implemented in future version.');
    });
    
    // ---------- Bulk Actions ----------
    
    // Select all checkboxes
    $('#wiztrivia-select-all, .wiztrivia-select-all-header').change(function() {
        var isChecked = $(this).is(':checked');
        $('.wiztrivia-question-checkbox').prop('checked', isChecked);
        $('#wiztrivia-select-all, .wiztrivia-select-all-header').prop('checked', isChecked);
    });
    
    // Update select all when individual checkboxes change
    $(document).on('change', '.wiztrivia-question-checkbox', function() {
        var allChecked = $('.wiztrivia-question-checkbox:checked').length === $('.wiztrivia-question-checkbox').length;
        $('#wiztrivia-select-all, .wiztrivia-select-all-header').prop('checked', allChecked);
    });
    
    // Bulk delete
    $('#wiztrivia-delete-selected').click(function() {
        var selected = $('.wiztrivia-question-checkbox:checked');
        
        if (selected.length === 0) {
            alert('Please select at least one question');
            return;
        }
        
        if (!confirm('Are you sure you want to delete ' + selected.length + ' selected questions?')) {
            return;
        }
        
        var ids = [];
        selected.each(function() {
            ids.push($(this).val());
        });
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wiztrivia_bulk_delete_questions',
                ids: ids,
                security: wiztriviaParams.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Questions deleted successfully!');
                    loadQuestions();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            }
        });
    });
    
    // Bulk regenerate
    $('#wiztrivia-regenerate-selected').click(function() {
        var selected = $('.wiztrivia-question-checkbox:checked');
        
        if (selected.length === 0) {
            alert('Please select at least one question');
            return;
        }
        
        if (!confirm('Are you sure you want to regenerate ' + selected.length + ' selected questions? This may take some time.')) {
            return;
        }
        
        var ids = [];
        selected.each(function() {
            ids.push($(this).val());
        });
        
        $(this).prop('disabled', true);
        var $button = $(this);
        $button.after('<span class="spinner is-active" style="float:none;margin-left:10px;"></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wiztrivia_bulk_regenerate_questions',
                ids: ids,
                security: wiztriviaParams.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Questions regenerated successfully!');
                    loadQuestions();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.next('.spinner').remove();
            }
        });
    });
    
    // ---------- Level Management ----------
    
    // Make levels sortable
    if ($.fn.sortable && $('#wiztrivia-levels-table tbody').length) {
        $('#wiztrivia-levels-table tbody').sortable({
            handle: '.wiztrivia-level-handle',
            update: function() {
                // Update level indices
                $('#wiztrivia-levels-table tbody tr').each(function(index) {
                    $(this).find('input, select, textarea').each(function() {
                        const name = $(this).attr('name');
                        if (name) {
                            const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                            $(this).attr('name', newName);
                        }
                    });
                });
            }
        });
    }
    
    // Add new level
    $('#wiztrivia-add-level').click(function() {
        const $tbody = $('#wiztrivia-levels-table tbody');
        const index = $tbody.children('tr').length;
        
        const html = `
            <tr>
                <td><span class="wiztrivia-level-handle dashicons dashicons-menu"></span></td>
                <td><input type="text" name="wiztrivia_settings[levels][${index}][name]" value="" class="regular-text" /></td>
                <td><input type="number" name="wiztrivia_settings[levels][${index}][required_score]" value="0" min="0" /></td>
                <td><input type="text" name="wiztrivia_settings[levels][${index}][article_url]" value="" class="regular-text" /></td>
                <td><input type="text" name="wiztrivia_settings[levels][${index}][article_title]" value="" class="regular-text" /></td>
                <td><button type="button" class="button wiztrivia-remove-level">Remove</button></td>
            </tr>
        `;
        
        $tbody.append(html);
    });
    
    // Remove level
    $(document).on('click', '.wiztrivia-remove-level', function() {
        const $row = $(this).closest('tr');
        
        if ($('#wiztrivia-levels-table tbody tr').length <= 1) {
            alert('You must have at least one level. Cannot remove the last level.');
            return;
        }
        
        $row.remove();
        
        // Update level indices
        $('#wiztrivia-levels-table tbody tr').each(function(index) {
            $(this).find('input, select, textarea').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                }
            });
        });
    });
    
    // ---------- Settings Form Submission ----------
    
    // Form submission protection
    $('#wiztrivia-settings-form').on('submit', function() {
        // Remove any disabled attributes before submit
        $(this).find(':disabled').prop('disabled', false);
        
        // Could add validation here if needed
        return true;
    });
    
    // ---------- Tab Navigation ----------
    
    // Handle tab navigation with hash in URL
    function activateTab(tabHash) {
        const tab = tabHash || window.location.hash;
        if (tab && $(tab).length) {
            $('.nav-tab-wrapper a').removeClass('nav-tab-active');
            $('.nav-tab-wrapper a[href="' + tab + '"]').addClass('nav-tab-active');
            $('.wiztrivia-tab-content').hide();
            $(tab).show();
        }
    }
    
    // Initial tab activation
    activateTab();
    
    // Tab click handler
    $('.nav-tab-wrapper a').click(function(e) {
        if ($(this).attr('href').indexOf('#') === 0) {
            e.preventDefault();
            const target = $(this).attr('href');
            window.location.hash = target;
            activateTab(target);
        }
    });
    
    // Remember active tab between page loads
    $(window).on('hashchange', function() {
        activateTab();
    });
});