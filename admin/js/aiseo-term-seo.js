/**
 * Category and tag SEO generate buttons.
 *
 * @package AISEO
 */
(function ($) {
    'use strict';

    var cfg = window.aiseoTermSeo || {};
    var i18n = cfg.i18n || {};

    function text(key, fallback) {
        return i18n[key] || fallback;
    }

    $(function () {
        $(document).on('click', '.aiseo-generate-term-seo', function () {
            var $btn = $(this);
            var field = $btn.data('field');
            var termId = parseInt($btn.data('term-id'), 10);
            if (!termId || (field !== 'title' && field !== 'description')) {
                return;
            }
            $btn.prop('disabled', true).text(text('generating', 'Generating...'));
            $.ajax({
                url: cfg.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_generate_term_seo',
                    nonce: cfg.nonce,
                    term_id: termId,
                    taxonomy: cfg.taxonomy || '',
                    field: field
                },
                success: function (response) {
                    if (response && response.success && response.data) {
                        var $field = field === 'title' ? $('#aiseo_title') : $('#aiseo_description');
                        $field.val(response.data);
                    } else {
                        window.alert(String((response && response.data) || text('failed', 'Failed to generate')));
                    }
                },
                error: function () {
                    window.alert(text('connectionError', 'Connection error'));
                },
                complete: function () {
                    var label = field === 'title' ? text('generateTitle', 'Generate title') : text('generateDescription', 'Generate description');
                    $btn.prop('disabled', false).text(label);
                }
            });
        });
    });
})(jQuery);
