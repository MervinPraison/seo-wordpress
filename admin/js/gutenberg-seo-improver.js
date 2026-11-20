/**
 * Block editor panel that improves SEO title, description and block text.
 *
 * @package AISEO
 */
(function (wp, $) {
    'use strict';

    if (!wp || !wp.plugins || !wp.element || !wp.components || !wp.data || !wp.i18n) {
        return;
    }

    var registerPlugin = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = (wp.editor && wp.editor.PluginDocumentSettingPanel) ||
        (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    var Notice = wp.components.Notice;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var __ = wp.i18n.__;

    if (!PluginDocumentSettingPanel || typeof aiseoSEOImprover === 'undefined') {
        return;
    }

    function ajaxPost(data) {
        return new Promise(function (resolve, reject) {
            $.ajax({
                url: aiseoSEOImprover.ajaxUrl || window.ajaxurl,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response && response.success) {
                        resolve(response.data);
                    } else {
                        reject((response && response.data) || __('Request failed.', 'aiseo'));
                    }
                },
                error: function (xhr, status, error) {
                    reject(error || status);
                }
            });
        });
    }

    function SEOScoreImprover() {
        var improving = useState(false);
        var isImproving = improving[0];
        var setIsImproving = improving[1];
        var scoreState = useState(null);
        var seoScore = scoreState[0];
        var setSeoScore = scoreState[1];
        var messageState = useState('');
        var message = messageState[0];
        var setMessage = messageState[1];
        var typeState = useState('info');
        var messageType = typeState[0];
        var setMessageType = typeState[1];

        var post = useSelect(function (select) {
            var editor = select('core/editor');
            var blockEditor = select('core/block-editor');
            return {
                postId: editor.getCurrentPostId(),
                postTitle: editor.getEditedPostAttribute('title'),
                blocks: blockEditor.getBlocks()
            };
        }, []);
        var editPost = useDispatch('core/editor').editPost;
        var updateBlockAttributes = useDispatch('core/block-editor').updateBlockAttributes;

        function getAnalysis() {
            return ajaxPost({
                action: 'aiseo_analyze_content',
                nonce: aiseoSEOImprover.nonce,
                post_id: post.postId
            });
        }

        function improveMeta(analysisData) {
            return ajaxPost({
                action: 'aiseo_improve_seo_meta',
                nonce: aiseoSEOImprover.nonce,
                post_id: post.postId,
                current_title: post.postTitle,
                analysis: JSON.stringify(analysisData)
            }).then(function (data) {
                if (data && data.title) {
                    editPost({ title: data.title });
                }
                return data;
            });
        }

        function shouldImproveBlock(block) {
            var allowed = ['core/paragraph', 'core/heading', 'core/list', 'core/quote'];
            return allowed.indexOf(block.name) !== -1 && (block.attributes.content || block.attributes.values);
        }

        function improveBlock(block, analysisData) {
            var html = block.attributes.content || '';
            var text = '';
            if (window.DOMParser) {
                var parsed = new DOMParser().parseFromString(String(html), 'text/html');
                text = ((parsed.body && parsed.body.textContent) || '').trim();
            } else {
                text = String(html).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
            }
            if (!text) {
                return Promise.resolve();
            }
            return ajaxPost({
                action: 'aiseo_improve_content_block',
                nonce: aiseoSEOImprover.nonce,
                content: text,
                block_type: block.name,
                analysis: JSON.stringify(analysisData),
                preserve_structure: true,
                minimal_changes: true
            }).then(function (data) {
                if (data) {
                    updateBlockAttributes(block.clientId, { content: data });
                }
            }).catch(function () {
                return null;
            });
        }

        function improveScore() {
            setIsImproving(true);
            setMessage(__('Analysing content…', 'aiseo'));
            setMessageType('info');
            getAnalysis().then(function (analysisData) {
                var score = (analysisData && analysisData.overall_score) ? analysisData.overall_score : 0;
                setSeoScore(score);
                if (score >= 80) {
                    setMessage(__('SEO score is already 80 or above. No changes made.', 'aiseo'));
                    setMessageType('success');
                    return null;
                }
                setMessage(__('Improving SEO title and description…', 'aiseo'));
                return improveMeta(analysisData).then(function () {
                    var chain = Promise.resolve();
                    post.blocks.forEach(function (block) {
                        if (shouldImproveBlock(block)) {
                            chain = chain.then(function () {
                                return improveBlock(block, analysisData);
                            });
                        }
                    });
                    return chain.then(function () {
                        return getAnalysis();
                    }).then(function (next) {
                        var nextScore = (next && next.overall_score) ? next.overall_score : 0;
                        setSeoScore(nextScore);
                        setMessage(
                            __('SEO score went from ', 'aiseo') + score + '/100 ' + __('to', 'aiseo') + ' ' + nextScore + '/100.'
                        );
                        setMessageType('success');
                    });
                });
            }).catch(function (error) {
                setMessage(String(error));
                setMessageType('error');
            }).then(function () {
                setIsImproving(false);
            });
        }

        var children = [
            seoScore !== null ? el('p', { key: 'score' },
                el('strong', null, __('Current SEO score:', 'aiseo') + ' ' + seoScore + '/100')
            ) : null,
            message ? el(Notice, { key: 'notice', status: messageType, isDismissible: false }, message) : null,
            el(Button, {
                key: 'button',
                isPrimary: true,
                disabled: isImproving,
                onClick: improveScore
            }, isImproving ? el(Fragment, null, el(Spinner, null), ' ', __('Improving…', 'aiseo')) : __('Improve SEO score', 'aiseo')),
            el('p', { key: 'help', className: 'aiseo-muted' },
                __('Uses your API key to adjust the title, description and text blocks. Review the result before publishing.', 'aiseo')
            )
        ];

        return el(PluginDocumentSettingPanel, {
            name: 'aiseo-seo-improver',
            title: __('AISEO score', 'aiseo'),
            icon: 'chart-line'
        }, children);
    }

    registerPlugin('aiseo-seo-improver', {
        render: SEOScoreImprover,
        icon: 'chart-line'
    });
})(window.wp, window.jQuery);
