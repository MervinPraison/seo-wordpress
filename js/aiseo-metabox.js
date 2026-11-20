/**
 * AISEO Metabox JavaScript
 * Handles AI generation buttons and content analysis
 */

(function($) {
    'use strict';
    
    // Wait for DOM ready
    $(document).ready(function() {
        
        // Check if we're on the post editor
        if (!$('.aiseo-metabox').length) {
            return;
        }

        $('.aiseo-metabox-tab').on('click', function() {
            var tab = $(this);
            var panelId = tab.attr('aria-controls');
            tab.addClass('is-active').attr('aria-selected', 'true')
                .siblings('.aiseo-metabox-tab').removeClass('is-active').attr('aria-selected', 'false');
            $('#' + panelId).removeAttr('hidden').addClass('is-active')
                .siblings('.aiseo-metabox-panel').attr('hidden', 'hidden').removeClass('is-active');
        });
        
        // Character counter
        function updateCharCount(input, counter) {
            var count = input.val().length;
            counter.find('.aiseo-current-count').text(count);
            
            var maxChars = input.attr('maxlength') || 160;
            if (count > maxChars * 0.9) {
                counter.addClass('warning');
            } else {
                counter.removeClass('warning');
            }
        }
        
        // Initialize character counters
        $('.aiseo-title-input').on('input', function() {
            updateCharCount($(this), $(this).parent().parent().find('.aiseo-char-count'));
        }).trigger('input');
        
        $('.aiseo-description-input').on('input', function() {
            updateCharCount($(this), $(this).parent().find('.aiseo-char-count'));
        }).trigger('input');
        
        // Advanced settings - native details/summary element handles toggle automatically
        
        // Generate with AI button
        $('.aiseo-generate-btn').on('click', function(e) {
            e.preventDefault();
            
            var btn = $(this);
            var field = btn.data('field');
            var input;
            
            // Determine which input field to update
            if (field === 'keyword') {
                input = $('#aiseo_focus_keyword');
            } else if (field === 'title') {
                input = $('#aiseo_meta_title');
            } else {
                input = $('#aiseo_meta_description');
            }
            
            var postId = $('#post_ID').val();
            
            if (!postId) {
                alert('Please save the post first before generating AI content.');
                return;
            }
            
            btn.prop('disabled', true).text('Generating...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aiseo_metabox_generate_' + field,
                    post_id: postId,
                    nonce: $('#aiseo_metabox_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        input.val(response.data).trigger('input');
                    } else {
                        alert('Error: ' + (response.data || 'Failed to generate'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AISEO Generate Error:', error);
                    alert('Error: Failed to connect to server');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Generate with AI');
                }
            });
        });
        
        // Analyze content button
        $('.aiseo-analyze-btn').on('click', function(e) {
            e.preventDefault();
            
            var btn = $(this);
            var postId = $('#post_ID').val();
            var resultsDiv = $('.aiseo-analysis-results');
            
            if (!postId) {
                alert('Please save the post first before analyzing content.');
                return;
            }
            
            btn.prop('disabled', true).text('Analysing...');
            resultsDiv.attr('hidden', 'hidden');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aiseo_metabox_analyze_content',
                    post_id: postId,
                    nonce: $('#aiseo_metabox_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<ul>';
                        
                        // Map of analysis keys to readable labels
                        var labels = {
                            'keyword_density': 'Keyword Density',
                            'readability': 'Readability',
                            'paragraph_structure': 'Paragraph Structure',
                            'sentence_length': 'Sentence Length',
                            'content_length': 'Content Length',
                            'keyword_in_title': 'Keyword in Title',
                            'keyword_in_headings': 'Keyword in Headings',
                            'keyword_in_intro': 'Keyword in Introduction',
                            'internal_links': 'Internal Links',
                            'external_links': 'External Links',
                            'images': 'Images'
                        };
                        
                        $.each(response.data.analyses, function(key, analysis) {
                            var label = labels[key] || key;
                            var statusClass = analysis.status === 'good' ? 'good' : (analysis.status === 'ok' ? 'warning' : 'poor');
                            var score = parseInt(analysis.score, 10);
                            if (isNaN(score)) {
                                score = 0;
                            }
                            html += '<li class="aiseo-check-' + statusClass + '"><strong>' + $('<div>').text(label).html() + ':</strong> ' + score + '/100 — ' + $('<div>').text(analysis.message || '').html() + '</li>';
                        });
                        html += '</ul>';
                        html += '<p><strong>Overall score: ' + response.data.overall_score + '/100</strong></p>';
                        
                        resultsDiv.find('.aiseo-analysis-content').html(html);
                        resultsDiv.removeAttr('hidden');
                    } else {
                        alert('Error: ' + (response.data || 'Failed to analyze'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AISEO Analyze Error:', error);
                    alert('Error: Failed to connect to server');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-bar"></span> Analyze Content');
                }
            });
        });
        
        function refreshSnippet() {
            var title = $('#aiseo_meta_title').val() || $('.editor-post-title__input').val() || $('input[name="post_title"]').val() || '';
            var description = $('#aiseo_meta_description').val() || '';
            var canonical = $('#aiseo_canonical_url').val();
            var shareTitle = $('#aiseo_og_title').val() || title;
            var shareDescription = $('#aiseo_og_description').val() || description;
            $('.aiseo-snippet-title').text(title);
            $('.aiseo-snippet-description').text(description);
            $('.aiseo-share-title').text(shareTitle);
            $('.aiseo-share-description').text(shareDescription);
            if (canonical) {
                $('.aiseo-snippet-url').text(canonical.replace(/^https?:\/\//, '').split('?')[0]);
            }
        }

        $('#aiseo_meta_title, #aiseo_meta_description, #aiseo_canonical_url, #aiseo_og_title, #aiseo_og_description').on('input', refreshSnippet);

        function htmlToPlainText(html) {
            if (!html) {
                return '';
            }
            if (window.DOMParser) {
                var doc = new DOMParser().parseFromString(String(html), 'text/html');
                return ((doc.body && doc.body.textContent) || '').replace(/\s+/g, ' ').trim();
            }
            return String(html).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        }

        function editorPlainText() {
            if (window.wp && wp.data && wp.data.select('core/editor')) {
                try {
                    return htmlToPlainText(wp.data.select('core/editor').getEditedPostContent() || '');
                } catch (ignore) {
                    /* Classic editor fallback below. */
                }
            }
            if (window.tinymce && tinymce.get('content')) {
                return tinymce.get('content').getContent({ format: 'text' }) || '';
            }
            return $('#content').val() || '';
        }

        function fleschEase(text) {
            if (!text) {
                return null;
            }
            var sentences = text.split(/[.!?]+/).filter(function (part) {
                return part.trim().length > 0;
            }).length;
            var words = text.split(/\s+/).filter(Boolean).length;
            if (!sentences || !words) {
                return null;
            }
            var syllables = 0;
            text.toLowerCase().split(/\s+/).forEach(function (word) {
                var clean = word.replace(/[^a-z]/g, '');
                if (!clean) {
                    return;
                }
                var groups = clean.match(/[aeiouy]+/g);
                syllables += groups ? groups.length : 1;
            });
            var score = 206.835 - 1.015 * (words / sentences) - 84.6 * (syllables / words);
            return Math.max(0, Math.min(100, Math.round(score * 10) / 10));
        }

        function liveCheckItem(status, message) {
            var icon = status === 'good' ? 'yes-alt' : (status === 'poor' ? 'dismiss' : 'warning');
            return '<li class="aiseo-check-' + status + '"><span class="dashicons dashicons-' + icon + '" aria-hidden="true"></span> ' + $('<div>').text(message).html() + '</li>';
        }

        function refreshLiveChecks() {
            var title = $('#aiseo_meta_title').val() || '';
            var description = $('#aiseo_meta_description').val() || '';
            var keyword = ($('#aiseo_focus_keyword').val() || '').trim();
            var extra = ($('#aiseo_additional_keywords').val() || '').split(/[\n,]+/).map(function (item) {
                return item.trim();
            }).filter(Boolean).slice(0, 4);
            var content = editorPlainText();
            var haystack = (title + ' ' + content).toLowerCase();
            var items = [];
            var titleLen = title.length;
            items.push(liveCheckItem(
                (titleLen >= 50 && titleLen <= 60) ? 'good' : 'warning',
                'SEO title length is ' + titleLen + ' characters. Aim for 50 to 60.'
            ));
            var descLen = description.length;
            items.push(liveCheckItem(
                (descLen >= 120 && descLen <= 160) ? 'good' : 'warning',
                'Meta description length is ' + descLen + ' characters. Aim for 120 to 160.'
            ));
            if (keyword) {
                items.push(liveCheckItem(
                    haystack.indexOf(keyword.toLowerCase()) !== -1 ? 'good' : 'poor',
                    haystack.indexOf(keyword.toLowerCase()) !== -1
                        ? 'The focus keyword appears in the title or content.'
                        : 'The focus keyword does not appear in the title or content.'
                ));
            }
            extra.forEach(function (phrase) {
                if (phrase.toLowerCase() === keyword.toLowerCase()) {
                    return;
                }
                items.push(liveCheckItem(
                    haystack.indexOf(phrase.toLowerCase()) !== -1 ? 'good' : 'warning',
                    haystack.indexOf(phrase.toLowerCase()) !== -1
                        ? 'Extra keyword “' + phrase + '” appears in the content.'
                        : 'Extra keyword “' + phrase + '” is missing from the content.'
                ));
            });
            var flesch = fleschEase(content);
            var readItems = [];
            if (flesch !== null) {
                var readStatus = (flesch >= 60 && flesch <= 80) || flesch >= 80 ? 'good' : (flesch >= 50 ? 'good' : 'warning');
                var readMsg = 'Flesch Reading Ease: ' + flesch + '. 60–80 is comfortable to read. This is not a search ranking.';
                items.push(liveCheckItem(readStatus, readMsg));
                readItems.push(liveCheckItem(readStatus, readMsg));
                $('.aiseo-readability-score strong').text('Flesch Reading Ease: ' + flesch);
            }
            var score = 50;
            if (titleLen >= 50 && titleLen <= 60) {
                score += 15;
            }
            if (descLen >= 120 && descLen <= 160) {
                score += 15;
            }
            if (keyword && haystack.indexOf(keyword.toLowerCase()) !== -1) {
                score += 15;
            }
            if (flesch !== null && flesch >= 50) {
                score += 5;
            }
            if (score > 100) {
                score = 100;
            }
            var ringStatus = score >= 80 ? 'good' : (score >= 50 ? 'ok' : 'poor');
            $('.aiseo-score-ring-number').text(String(score));
            $('.aiseo-score-stroke')
                .attr('stroke-dasharray', ((score / 100) * 188) + ' 188')
                .removeClass('aiseo-score-stroke-good aiseo-score-stroke-ok aiseo-score-stroke-poor')
                .addClass('aiseo-score-stroke-' + ringStatus);
            $('.aiseo-score-ring-copy p').text('Status: ' + ringStatus.charAt(0).toUpperCase() + ringStatus.slice(1));
            $('#aiseo-live-checks').html(items.join(''));
            if (readItems.length) {
                $('#aiseo-live-readability').html(readItems.join(''));
            }
        }

        var liveTimer;
        function queueLiveChecks() {
            clearTimeout(liveTimer);
            liveTimer = setTimeout(refreshLiveChecks, 300);
        }
        $('#aiseo_meta_title, #aiseo_meta_description, #aiseo_focus_keyword, #aiseo_additional_keywords, #title, #content').on('input', queueLiveChecks);
        $(document).on('tinymce-editor-keyup', queueLiveChecks);
        if (window.wp && wp.data && typeof wp.data.subscribe === 'function') {
            var lastEditorContent = '';
            wp.data.subscribe(function() {
                try {
                    var editor = wp.data.select('core/editor');
                    var next = editor && editor.getEditedPostContent ? (editor.getEditedPostContent() || '') : '';
                    if (next === lastEditorContent) {
                        return;
                    }
                    lastEditorContent = next;
                    queueLiveChecks();
                } catch (ignore) {
                    /* Block editor store not ready. */
                }
            });
        }

        var ogFrame;
        $('.aiseo-og-image-select').on('click', function(e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }
            if (ogFrame) {
                ogFrame.open();
                return;
            }
            ogFrame = wp.media({
                title: 'Social image',
                button: { text: 'Use this image' },
                library: { type: 'image' },
                multiple: false
            });
            ogFrame.on('select', function() {
                var attachment = ogFrame.state().get('selection').first().toJSON();
                var url = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
                $('#aiseo_og_image').val(attachment.id);
                $('.aiseo-og-preview-img, .aiseo-share-image').attr('src', url).show();
                $('.aiseo-og-image-remove').prop('disabled', false);
            });
            ogFrame.open();
        });

        $('.aiseo-og-image-remove').on('click', function(e) {
            e.preventDefault();
            $('#aiseo_og_image').val('0');
            $('.aiseo-og-preview-img').attr('src', '').hide();
            $(this).prop('disabled', true);
        });
        $('.aiseo-preview-btn').on('click', function(e) {
            e.preventDefault();
            $('#aiseo-tab-seo').trigger('click');
            refreshSnippet();
            var resultsDiv = $('.aiseo-preview-results');
            if (resultsDiv.length) {
                $('html, body').animate({ scrollTop: resultsDiv.offset().top - 100 }, 300);
            }
        });
        
    });
    
})(jQuery);
