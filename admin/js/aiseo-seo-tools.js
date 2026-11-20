/**
 * SEO Tools tab.
 *
 * @package AISEO
 */
(function ($) {
    'use strict';

    if (typeof aiseoAdmin === 'undefined') {
        return;
    }

    var i18n = window.aiseoSeoTools && aiseoSeoTools.i18n ? aiseoSeoTools.i18n : {};

    function text(key, fallback) {
        return i18n[key] || fallback;
    }

    function noticeHtml(type, message) {
        return $('<div class="notice"></div>')
            .addClass('notice-' + type)
            .append($('<p></p>').text(message));
    }

    function restoreButton($btn, icon, label) {
        $btn.prop('disabled', false).html('<span class="dashicons ' + icon + '"></span> ' + label);
    }

    $(function () {
        sessionStorage.removeItem('aiseo_nonce_refresh_attempted');

        $('.aiseo-generate-meta').on('click', function () {
            var btn = $(this);
            var field = btn.data('field');
            var postId = $('#aiseo-meta-post-select').val();
            if (!postId) {
                $('#aiseo-meta-results').empty().append(noticeHtml('error', text('selectPost', 'Please select a post first'))).show();
                return;
            }
            btn.prop('disabled', true).text(text('generating', 'Generating...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_generate_' + field,
                    post_id: postId,
                    nonce: aiseoAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#aiseo-meta-output').empty().append($('<strong></strong>').text(String(field).toUpperCase() + ': ')).append(document.createTextNode(String(response.data)));
                        $('#aiseo-meta-results').show();
                    } else {
                        $('#aiseo-meta-results').empty().append(noticeHtml('error', String(response.data || text('failed', 'Failed to generate')))).show();
                    }
                },
                error: function () {
                    $('#aiseo-meta-results').empty().append(noticeHtml('error', text('connectionError', 'Connection error'))).show();
                },
                complete: function () {
                    var label = field === 'title' ? text('generateTitle', 'Generate Title') : text('generateDescription', 'Generate Description');
                    restoreButton(btn, 'dashicons-edit', label);
                }
            });
        });

        $('.aiseo-analyze-content').on('click', function () {
            var btn = $(this);
            var postId = $('#aiseo-analyze-post-select').val();
            if (!postId) {
                $('.aiseo-analysis-results').empty().append(noticeHtml('error', text('selectPost', 'Please select a post first'))).show();
                return;
            }
            btn.prop('disabled', true).text(text('analyzing', 'Analyzing...'));
            $('.aiseo-analysis-results').hide();
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_analyze_content',
                    post_id: postId,
                    nonce: aiseoAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var wrap = $('<div class="aiseo-card"></div>');
                        wrap.append($('<h4></h4>').text(text('analysisResults', 'Analysis Results')));
                        var grid = $('<div class="aiseo-stat-grid"></div>');
                        if (response.data.overall_score) {
                            grid.append($('<div class="aiseo-stat-item"></div>')
                                .append($('<div class="aiseo-stat-value"></div>').text(response.data.overall_score))
                                .append($('<div class="aiseo-stat-label"></div>').text(text('seoScore', 'SEO Score'))));
                        }
                        $.each(response.data, function (key, value) {
                            if (key !== 'overall_score' && typeof value === 'object' && value && value.score) {
                                grid.append($('<div class="aiseo-stat-item"></div>')
                                    .append($('<div class="aiseo-stat-value"></div>').text(value.score))
                                    .append($('<div class="aiseo-stat-label"></div>').text(String(key).replace(/_/g, ' '))));
                            }
                        });
                        wrap.append(grid);
                        $('.aiseo-analysis-results').empty().append(wrap).show();
                    } else {
                        $('.aiseo-analysis-results').empty().append(noticeHtml('error', String(response.data || text('failedAnalyze', 'Failed to analyze')))).show();
                    }
                },
                error: function () {
                    $('.aiseo-analysis-results').empty().append(noticeHtml('error', text('connectionError', 'Connection error'))).show();
                },
                complete: function () {
                    restoreButton(btn, 'dashicons-chart-bar', text('analyzeContent', 'Analyze Content'));
                }
            });
        });

        $('.aiseo-get-linking').on('click', function () {
            var btn = $(this);
            var postId = $('#aiseo-linking-post-select').val();
            if (!postId) {
                window.alert(text('selectPost', 'Please select a post first'));
                return;
            }
            btn.prop('disabled', true).text(text('gettingSuggestions', 'Getting suggestions...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_internal_linking',
                    post_id: postId,
                    nonce: aiseoAdmin.nonce
                },
                success: function (response) {
                    var box = $('#aiseo-linking-results').show().find('.aiseo-result-content').empty();
                    var suggestions = [];
                    if (response.success && response.data) {
                        if (Array.isArray(response.data)) {
                            suggestions = response.data;
                        } else if (response.data.suggestions && Array.isArray(response.data.suggestions)) {
                            suggestions = response.data.suggestions;
                        }
                    }
                    if (suggestions.length) {
                        var list = $('<ul></ul>');
                        $.each(suggestions, function (i, link) {
                            var title = link.post_title || link.title || text('untitled', 'Untitled');
                            var url = link.post_url || link.url || link.permalink || '';
                            var item = $('<li></li>');
                            if (url) {
                                $('<a></a>').attr({ href: url, target: '_blank', rel: 'noopener noreferrer' }).text(title).appendTo(item);
                            } else {
                                item.text(title);
                            }
                            if (link.relevance_score) {
                                item.append(document.createTextNode(' (' + Math.round(link.relevance_score * 100) + '%)'));
                            }
                            if (link.reason || link.description) {
                                item.append(document.createTextNode(' — ' + (link.reason || link.description)));
                            }
                            list.append(item);
                        });
                        box.append(list);
                    } else {
                        box.text(response.success ? text('noSuggestions', 'No suggestions found') : String(response.data || text('failed', 'Failed to get suggestions')));
                    }
                },
                error: function () {
                    $('#aiseo-linking-results').show().find('.aiseo-result-content').text(text('connectionError', 'Connection error'));
                },
                complete: function () {
                    restoreButton(btn, 'dashicons-admin-links', text('getLinking', 'Get Linking Suggestions'));
                }
            });
        });

        function loadVariations(type, restoreLabel) {
            var postId = $('#aiseo-variations-post-select').val();
            var $results = $('#aiseo-variations-results');
            if (!postId) {
                $results.empty().append(noticeHtml('error', text('selectPost', 'Please select a post first'))).show();
                return false;
            }
            return {
                postId: postId,
                $results: $results,
                restoreLabel: restoreLabel,
                type: type
            };
        }

        function runVariations(btn, type, restoreLabel) {
            var ctx = loadVariations(type, restoreLabel);
            if (!ctx) {
                return;
            }
            btn.prop('disabled', true).text(text('generating', 'Generating...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_meta_variations',
                    post_id: ctx.postId,
                    type: type,
                    nonce: aiseoAdmin.nonce
                },
                success: function (response) {
                    if (response.success && response.data && Array.isArray(response.data) && response.data.length) {
                        var list = $('<ol></ol>');
                        $.each(response.data, function (i, variation) {
                            var value = variation.text || variation.title || variation.description || variation;
                            var length = variation.length || String(value).length;
                            var item = $('<li></li>');
                            $('<strong></strong>').text(value).appendTo(item);
                            item.append(document.createTextNode(' (' + length + ' ' + text('characters', 'characters') + ')'));
                            list.append(item);
                        });
                        ctx.$results.show().find('.aiseo-result-content').empty().append(list);
                    } else if (response.success) {
                        ctx.$results.empty().append(noticeHtml('warning', text('noVariations', 'No variations generated. Try a different post.'))).show();
                    } else {
                        ctx.$results.empty().append(noticeHtml('error', String(response.data || text('failed', 'Failed to generate variations')))).show();
                    }
                },
                error: function () {
                    ctx.$results.empty().append(noticeHtml('error', text('connectionError', 'Connection error'))).show();
                },
                complete: function () {
                    restoreButton(btn, 'dashicons-admin-page', restoreLabel);
                }
            });
        }

        $('.aiseo-get-title-variations').on('click', function () {
            runVariations($(this), 'title', text('generateTitleVariations', 'Generate Title Variations'));
        });
        $('.aiseo-get-desc-variations').on('click', function () {
            runVariations($(this), 'description', text('generateDescVariations', 'Generate Description Variations'));
        });
    });
})(jQuery);
