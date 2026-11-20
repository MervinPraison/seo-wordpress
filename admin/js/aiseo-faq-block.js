/**
 * FAQ Gutenberg block.
 *
 * @package AISEO
 */
(function (wp) {
    'use strict';

    if (!wp || !wp.blocks || !wp.element || !wp.components) {
        return;
    }

    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var Button = wp.components.Button;
    var i18n = window.aiseoFaqBlock && aiseoFaqBlock.i18n ? aiseoFaqBlock.i18n : {};

    function text(key, fallback) {
        return i18n[key] || fallback;
    }

    registerBlockType('aiseo/faq', {
        apiVersion: 2,
        title: text('title', 'AISEO FAQ'),
        description: text('description', 'Questions and answers for FAQ schema.'),
        icon: 'editor-help',
        category: 'widgets',
        attributes: {
            questions: { type: 'array', default: [] }
        },
        supports: { html: false },
        edit: function (props) {
            var questions = Array.isArray(props.attributes.questions) ? props.attributes.questions.slice() : [];

            function updateItem(index, key, value) {
                var next = questions.slice();
                var row = Object.assign({ question: '', answer: '' }, next[index] || {});
                row[key] = value;
                next[index] = row;
                props.setAttributes({ questions: next });
            }

            function addItem() {
                props.setAttributes({ questions: questions.concat([{ question: '', answer: '' }]) });
            }

            function removeItem(index) {
                props.setAttributes({ questions: questions.filter(function (item, i) { return i !== index; }) });
            }

            var rows = questions.map(function (item, index) {
                return el('div', { key: 'faq-' + index, className: 'aiseo-faq-editor-item' },
                    el(TextControl, {
                        label: text('question', 'Question'),
                        value: item.question || '',
                        onChange: function (value) {
                            updateItem(index, 'question', value);
                        }
                    }),
                    el(TextareaControl, {
                        label: text('answer', 'Answer'),
                        value: item.answer || '',
                        onChange: function (value) {
                            updateItem(index, 'answer', value);
                        }
                    }),
                    el(Button, {
                        isDestructive: true,
                        variant: 'link',
                        onClick: function () {
                            removeItem(index);
                        }
                    }, text('remove', 'Remove'))
                );
            });

            return el('div', { className: 'aiseo-faq-editor' },
                rows.length ? rows : el('p', {}, text('empty', 'Add a question to show an FAQ on this page.')),
                el(Button, { variant: 'secondary', onClick: addItem }, text('add', 'Add question'))
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp);
