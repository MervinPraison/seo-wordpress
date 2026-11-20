/**
 * Block toolbar dropdown for rewriting selected text blocks.
 *
 * @package AISEO
 */
(function (wp, $) {
    'use strict';

    if (!wp || !wp.hooks || !wp.compose || !wp.element || !wp.components || !wp.data || !wp.i18n) {
        return;
    }

    var addFilter = wp.hooks.addFilter;
    var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var BlockControls = wp.blockEditor && wp.blockEditor.BlockControls;
    var ToolbarGroup = wp.components.ToolbarGroup;
    var ToolbarDropdownMenu = wp.components.ToolbarDropdownMenu;
    var select = wp.data.select;
    var dispatch = wp.data.dispatch;
    var __ = wp.i18n.__;

    if (!BlockControls || typeof aiseoGutenbergData === 'undefined') {
        return;
    }

    var rewriteModes = [
        { title: __('Fix grammar', 'aiseo'), icon: 'yes', mode: 'grammar' },
        { title: __('Improve clarity', 'aiseo'), icon: 'visibility', mode: 'clarity' },
        { title: __('Shorten', 'aiseo'), icon: 'editor-contract', mode: 'concise' },
        { title: __('Professional tone', 'aiseo'), icon: 'businessman', mode: 'professional' },
        { title: __('Casual tone', 'aiseo'), icon: 'smiley', mode: 'casual' },
        { title: __('Simplify', 'aiseo'), icon: 'lightbulb', mode: 'simplify' }
    ];

    var supportedBlocks = [
        'core/paragraph',
        'core/heading',
        'core/list',
        'core/quote',
        'core/verse',
        'core/code'
    ];

    function rewriteBlockContent(clientId, mode) {
        var block = select('core/block-editor').getBlock(clientId);
        if (!block) {
            return;
        }
        var content = block.attributes.content || block.attributes.text || block.attributes.value || '';
        var textContent = '';
        if (window.DOMParser) {
            var parsed = new DOMParser().parseFromString(String(content), 'text/html');
            textContent = ((parsed.body && parsed.body.textContent) || '').trim();
        } else {
            textContent = String(content).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        }
        if (!textContent) {
            window.alert(__('This block has no text to rewrite.', 'aiseo'));
            return;
        }
        var originalContent = content;
        dispatch('core/block-editor').updateBlockAttributes(clientId, {
            content: '<em>' + __('Rewriting…', 'aiseo') + '</em>'
        });
        $.ajax({
            url: aiseoGutenbergData.ajaxUrl || window.ajaxurl,
            type: 'POST',
            data: {
                action: 'aiseo_rewrite_content',
                nonce: aiseoGutenbergData.nonce,
                content: textContent,
                mode: mode,
                minimal: true,
                preserve_meaning: true
            },
            success: function (response) {
                if (response && response.success && response.data) {
                    dispatch('core/block-editor').updateBlockAttributes(clientId, { content: response.data });
                } else {
                    dispatch('core/block-editor').updateBlockAttributes(clientId, { content: originalContent });
                    window.alert(__('Could not rewrite this block.', 'aiseo'));
                }
            },
            error: function () {
                dispatch('core/block-editor').updateBlockAttributes(clientId, { content: originalContent });
                window.alert(__('Connection error. Please try again.', 'aiseo'));
            }
        });
    }

    var withRewriteButton = createHigherOrderComponent(function (BlockEdit) {
        return function (props) {
            var showButton = props.isSelected && supportedBlocks.indexOf(props.name) !== -1;
            var controls = rewriteModes.map(function (mode) {
                return {
                    title: mode.title,
                    icon: mode.icon,
                    onClick: function () {
                        rewriteBlockContent(props.clientId, mode.mode);
                    }
                };
            });
            return el(
                Fragment,
                null,
                el(BlockEdit, props),
                showButton ? el(
                    BlockControls,
                    { group: 'block' },
                    el(ToolbarGroup, null,
                        el(ToolbarDropdownMenu, {
                            icon: 'edit',
                            label: __('AI rewrite', 'aiseo'),
                            controls: controls
                        })
                    )
                ) : null
            );
        };
    }, 'withRewriteButton');

    addFilter('editor.BlockEdit', 'aiseo/rewrite-button', withRewriteButton);
})(window.wp, window.jQuery);
