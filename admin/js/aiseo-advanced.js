/**
 * Advanced tab.
 *
 * @package AISEO
 */
(function ($) {
    'use strict';

    if (typeof aiseoAdmin === 'undefined') {
        return;
    }

    var i18n = window.aiseoAdvanced && aiseoAdvanced.i18n ? aiseoAdvanced.i18n : {};

    function text(key, fallback) {
        return i18n[key] || fallback;
    }

    function escapeText(value) {
        return $('<div>').text(String(value == null ? '' : value)).html();
    }

    $(function () {
        $('.aiseo-advanced form').on('submit', function (e) {
            var $form = $(this);
            if ($form.find('[name="aiseo_cpt_form"]').length) {
                return;
            }
            e.preventDefault();
            var $btn = $form.find('button[type="submit"]');
            $btn.prop('disabled', true).text(text('saving', 'Saving...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=aiseo_save_cpt_settings&nonce=' + aiseoAdmin.nonce,
                success: function (response) {
                    window.alert(response.success ? text('saved', 'Settings saved successfully!') : (text('error', 'Error') + ': ' + response.data));
                },
                complete: function () {
                    $btn.prop('disabled', false).text(text('saveSettings', 'Save Post Type Settings'));
                }
            });
        });

        $('#aiseo-research-keyword').on('click', function () {
            var $btn = $(this);
            var keyword = $('#aiseo-keyword-input').val();
            var $resultDiv = $btn.parent().nextAll('.aiseo-keyword-results').first();
            if (!$resultDiv.length) {
                $resultDiv = $('<div class="aiseo-result-box aiseo-keyword-results"></div>').insertAfter($btn.parent());
            }
            if (!keyword) {
                $resultDiv.empty().append($('<p class="aiseo-index-warn"></p>').text(text('enterKeyword', 'Please enter a keyword'))).show();
                return;
            }
            $btn.prop('disabled', true).text(text('researching', 'Researching...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_keyword_research',
                    nonce: aiseoAdmin.nonce,
                    keyword: keyword
                },
                success: function (response) {
                    if (response.success && response.data) {
                        var wrap = $('<div class="aiseo-callout"></div>');
                        wrap.append($('<h4></h4>').text(text('suggestionsFor', 'Keyword Suggestions for:') + ' ' + (response.data.keyword || keyword)));
                        var list = $('<ul class="aiseo-list-compact"></ul>');
                        $.each(response.data.suggestions || [], function (i, kw) {
                            list.append($('<li></li>').text(kw));
                        });
                        wrap.append(list);
                        $resultDiv.empty().append(wrap).show();
                    } else {
                        $resultDiv.empty().append($('<p class="aiseo-index-warn"></p>').text(String(response.data || text('error', 'Error')))).show();
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + text('researchKeyword', 'Research Keyword'));
                }
            });
        });

        $('#aiseo-generate-report').on('click', function () {
            var $btn = $(this);
            var $resultDiv = $('#aiseo-report-results');
            $btn.prop('disabled', true).text(text('generating', 'Generating...'));
            $resultDiv.html('');
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_generate_report',
                    nonce: aiseoAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var html = '<div class="aiseo-callout"><h3>' + escapeText(text('reportTitle', 'SEO Report Generated')) + '</h3>';
                        html += '<table class="wp-list-table widefat"><thead><tr><th>' + escapeText(text('metric', 'Metric')) + '</th><th>' + escapeText(text('value', 'Value')) + '</th></tr></thead><tbody>';
                        html += '<tr><td>' + escapeText(text('postsAnalyzed', 'Posts Analyzed')) + '</td><td>' + escapeText(response.data.posts_analyzed) + '</td></tr>';
                        html += '<tr><td>' + escapeText(text('metadataGenerated', 'Metadata Generated')) + '</td><td>' + escapeText(response.data.metadata_generated) + '</td></tr>';
                        html += '<tr><td>' + escapeText(text('aiPosts', 'AI Posts Created')) + '</td><td>' + escapeText(response.data.ai_posts_created) + '</td></tr>';
                        html += '<tr><td>' + escapeText(text('apiRequests', 'API Requests')) + '</td><td>' + escapeText(response.data.api_requests) + '</td></tr></tbody></table></div>';
                        $resultDiv.html(html).show();
                        $('#aiseo-download-pdf').data('report', response.data).prop('disabled', false);
                    } else {
                        $resultDiv.empty().append($('<div class="notice notice-error"><p></p></div>').find('p').text(String(response.data)).end()).show();
                    }
                },
                error: function () {
                    $resultDiv.empty().append($('<div class="notice notice-error"><p></p></div>').find('p').text(text('connectionError', 'Connection error')).end()).show();
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-bar"></span> ' + text('generateReport', 'Generate Full Report'));
                }
            });
        });

        $('#aiseo-download-pdf').on('click', function () {
            var report = $(this).data('report');
            if (!report) {
                $(this).after($('<p class="aiseo-index-warn"></p>').text(text('noReport', 'No report data available')));
                return;
            }
            var pdfContent = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>AISEO Report</title></head><body>';
            pdfContent += '<h1>' + escapeText(text('reportHeading', 'AISEO SEO Report')) + '</h1>';
            pdfContent += '<p><strong>' + escapeText(text('generated', 'Generated')) + ':</strong> ' + escapeText(report.generated_at) + '</p>';
            pdfContent += '<table><tr><th>' + escapeText(text('metric', 'Metric')) + '</th><th>' + escapeText(text('value', 'Value')) + '</th></tr>';
            pdfContent += '<tr><td>' + escapeText(text('postsAnalyzed', 'Posts Analyzed')) + '</td><td>' + escapeText(report.posts_analyzed) + '</td></tr>';
            pdfContent += '<tr><td>' + escapeText(text('metadataGenerated', 'Metadata Generated')) + '</td><td>' + escapeText(report.metadata_generated) + '</td></tr>';
            pdfContent += '<tr><td>' + escapeText(text('aiPosts', 'AI Posts Created')) + '</td><td>' + escapeText(report.ai_posts_created) + '</td></tr>';
            pdfContent += '<tr><td>' + escapeText(text('apiRequests', 'API Requests')) + '</td><td>' + escapeText(report.api_requests) + '</td></tr></table>';
            if (report.recent_scores) {
                pdfContent += '<h2>' + escapeText(text('recentScores', 'Recent Post Scores')) + '</h2><table><tr><th>' + escapeText(text('postTitle', 'Post Title')) + '</th><th>' + escapeText(text('seoScore', 'SEO Score')) + '</th></tr>';
                $.each(report.recent_scores, function (i, item) {
                    pdfContent += '<tr><td>' + escapeText(item.title) + '</td><td>' + escapeText(item.score) + '</td></tr>';
                });
                pdfContent += '</table>';
            }
            pdfContent += '</body></html>';
            var blob = new Blob([pdfContent], { type: 'text/html' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'aiseo-report-' + Date.now() + '.html';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        });

        $('#aiseo-brief-form').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $('#aiseo-generate-brief');
            var topic = $form.find('[name="topic"]').val();
            var keyword = $form.find('[name="keyword"]').val();
            if (!topic) {
                window.alert(text('enterTopic', 'Please enter a topic'));
                return;
            }
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> ' + text('generatingBrief', 'Generating Brief...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_generate_brief',
                    topic: topic,
                    keyword: keyword,
                    nonce: aiseoAdmin.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        var brief = response.data;
                        var wrap = $('<div class="content-brief"></div>');
                        if (brief.title) {
                            wrap.append($('<h3></h3>').text(brief.title));
                        }
                        if (brief.keywords && brief.keywords.length) {
                            wrap.append($('<p></p>').append($('<strong></strong>').text(text('targetKeywords', 'Target keywords'))).append(document.createTextNode(': ' + brief.keywords.join(', '))));
                        }
                        if (brief.word_count) {
                            wrap.append($('<p></p>').append($('<strong></strong>').text(text('wordCount', 'Recommended word count'))).append(document.createTextNode(': ' + brief.word_count)));
                        }
                        if (brief.structure && brief.structure.length) {
                            var ol = $('<ol></ol>');
                            $.each(brief.structure, function (i, section) {
                                ol.append($('<li></li>').text(section));
                            });
                            wrap.append($('<p></p>').append($('<strong></strong>').text(text('structure', 'Content structure')))).append(ol);
                        }
                        if (brief.key_topics && brief.key_topics.length) {
                            var ul = $('<ul></ul>');
                            $.each(brief.key_topics, function (i, item) {
                                ul.append($('<li></li>').text(item));
                            });
                            wrap.append($('<p></p>').append($('<strong></strong>').text(text('keyTopics', 'Key topics')))).append(ul);
                        }
                        if (brief.seo_tips) {
                            wrap.append($('<p></p>').append($('<strong></strong>').text(text('seoTips', 'SEO tips'))).append(document.createTextNode(': ' + brief.seo_tips)));
                        }
                        $('#aiseo-brief-results').show().find('.aiseo-result-content').empty().append(wrap);
                        $('.aiseo-create-post-from-brief').show();
                    } else {
                        $('#aiseo-brief-results').show().find('.aiseo-result-content').text(String(response.data || text('failedBrief', 'Failed to generate brief')));
                        $('.aiseo-create-post-from-brief').hide();
                    }
                },
                error: function () {
                    $('#aiseo-brief-results').show().find('.aiseo-result-content').text(text('connectionError', 'Connection error'));
                    $('.aiseo-create-post-from-brief').hide();
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-edit-page"></span> ' + text('generateBrief', 'Generate Content Brief'));
                }
            });
        });

        $('.aiseo-create-post-from-brief').on('click', function () {
            var $btn = $(this);
            var briefContent = $('#aiseo-brief-results .aiseo-result-content').html();
            var topic = $('#aiseo-brief-form [name="topic"]').val();
            if (!briefContent) {
                window.alert(text('noBrief', 'No brief content to create post from'));
                return;
            }
            $btn.prop('disabled', true).text(text('creating', 'Creating Post...'));
            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                timeout: 30000,
                data: {
                    action: 'aiseo_create_post',
                    nonce: aiseoAdmin.nonce,
                    topic: topic || text('fromBrief', 'Generated from Content Brief'),
                    content: briefContent,
                    post_type: 'post',
                    length: 'medium'
                },
                success: function (response) {
                    if (response.success && response.data && response.data.edit_url) {
                        var notice = $('<div class="notice notice-success"><p></p></div>');
                        notice.find('p').append(document.createTextNode(text('postCreated', 'Post created. '))).append($('<a></a>').attr({ href: response.data.edit_url, target: '_blank', rel: 'noopener noreferrer' }).text(text('editPost', 'Edit post')));
                        $btn.after(notice);
                    } else {
                        $btn.after($('<div class="notice notice-error"><p></p></div>').find('p').text(String(response.data || text('error', 'Error'))).end());
                    }
                },
                error: function () {
                    $btn.after($('<div class="notice notice-error"><p></p></div>').find('p').text(text('connectionError', 'Connection error')).end());
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus"></span> ' + text('createFromBrief', 'Create Post from Brief'));
                }
            });
        });
    });
})(jQuery);
