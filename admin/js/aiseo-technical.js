/**
 * Technical SEO tab.
 *
 * @package AISEO
 */
(function ($) {
    'use strict';

    if (typeof aiseoAdmin === 'undefined') {
        return;
    }

    var i18n = window.aiseoTechnical && aiseoTechnical.i18n ? aiseoTechnical.i18n : {};
    var currentPage = 1;
    var perPage = 10;

    function text(key, fallback) {
        return i18n[key] || fallback;
    }

    function escapeHtml(value) {
        return $('<div>').text(String(value == null ? '' : value)).html();
    }

    function loadRedirects(page) {
        page = page || 1;
        currentPage = page;
        $('#aiseo-redirects-list').html('<p>' + text('loading', 'Loading redirects...') + '</p>');

        $.ajax({
            url: aiseoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiseo_list_redirects',
                nonce: aiseoAdmin.nonce
            },
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    var redirects = response.data;
                    var totalPages = Math.ceil(redirects.length / perPage);
                    var start = (page - 1) * perPage;
                    var pageRedirects = redirects.slice(start, start + perPage);
                    var html = '<table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr>';
                    html += '<th>' + text('from', 'From URL') + '</th>';
                    html += '<th>' + text('to', 'To URL') + '</th>';
                    html += '<th>' + text('type', 'Type') + '</th>';
                    html += '<th>' + text('regex', 'Regex') + '</th>';
                    html += '<th>' + text('hits', 'Hits') + '</th>';
                    html += '<th>' + text('actions', 'Actions') + '</th>';
                    html += '</tr></thead><tbody>';
                    $.each(pageRedirects, function (i, redirect) {
                        html += '<tr>';
                        html += '<td><code>' + escapeHtml(redirect.from_url || redirect.from || '') + '</code></td>';
                        html += '<td><code>' + escapeHtml(redirect.to_url || redirect.to || '') + '</code></td>';
                        html += '<td><span class="aiseo-badge">' + escapeHtml(redirect.type || '') + '</span></td>';
                        html += '<td>' + (redirect.is_regex ? text('yes', 'Yes') : text('no', 'No')) + '</td>';
                        html += '<td>' + (redirect.hits || 0) + '</td>';
                        html += '<td><button type="button" class="button button-small aiseo-delete-redirect" data-id="' + escapeHtml(redirect.id) + '"><span class="dashicons dashicons-trash"></span> ' + text('delete', 'Delete') + '</button></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                    if (totalPages > 1) {
                        html += '<div class="tablenav aiseo-redirect-nav"><div class="tablenav-pages">';
                        html += '<span class="displaying-num">' + redirects.length + ' ' + text('items', 'items') + '</span>';
                        html += '<span class="pagination-links">';
                        if (page > 1) {
                            html += '<a class="button aiseo-redirect-page" data-page="' + (page - 1) + '">‹ ' + text('previous', 'Previous') + '</a> ';
                        }
                        html += '<span class="paging-input"><span class="tablenav-paging-text">' + page + ' ' + text('of', 'of') + ' <span class="total-pages">' + totalPages + '</span></span></span>';
                        if (page < totalPages) {
                            html += ' <a class="button aiseo-redirect-page" data-page="' + (page + 1) + '">' + text('next', 'Next') + ' ›</a>';
                        }
                        html += '</span></div></div>';
                    }
                    $('#aiseo-redirects-list').html(html);
                } else {
                    $('#aiseo-redirects-list').html('<p class="aiseo-muted">' + text('none', 'No redirects found. Create one above to get started.') + '</p>');
                }
            },
            error: function () {
                $('#aiseo-redirects-list').html('<p class="aiseo-index-warn">' + text('loadError', 'Error loading redirects.') + '</p>');
            }
        });
    }

    $(function () {
        sessionStorage.removeItem('aiseo_nonce_refresh_attempted');

        $('#aiseo-redirect-form').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            $btn.prop('disabled', true).text(text('adding', 'Adding...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_add_redirect',
                    nonce: aiseoAdmin.nonce,
                    from_url: $form.find('[name="from_url"]').val(),
                    to_url: $form.find('[name="to_url"]').val(),
                    redirect_type: $form.find('[name="redirect_type"]').val(),
                    is_regex: $form.find('[name="is_regex"]').is(':checked') ? 1 : 0
                },
                success: function (response) {
                    if (response.success) {
                        $form.before('<div class="notice notice-success is-dismissible"><p>' + text('added', 'Redirect added.') + '</p></div>');
                        $form[0].reset();
                        loadRedirects();
                    } else {
                        $form.before('<div class="notice notice-error is-dismissible"><p>' + escapeHtml(response.data) + '</p></div>');
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus"></span> ' + text('addRedirect', 'Add Redirect'));
                }
            });
        });

        $(document).on('click', '.aiseo-redirect-page', function (e) {
            e.preventDefault();
            loadRedirects(parseInt($(this).data('page'), 10));
        });

        $('#aiseo-refresh-redirects').on('click', function () {
            loadRedirects();
        });

        $(document).on('click', '.aiseo-delete-redirect', function () {
            if (!window.confirm(text('deleteConfirm', 'Are you sure you want to delete this redirect?'))) {
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true).text(text('deleting', 'Deleting...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_delete_redirect',
                    nonce: aiseoAdmin.nonce,
                    redirect_id: $btn.data('id')
                },
                success: function (response) {
                    if (response.success) {
                        loadRedirects(currentPage);
                    } else {
                        window.alert(text('error', 'Error') + ': ' + response.data);
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + text('delete', 'Delete'));
                    }
                }
            });
        });

        loadRedirects();

        $('#aiseo-regenerate-sitemap').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).text(text('regenerating', 'Regenerating...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_regenerate_sitemap',
                    nonce: aiseoAdmin.nonce
                },
                success: function (response) {
                    window.alert(response.success ? text('sitemapOk', 'Sitemap regenerated successfully!') : (text('error', 'Error') + ': ' + response.data));
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + text('regenerate', 'Regenerate Sitemap'));
                }
            });
        });

        $('#aiseo-find-missing-alt').on('click', function () {
            var $btn = $(this);
            var $results = $('#aiseo-image-results');
            $btn.prop('disabled', true).text(text('searching', 'Searching...'));
            $results.html('<p>' + text('searchingImages', 'Searching for images without alt text...') + '</p>');
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_find_missing_alt',
                    nonce: aiseoAdmin.nonce
                },
                success: function (response) {
                    if (response.success && response.data.length > 0) {
                        var html = '<div class="aiseo-card"><h4>' + text('found', 'Found') + ' ' + response.data.length + ' ' + text('missingAlt', 'images without alt text:') + '</h4>';
                        html += '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                        html += '<th>' + text('image', 'Image') + '</th><th>' + text('post', 'Post') + '</th><th>' + text('actions', 'Actions') + '</th></tr></thead><tbody>';
                        $.each(response.data, function (i, img) {
                            html += '<tr data-post-id="' + escapeHtml(img.post_id) + '" data-image-url="' + escapeHtml(img.url) + '">';
                            html += '<td><img class="aiseo-thumb-md" src="' + escapeHtml(img.url) + '" alt=""></td>';
                            html += '<td>' + escapeHtml(img.post_title) + '</td>';
                            html += '<td>';
                            html += '<button type="button" class="button button-primary button-small aiseo-generate-single-alt">' + text('generateAlt', 'Generate Alt') + '</button> ';
                            html += '<a href="' + escapeHtml(img.edit_url) + '" class="button button-small" target="_blank" rel="noopener noreferrer">' + text('editPost', 'Edit Post') + '</a>';
                            html += '</td></tr>';
                        });
                        html += '</tbody></table></div>';
                        $results.html(html);
                    } else if (response.success) {
                        $results.html('<div class="notice notice-success"><p>' + text('allHaveAlt', 'All images have alt text.') + '</p></div>');
                    } else {
                        $results.html('<div class="notice notice-error"><p>' + escapeHtml(response.data) + '</p></div>');
                    }
                },
                error: function (xhr, status, error) {
                    $results.html('<div class="notice notice-error"><p>' + escapeHtml(error) + '</p></div>');
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + text('findMissing', 'Find Images Missing Alt Text'));
                }
            });
        });

        $('#aiseo-generate-all-alt').on('click', function () {
            var $btn = $(this);
            var $results = $('#aiseo-image-results');
            if (!window.confirm(text('generateAllConfirm', 'Generate alt text for all images? This may take a while.'))) {
                return;
            }
            $btn.prop('disabled', true).text(text('generating', 'Generating...'));
            $results.html('<div class="aiseo-card"><h4>' + text('progress', 'Generating Alt Text Progress') + '</h4><div id="aiseo-alt-progress"></div><div id="aiseo-alt-summary"></div></div>');
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_generate_image_alt',
                    nonce: aiseoAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var data = response.data;
                        if (data.images && data.images.length > 0) {
                            var html = '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                            html += '<th>' + text('post', 'Post') + '</th><th>' + text('image', 'Image') + '</th><th>' + text('status', 'Status') + '</th></tr></thead><tbody>';
                            $.each(data.images, function (i, img) {
                                html += '<tr>';
                                html += '<td>' + escapeHtml(img.post_title) + '</td>';
                                html += '<td><img class="aiseo-thumb-sm" src="' + escapeHtml(img.url) + '" alt=""></td>';
                                html += '<td>' + (img.success ? '✓ ' : '✗ ') + escapeHtml(img.message) + '</td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                            $('#aiseo-alt-progress').html(html);
                            $('#aiseo-alt-summary').html(text('completed', 'Completed') + ': ' + data.success_count + ' | ' + text('failed', 'Failed') + ': ' + data.failed_count);
                        } else {
                            $('#aiseo-alt-progress').html('<p>' + text('noImages', 'No images found that need alt text.') + '</p>');
                        }
                    } else {
                        $results.html('<div class="notice notice-error"><p>' + escapeHtml(response.data) + '</p></div>');
                    }
                },
                error: function (xhr, status, error) {
                    $results.html('<div class="notice notice-error"><p>' + escapeHtml(error) + '</p></div>');
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-images-alt2"></span> ' + text('generateAll', 'Generate Alt Text for All Images'));
                }
            });
        });

        $(document).on('click', '.aiseo-generate-single-alt', function () {
            var $btn = $(this);
            var $row = $btn.closest('tr');
            $btn.prop('disabled', true).text(text('generating', 'Generating...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_generate_single_alt',
                    nonce: aiseoAdmin.nonce,
                    post_id: $row.data('post-id'),
                    image_url: $row.data('image-url')
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(400, function () {
                            $(this).remove();
                            if ($('#aiseo-image-results tbody tr').length === 0) {
                                $('#aiseo-image-results').html('<div class="notice notice-success"><p>' + text('allHaveAlt', 'All images have alt text.') + '</p></div>');
                            }
                        });
                    } else {
                        $btn.after('<span class="aiseo-index-warn"> ' + escapeHtml(response.data) + '</span>');
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false).text(text('generateAlt', 'Generate Alt'));
                }
            });
        });
    });
})(jQuery);
