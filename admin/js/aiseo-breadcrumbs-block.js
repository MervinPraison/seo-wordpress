/**
 * Gutenberg breadcrumbs block.
 *
 * @package AISEO
 */
(function (wp) {
    'use strict';

    if (!wp || !wp.blocks || !wp.element) {
        return;
    }

    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;
    var ServerSideRender = wp.serverSideRender;
    var InspectorControls = wp.blockEditor && wp.blockEditor.InspectorControls;
    var TextControl = wp.components && wp.components.TextControl;
    var PanelBody = wp.components && wp.components.PanelBody;
    var i18n = window.aiseoBreadcrumbsBlock && aiseoBreadcrumbsBlock.i18n ? aiseoBreadcrumbsBlock.i18n : {};

    function text(key, fallback) {
        return i18n[key] || fallback;
    }

    registerBlockType('aiseo/breadcrumbs', {
        apiVersion: 2,
        title: text('title', 'AISEO Breadcrumbs'),
        description: text('description', 'Breadcrumb trail for the current page.'),
        icon: 'arrow-right-alt',
        category: 'widgets',
        attributes: {
            separator: { type: 'string', default: '' },
            homeText: { type: 'string', default: '' }
        },
        supports: {
            html: false
        },
        edit: function (props) {
            var attributes = props.attributes;
            var inspector = InspectorControls && PanelBody && TextControl
                ? el(InspectorControls, {},
                    el(PanelBody, { title: text('settings', 'Breadcrumb settings'), initialOpen: true },
                        el(TextControl, {
                            label: text('separator', 'Separator'),
                            value: attributes.separator,
                            onChange: function (value) {
                                props.setAttributes({ separator: value });
                            }
                        }),
                        el(TextControl, {
                            label: text('home', 'Home label'),
                            value: attributes.homeText,
                            onChange: function (value) {
                                props.setAttributes({ homeText: value });
                            }
                        })
                    )
                )
                : null;
            var preview = ServerSideRender
                ? el(ServerSideRender, {
                    block: 'aiseo/breadcrumbs',
                    attributes: attributes
                })
                : el('p', {}, text('placeholder', 'Breadcrumb trail'));
            return el('div', { className: 'aiseo-breadcrumbs-editor' }, inspector, preview);
        },
        save: function () {
            return null;
        }
    });
})(window.wp);
