/**
 * AI Content tab.
 *
 * @package AISEO
 */
(function ($) {
    'use strict';

    if (typeof aiseoAdmin === 'undefined') {
        return;
    }

    var i18n = window.aiseoAiContent && aiseoAiContent.i18n ? aiseoAiContent.i18n : {};

    function text(key, fallback) {
        return i18n[key] || fallback;
    }

    function errorParagraph(message) {
        return $('<p class="aiseo-index-warn"></p>').text(message);
    }

    function successNotice(editUrl) {
        var notice = $('<div class="notice notice-success is-dismissible"></div>');
        var p = $('<p></p>');
        p.append($('<strong></strong>').text(text('success', 'Success!')));
        p.append(document.createTextNode(' ' + text('postCreated', 'Post created!') + ' '));
        if (editUrl) {
            p.append($('<a></a>').attr({ href: editUrl, target: '_blank', rel: 'noopener noreferrer' }).text(text('editPost', 'Edit post')));
        }
        return notice.append(p);
    }

    function errorNotice(message) {
        var notice = $('<div class="notice notice-error"></div>');
        var p = $('<p></p>');
        p.append($('<strong></strong>').text(text('error', 'Error:')));
        p.append(document.createTextNode(' ' + message));
        return notice.append(p);
    }

    function itemText(item) {
        if (typeof item === 'string') {
            return item;
        }
        if (!item || typeof item !== 'object') {
            return String(item);
        }
        return item.text || item.title || item.heading || JSON.stringify(item);
    }

    function parseMarkdown(raw) {
        if (!raw) {
            return '';
        }
        var html = String(raw)
            .replace(/^### (.*$)/gim, '<h3>$1</h3>')
            .replace(/^## (.*$)/gim, '<h2>$1</h2>')
            .replace(/^# (.*$)/gim, '<h1>$1</h1>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/__(.+?)__/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/_(.+?)_/g, '<em>$1</em>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
            .replace(/\n\n/g, '</p><p>')
            .replace(/\n/g, '<br>');
        if (html.indexOf('<h') !== 0 && html.indexOf('<p') !== 0) {
            html = '<p>' + html + '</p>';
        }
        return html;
    }

    function showResult($box, $content) {
        $box.show().find('.aiseo-result-content').empty().append($content);
    }

    function restoreButton($btn, icon, label) {
        $btn.prop('disabled', false).html('<span class="dashicons ' + icon + '"></span> ' + label);
    }

    $(function () {
        $('.aiseo-generate-post').on('click', function () {
            var $btn = $(this);
            var $form = $('#aiseo-create-post-form');
            var topic = $form.find('[name="topic"]').val().trim();
            var keyword = $form.find('[name="keyword"]').val();
            var postType = $form.find('[name="post_type"]').val();
            var length = $form.find('[name="content_length"]').val();
            var $results = $('#aiseo-post-creator-results');

            if (!topic) {
                window.alert(text('enterTopic', 'Please enter a topic'));
                return;
            }

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> ' + text('generating', 'Generating Content...'));

            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                timeout: 60000,
                data: {
                    action: 'aiseo_generate_content',
                    nonce: aiseoAdmin.nonce,
                    topic: topic,
                    keyword: keyword,
                    post_type: postType,
                    length: length
                },
                success: function (response) {
                    if (response.success && response.data) {
                        $results.show().find('.aiseo-result-content').html(parseMarkdown(response.data));
                    } else {
                        showResult($results, errorParagraph(text('error', 'Error:') + ' ' + String(response.data || text('failedContent', 'Failed to generate content'))));
                    }
                },
                error: function (xhr, status) {
                    var message = text('connectionError', 'Connection error');
                    if (status === 'timeout') {
                        message = text('timeout60', 'Request timed out (60s). Please try again with shorter content.');
                    }
                    showResult($results, errorParagraph(text('error', 'Error:') + ' ' + message));
                },
                complete: function () {
                    restoreButton($btn, 'dashicons-edit', text('generatePost', 'Generate Post'));
                }
            });
        });

        $('.aiseo-create-post-from-generator').on('click', function () {
            var $btn = $(this);
            var content = $('#aiseo-post-creator-results .aiseo-result-content').html();
            var topic = $('#aiseo-create-post-form [name="topic"]').val() || $('#aiseo-post-creator-form [name="topic"]').val();
            var postType = $('#aiseo-post-creator-results .aiseo-post-type-select').val();

            if (!content) {
                window.alert(text('noContent', 'No content to create post from'));
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
                    topic: topic,
                    content: content,
                    post_type: postType,
                    length: 'medium'
                },
                success: function (response) {
                    sessionStorage.removeItem('aiseo_nonce_refresh_attempted');
                    if (response.success && response.data && response.data.edit_url) {
                        $btn.after(successNotice(response.data.edit_url));
                    } else {
                        $btn.after(errorNotice(String(response.data || text('errorOccurred', 'Error occurred'))));
                    }
                },
                error: function (xhr, status) {
                    var message = text('connectionError', 'Connection error');
                    if (status === 'timeout') {
                        message = text('timeout30', 'Request timed out (30s). The post may still be creating in the background.');
                    }
                    $btn.after(errorNotice(message));
                },
                complete: function () {
                    restoreButton($btn, 'dashicons-plus', text('createPost', 'Create Post'));
                }
            });
        });

        $('.aiseo-rewrite-btn').on('click', function () {
            var $btn = $(this);
            var $form = $('#aiseo-rewrite-form');
            var content = $form.find('[name="content"]').val();
            var mode = $form.find('[name="mode"]').val();
            var $result = $('#aiseo-rewrite-result');

            if (!content) {
                showResult($result, errorParagraph(text('enterRewrite', 'Please enter content to rewrite')));
                return;
            }

            $btn.prop('disabled', true).text(text('rewriting', 'Rewriting...'));

            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_rewrite_content',
                    nonce: aiseoAdmin.nonce,
                    content: content,
                    mode: mode
                },
                success: function (response) {
                    if (response.success && response.data) {
                        var rewritten = '';
                        if (typeof response.data === 'string') {
                            rewritten = response.data;
                        } else if (response.data.rewritten) {
                            rewritten = response.data.rewritten;
                        } else if (response.data.content) {
                            rewritten = response.data.content;
                        } else if (response.data.rewritten_content) {
                            rewritten = response.data.rewritten_content;
                        } else if (response.data.text) {
                            rewritten = response.data.text;
                        }
                        if (rewritten) {
                            var wrap = $('<div class="aiseo-result-inner"></div>');
                            wrap.html(parseMarkdown(rewritten));
                            showResult($result, wrap);
                        } else {
                            showResult($result, errorParagraph(text('unexpectedFormat', 'Rewrite completed but content format is unexpected.')));
                        }
                    } else {
                        var errorMsg = response.data || text('rewriteFailed', 'Rewrite failed');
                        if (typeof errorMsg === 'object') {
                            errorMsg = JSON.stringify(errorMsg);
                        }
                        showResult($result, errorParagraph(text('error', 'Error:') + ' ' + errorMsg));
                    }
                },
                error: function () {
                    showResult($result, errorParagraph(text('connectionError', 'Connection error')));
                },
                complete: function () {
                    restoreButton($btn, 'dashicons-update', text('rewriteContent', 'Rewrite Content'));
                }
            });
        });

        $('.aiseo-suggestions-btn').on('click', function () {
            var $btn = $(this);
            var topic = $('#aiseo-suggestions-form').find('[name="topic"]').val();
            var $result = $('#aiseo-suggestions-result');
            var $list = $result.show().find('.aiseo-result-list').empty();

            if (!topic) {
                $list.append($('<li class="aiseo-index-warn"></li>').text(text('enterTopic', 'Please enter a topic')));
                return;
            }

            $btn.prop('disabled', true).text(text('gettingSuggestions', 'Getting suggestions...'));

            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_content_suggestions',
                    nonce: aiseoAdmin.nonce,
                    topic: topic
                },
                success: function (response) {
                    if (response.success && response.data) {
                        if (Array.isArray(response.data) && response.data.length) {
                            $.each(response.data, function (i, suggestion) {
                                $list.append($('<li></li>').text(itemText(suggestion)));
                            });
                        } else {
                            $list.append($('<li></li>').text(text('noSuggestions', 'No suggestions generated. Try a different topic.')));
                        }
                    } else {
                        var errorMsg = response.data || text('failedSuggestions', 'Failed to get suggestions');
                        if (typeof errorMsg === 'object') {
                            errorMsg = JSON.stringify(errorMsg);
                        }
                        $list.append($('<li class="aiseo-index-warn"></li>').text(text('error', 'Error:') + ' ' + errorMsg));
                    }
                },
                error: function () {
                    $list.append($('<li class="aiseo-index-warn"></li>').text(text('connectionError', 'Connection error')));
                },
                complete: function () {
                    restoreButton($btn, 'dashicons-lightbulb', text('getSuggestions', 'Get Suggestions'));
                }
            });
        });

        $('.aiseo-outline-btn').on('click', function () {
            var $btn = $(this);
            var $form = $('#aiseo-outline-form');
            var topic = $form.find('[name="topic"]').val();
            var keyword = $form.find('[name="keyword"]').val();
            var $result = $('#aiseo-outline-result');

            if (!topic) {
                showResult($result, errorParagraph(text('enterTopic', 'Please enter a topic')));
                return;
            }

            $btn.prop('disabled', true).text(text('generating', 'Generating...'));

            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_generate_outline',
                    nonce: aiseoAdmin.nonce,
                    topic: topic,
                    keyword: keyword
                },
                success: function (response) {
                    if (!response.success || !response.data) {
                        var errorMsg = response.data || text('failedOutline', 'Failed to generate outline');
                        if (typeof errorMsg === 'object') {
                            errorMsg = JSON.stringify(errorMsg);
                        }
                        showResult($result, errorParagraph(text('error', 'Error:') + ' ' + errorMsg));
                        return;
                    }

                    var wrap = $('<div class="aiseo-result-inner outline-structured"></div>');
                    var data = response.data;

                    function appendList(title, items) {
                        if (!items || !items.length) {
                            return;
                        }
                        wrap.append($('<h4></h4>').text(title));
                        var ul = $('<ul></ul>');
                        $.each(items, function (i, item) {
                            ul.append($('<li></li>').text(itemText(item)));
                        });
                        wrap.append(ul);
                    }

                    if (typeof data === 'string') {
                        wrap.text(data);
                    } else if (data.outline && (data.outline.introduction || data.outline.sections || data.outline.conclusion)) {
                        appendList(text('introduction', 'Introduction'), data.outline.introduction);
                        if (data.outline.sections && data.outline.sections.length) {
                            wrap.append($('<h4></h4>').text(text('mainSections', 'Main Sections')));
                            var sections = $('<ul></ul>');
                            $.each(data.outline.sections, function (i, section) {
                                var li = $('<li></li>');
                                li.append($('<strong></strong>').text(section.title || section.heading || text('section', 'Section') + ' ' + (i + 1)));
                                if (section.subsections && section.subsections.length) {
                                    var sub = $('<ul></ul>');
                                    $.each(section.subsections, function (j, nested) {
                                        sub.append($('<li></li>').text(itemText(nested)));
                                    });
                                    li.append(sub);
                                }
                                sections.append(li);
                            });
                            wrap.append(sections);
                        }
                        appendList(text('conclusion', 'Conclusion'), data.outline.conclusion);
                        appendList(text('cta', 'Call to Action'), data.outline.cta);
                    } else if (data.outline && typeof data.outline === 'string') {
                        wrap.text(data.outline);
                    } else if (data.outline && Array.isArray(data.outline)) {
                        appendList('', data.outline);
                    } else if (data.content) {
                        wrap.text(data.content);
                    } else if (Array.isArray(data)) {
                        appendList('', data);
                    } else {
                        wrap.append($('<pre></pre>').text(JSON.stringify(data, null, 2)));
                    }

                    if (wrap.children().length || wrap.text()) {
                        showResult($result, wrap);
                    } else {
                        showResult($result, errorParagraph(text('emptyOutline', 'No outline generated. Try a different topic or check your API key.')));
                    }
                },
                error: function () {
                    showResult($result, errorParagraph(text('connectionError', 'Connection error')));
                },
                complete: function () {
                    restoreButton($btn, 'dashicons-list-view', text('generateOutline', 'Generate Outline'));
                }
            });
        });

        $('.aiseo-faq-btn').on('click', function () {
            var $btn = $(this);
            var $form = $('#aiseo-faq-form');
            var content = $form.find('[name="content"]').val();
            var count = $form.find('[name="count"]').val();
            var $result = $('#aiseo-faq-result');

            if (!content) {
                showResult($result, errorParagraph(text('enterContent', 'Please enter content')));
                return;
            }

            $btn.prop('disabled', true).text(text('generating', 'Generating...'));

            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_generate_faq',
                    nonce: aiseoAdmin.nonce,
                    content: content,
                    count: count
                },
                success: function (response) {
                    if (!response.success || !response.data) {
                        var errorMsg = response.data || text('failedFaq', 'Failed to generate FAQs');
                        if (typeof errorMsg === 'object') {
                            errorMsg = JSON.stringify(errorMsg);
                        }
                        showResult($result, errorParagraph(text('error', 'Error:') + ' ' + errorMsg));
                        return;
                    }

                    var faqs = [];
                    if (Array.isArray(response.data)) {
                        faqs = response.data;
                    } else if (response.data.faqs && Array.isArray(response.data.faqs)) {
                        faqs = response.data.faqs;
                    }

                    var wrap = $('<div class="aiseo-result-inner"></div>');
                    if (faqs.length) {
                        $.each(faqs, function (i, faq) {
                            var item = $('<div class="faq-item"></div>');
                            item.append($('<strong></strong>').text('Q: ' + (faq.question || faq.q || text('noQuestion', 'No question'))));
                            item.append($('<p></p>').text('A: ' + (faq.answer || faq.a || text('noAnswer', 'No answer'))));
                            wrap.append(item);
                        });
                        showResult($result, wrap);
                    } else if (response.data.html && response.data.html !== '<div class="aiseo-faq"></div>') {
                        wrap.html(response.data.html);
                        showResult($result, wrap);
                    } else {
                        showResult($result, errorParagraph(text('emptyFaq', 'No FAQs generated. Try different content or check your API key.')));
                    }
                },
                error: function () {
                    showResult($result, errorParagraph(text('connectionError', 'Connection error')));
                },
                complete: function () {
                    restoreButton($btn, 'dashicons-editor-help', text('generateFaqs', 'Generate FAQs'));
                }
            });
        });

        $(document).on('click', '.aiseo-copy-result', function () {
            var $btn = $(this);
            var copied = $btn.closest('.aiseo-result-box').find('.aiseo-result-content').text();
            if (!navigator.clipboard || !navigator.clipboard.writeText) {
                return;
            }
            navigator.clipboard.writeText(copied).then(function () {
                var originalText = $btn.text();
                $btn.text(text('copied', 'Copied!'));
                setTimeout(function () {
                    $btn.text(originalText);
                }, 2000);
            });
        });

        $(document).on('click', '.aiseo-create-post-from-result', function () {
            var $btn = $(this);
            var $resultBox = $btn.closest('.aiseo-result-box');
            var content = $resultBox.find('.aiseo-result-content').html();
            var postType = $resultBox.find('.aiseo-post-type-select').val();
            var source = $btn.data('source');

            $btn.prop('disabled', true).text(text('creating', 'Creating...'));

            var tempDiv = $('<div>').html(content);
            var title = '';

            if (source === 'faq') {
                var faqText = tempDiv.text().trim();
                var qMatch = faqText.match(/Q:\s*([^?]+\?)/i);
                if (qMatch) {
                    title = qMatch[1].trim();
                }
            }
            if (!title) {
                title = tempDiv.find('h1, h2, h3').first().text();
            }
            if (!title) {
                title = tempDiv.text().trim().split(/[.!?]/)[0].substring(0, 100);
            }
            if (!title) {
                title = text('generatedFrom', 'Generated from') + ' ' + source;
            }

            $.ajax({
                url: aiseoAdmin.ajaxUrl,
                type: 'POST',
                timeout: 30000,
                data: {
                    action: 'aiseo_create_post',
                    nonce: aiseoAdmin.nonce,
                    topic: title,
                    content: content,
                    post_type: postType,
                    length: 'medium'
                },
                success: function (response) {
                    if (response.success && response.data && response.data.edit_url) {
                        $resultBox.prepend(successNotice(response.data.edit_url));
                    } else {
                        $resultBox.prepend(errorNotice(String(response.data || text('errorOccurred', 'Error occurred'))));
                    }
                },
                error: function (xhr, status) {
                    var message = text('connectionError', 'Connection error');
                    if (status === 'timeout') {
                        message = text('timeout30', 'Request timed out (30s). The post may still be creating in the background.');
                    } else if (xhr.status === 500) {
                        message = text('serverError', 'Server error (500). Check if the post was created anyway.');
                    }
                    $resultBox.prepend(errorNotice(message));
                },
                complete: function () {
                    restoreButton($btn, 'dashicons-plus', text('createPost', 'Create Post'));
                }
            });
        });
    });
})(jQuery);
