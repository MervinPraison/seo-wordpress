/**
 * Bulk operations tab.
 *
 * @package AISEO
 */
(function ($) {
    'use strict';

    if (typeof aiseoAdmin === 'undefined') {
        return;
    }

    var i18n = window.aiseoBulk && aiseoBulk.i18n ? aiseoBulk.i18n : {};
    var bulkNonce = aiseoAdmin.nonce;
    var bulkResults = [];

    function text(key, fallback) {
        return i18n[key] || fallback;
    }

    function refreshNonce(callback) {
        $.ajax({
            url: aiseoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: aiseoAdmin.nonceRefreshAction,
                nonce: aiseoAdmin.refreshNonce
            },
            success: function (response) {
                if (response.success && response.data.nonce) {
                    bulkNonce = response.data.nonce;
                    aiseoAdmin.nonce = response.data.nonce;
                    if (callback) {
                        callback();
                    }
                }
            }
        });
    }

    function bulkOperation(action, fieldType) {
        var selectedPosts = [];
        $('.aiseo-bulk-post:checked').each(function () {
            selectedPosts.push({
                id: $(this).val(),
                title: $(this).parent().text().trim()
            });
        });

        if (selectedPosts.length === 0) {
            $('#aiseo-bulk-status').html('<span class="aiseo-index-warn">' + text('selectPosts', 'Please select at least one post') + '</span>');
            $('#aiseo-bulk-progress').show();
            return;
        }

        $('#aiseo-bulk-generate-titles, #aiseo-bulk-generate-descriptions, #aiseo-bulk-analyze').prop('disabled', true).addClass('disabled');
        bulkResults = [];
        var currentIndex = 0;
        $('#aiseo-bulk-progress').show();
        $('#aiseo-bulk-results').hide();
        $('.aiseo-progress-fill').css('width', '0%').text('0%');

        function processNext() {
            if (currentIndex >= selectedPosts.length) {
                displayResults(fieldType);
                return;
            }
            var post = selectedPosts[currentIndex];
            var progress = Math.round(((currentIndex + 1) / selectedPosts.length) * 100);
            $('.aiseo-progress-fill').css('width', progress + '%').text(progress + '%');
            $('#aiseo-bulk-status').text(text('processing', 'Processing') + ' ' + (currentIndex + 1) + ' ' + text('of', 'of') + ' ' + selectedPosts.length + ': ' + post.title);

            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    post_id: post.id,
                    nonce: bulkNonce
                },
                success: function (response) {
                    sessionStorage.removeItem('aiseo_nonce_refresh_attempted');
                    if (response.success) {
                        bulkResults.push({
                            postId: post.id,
                            postTitle: post.title,
                            success: true,
                            generated: response.data,
                            fieldType: fieldType
                        });
                    } else {
                        bulkResults.push({
                            postId: post.id,
                            postTitle: post.title,
                            success: false,
                            error: response.data
                        });
                    }
                },
                error: function (xhr, status, error) {
                    if (xhr.status === 403 && xhr.responseText === '-1') {
                        if (!sessionStorage.getItem('aiseo_nonce_refresh_attempted')) {
                            sessionStorage.setItem('aiseo_nonce_refresh_attempted', '1');
                            refreshNonce(function () {
                                currentIndex -= 1;
                                processNext();
                            });
                            return;
                        }
                        sessionStorage.removeItem('aiseo_nonce_refresh_attempted');
                        bulkResults.push({
                            postId: post.id,
                            postTitle: post.title,
                            success: false,
                            error: text('sessionError', 'Session Error: Please log out and log back in to WordPress')
                        });
                    } else {
                        bulkResults.push({
                            postId: post.id,
                            postTitle: post.title,
                            success: false,
                            error: text('connectionError', 'Connection error') + ': ' + error
                        });
                    }
                },
                complete: function () {
                    currentIndex += 1;
                    processNext();
                }
            });
        }

        processNext();
    }

    function displayResults(fieldType) {
        var html = '';
        var fieldLabel = fieldType === 'title' ? text('title', 'Title') : (fieldType === 'description' ? text('description', 'Description') : text('analysis', 'Analysis'));

        $.each(bulkResults, function (i, result) {
            if (result.success) {
                var generatedText = '';
                if (typeof result.generated === 'string') {
                    generatedText = result.generated;
                } else if (result.generated && result.generated.title) {
                    generatedText = result.generated.title;
                } else if (result.generated && result.generated.description) {
                    generatedText = result.generated.description;
                } else if (result.generated && result.generated.content) {
                    generatedText = result.generated.content;
                } else if (result.generated && result.generated.text) {
                    generatedText = result.generated.text;
                } else if (typeof result.generated === 'object') {
                    generatedText = JSON.stringify(result.generated);
                }
                var escapedValue = generatedText.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                html += '<div class="aiseo-result-item">';
                html += '<label class="aiseo-result-label">';
                html += '<input type="checkbox" class="aiseo-approve-checkbox" data-post-id="' + result.postId + '" data-field="' + result.fieldType + '" data-value="' + escapedValue + '" checked />';
                html += '<div><strong>' + $('<div>').text(result.postTitle).html() + '</strong><br>';
                html += '<span class="aiseo-muted">' + text('newField', 'New') + ' ' + fieldLabel + ':</span><br>';
                html += '<span class="aiseo-result-value">' + $('<div>').text(generatedText).html() + '</span></div>';
                html += '</label></div>';
            } else {
                html += '<div class="aiseo-result-item is-error">';
                html += '<strong>' + $('<div>').text(result.postTitle).html() + '</strong><br>';
                html += '<span class="aiseo-index-warn">' + text('error', 'Error') + ': ' + $('<div>').text(String(result.error || text('unknownError', 'Unknown error'))).html() + '</span>';
                html += '</div>';
            }
        });

        if (html === '') {
            html = '<div class="notice notice-warning"><p>' + text('noResults', 'No results to display.') + '</p></div>';
        }

        $('#aiseo-results-list').html(html);
        $('#aiseo-bulk-results').show();
        $('#aiseo-bulk-status').html('<strong>' + text('complete', 'Complete! Review and approve changes below.') + '</strong>');
        $('#aiseo-bulk-generate-titles, #aiseo-bulk-generate-descriptions, #aiseo-bulk-analyze').prop('disabled', false).removeClass('disabled');
        $('html, body').animate({ scrollTop: $('#aiseo-bulk-results').offset().top - 100 }, 500);
    }

    $(function () {
        sessionStorage.removeItem('aiseo_nonce_refresh_attempted');

        $('#aiseo-select-all-posts').on('change', function () {
            $('.aiseo-bulk-post').prop('checked', $(this).is(':checked'));
        });

        $('#aiseo-bulk-generate-titles').on('click', function () {
            bulkOperation('aiseo_generate_title', 'title');
        });
        $('#aiseo-bulk-generate-descriptions').on('click', function () {
            bulkOperation('aiseo_generate_description', 'description');
        });
        $('#aiseo-bulk-analyze').on('click', function () {
            bulkOperation('aiseo_analyze_content', 'analysis');
        });
        $('#aiseo-select-all-results').on('click', function () {
            $('.aiseo-approve-checkbox').prop('checked', true);
        });
        $('#aiseo-clear-all-results').on('click', function () {
            $('.aiseo-approve-checkbox').prop('checked', false);
        });

        $('#aiseo-save-approved').on('click', function () {
            var $btn = $(this);
            var approved = [];
            $('.aiseo-approve-checkbox:checked').each(function () {
                approved.push({
                    post_id: $(this).data('post-id'),
                    field: $(this).data('field'),
                    value: $(this).data('value')
                });
            });
            if (approved.length === 0) {
                $('#aiseo-bulk-status').html('<span class="aiseo-index-warn">' + text('selectSave', 'Please select at least one item to save') + '</span>');
                return;
            }
            $btn.prop('disabled', true).text(text('saving', 'Saving...'));
            var saved = 0;
            var total = approved.length;

            function saveNext(index) {
                if (index >= total) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> ' + text('saveApproved', 'Save Approved Changes'));
                    $('#aiseo-bulk-status').html('<strong>' + text('saved', 'Saved') + ' ' + saved + ' ' + text('changes', 'changes successfully!') + '</strong>');
                    $('#aiseo-bulk-results').fadeOut(500);
                    return;
                }
                var item = approved[index];
                var action = item.field === 'title' ? 'aiseo_save_title' : 'aiseo_save_description';
                $.ajax({
                    url: aiseoAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: action,
                        post_id: item.post_id,
                        value: item.value,
                        nonce: aiseoAdmin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            saved += 1;
                        }
                    },
                    complete: function () {
                        $btn.text(text('saving', 'Saving') + ' ' + (index + 1) + '/' + total + '...');
                        saveNext(index + 1);
                    }
                });
            }
            saveNext(0);
        });

        $('.aiseo-import-btn').on('click', function () {
            var btn = $(this);
            var source = btn.data('source');
            if (!window.confirm(text('importConfirm', 'Import SEO data from this plugin? This may take a few minutes.'))) {
                return;
            }
            btn.prop('disabled', true).text(text('importing', 'Importing...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_import_seo',
                    source: source,
                    nonce: aiseoAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#aiseo-import-result').show().find('.aiseo-result-content').html(
                            '<p><strong>' + text('success', 'Success!') + '</strong> ' + text('imported', 'Imported') + ' ' + response.data.count + ' ' + text('itemsFrom', 'items from') + ' ' + String(source).toUpperCase() + '</p>'
                        );
                    } else {
                        window.alert(text('error', 'Error') + ': ' + (response.data || text('importFailed', 'Import failed')));
                    }
                },
                error: function () {
                    window.alert(text('connectionError', 'Connection error'));
                },
                complete: function () {
                    btn.prop('disabled', false);
                }
            });
        });

        $('.aiseo-export-btn').on('click', function () {
            var btn = $(this);
            var format = btn.data('format');
            var $resultDiv = $('#aiseo-export-result');
            if ($resultDiv.length === 0) {
                $resultDiv = $('<div id="aiseo-export-result" class="aiseo-result-box"></div>').insertAfter(btn.parent());
            }
            btn.prop('disabled', true).text(text('exporting', 'Exporting...'));
            $resultDiv.html('<p>' + text('preparing', 'Preparing export...') + '</p>').show();
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_export_seo',
                    format: format,
                    nonce: aiseoAdmin.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        var content;
                        var mimeType;
                        if (format === 'csv') {
                            if (Array.isArray(response.data) && response.data.length > 0) {
                                var headers = Object.keys(response.data[0]);
                                var csv = headers.join(',') + '\n';
                                response.data.forEach(function (row) {
                                    var values = headers.map(function (header) {
                                        var val = row[header] || '';
                                        return '"' + String(val).replace(/"/g, '""') + '"';
                                    });
                                    csv += values.join(',') + '\n';
                                });
                                content = csv;
                                mimeType = 'text/csv';
                            } else {
                                $resultDiv.html('<div class="notice notice-warning"><p>' + text('noExport', 'No data to export.') + '</p></div>').show();
                                return;
                            }
                        } else {
                            content = JSON.stringify(response.data, null, 2);
                            mimeType = 'application/json';
                        }
                        var blob = new Blob([content], { type: mimeType });
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'aiseo-export-' + Date.now() + '.' + format;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                        var count = Array.isArray(response.data) ? response.data.length : 0;
                        $resultDiv.html('<div class="notice notice-success"><p>' + text('exported', 'Exported') + ' ' + count + ' ' + text('postsTo', 'posts to') + ' ' + String(format).toUpperCase() + '.</p></div>').show();
                    } else {
                        var errorMsg = response.data || text('exportFailed', 'Export failed');
                        if (typeof errorMsg === 'object') {
                            errorMsg = JSON.stringify(errorMsg);
                        }
                        $resultDiv.html('<div class="notice notice-error"><p>' + $('<div>').text(String(errorMsg)).html() + '</p></div>').show();
                    }
                },
                error: function () {
                    $resultDiv.html('<div class="notice notice-error"><p>' + text('connectionError', 'Connection error') + '</p></div>').show();
                },
                complete: function () {
                    btn.prop('disabled', false);
                }
            });
        });
    });
})(jQuery);
