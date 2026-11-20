/**
 * Block editor SEO panel for title, description, keyword and noindex.
 *
 * @package AISEO
 */
(function (wp) {
    'use strict';

    if (!wp || !wp.plugins || !wp.element || !wp.components || !wp.data || !wp.i18n) {
        return;
    }

    var registerPlugin = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = (wp.editor && wp.editor.PluginDocumentSettingPanel) ||
        (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
    var el = wp.element.createElement;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var CheckboxControl = wp.components.CheckboxControl;
    var SelectControl = wp.components.SelectControl;
    var Button = wp.components.Button;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var Fragment = wp.element.Fragment;
    var __ = wp.i18n.__;
    var apiFetch = wp.apiFetch;
    var MediaUpload = (wp.blockEditor && wp.blockEditor.MediaUpload) || (wp.editor && wp.editor.MediaUpload);

    function flagOn(value) {
        return value === true || value === 1 || value === '1';
    }

    if (!PluginDocumentSettingPanel) {
        return;
    }

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

    function fleschEase(html) {
        var text = htmlToPlainText(html);
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
        if (score < 0) {
            score = 0;
        }
        if (score > 100) {
            score = 100;
        }
        return Math.round(score * 10) / 10;
    }

    function SEOFieldsPanel() {
        var editorState = useSelect(function (select) {
            var editor = select('core/editor');
            return {
                meta: editor.getEditedPostAttribute('meta') || {},
                content: editor.getEditedPostContent() || '',
                postId: editor.getCurrentPostId(),
                postTitle: editor.getEditedPostAttribute('title') || '',
                permalink: (editor.getPermalink && editor.getPermalink()) || ''
            };
        }, []);
        var meta = editorState.meta;
        var flesch = fleschEase(editorState.content);
        var editPost = useDispatch('core/editor').editPost;
        var related = useState([]);
        var relatedList = related[0];
        var setRelated = related[1];
        useEffect(function () {
            if (!editorState.postId || !apiFetch) {
                return;
            }
            apiFetch({ path: '/aiseo/v1/internal-linking/suggestions/' + editorState.postId + '?limit=3' }).then(function (response) {
                var items = response && response.data && response.data.suggestions ? response.data.suggestions : [];
                setRelated(items.slice(0, 3));
            }).catch(function () {
                setRelated([]);
            });
        }, [editorState.postId]);

        function updateMeta(key, value) {
            var next = {};
            next[key] = value;
            editPost({ meta: Object.assign({}, meta, next) });
        }

        var title = typeof meta._aiseo_meta_title === 'string' ? meta._aiseo_meta_title : '';
        var snippetTitle = title || editorState.postTitle || '';
        var description = typeof meta._aiseo_meta_description === 'string' ? meta._aiseo_meta_description : '';
        var keyword = typeof meta._aiseo_focus_keyword === 'string' ? meta._aiseo_focus_keyword : '';
        var extra = typeof meta._aiseo_additional_keywords === 'string' ? meta._aiseo_additional_keywords : '';
        var noindex = flagOn(meta._aiseo_noindex);
        var excludeSitemap = flagOn(meta._aiseo_exclude_sitemap);
        var ogTitle = typeof meta._aiseo_og_title === 'string' ? meta._aiseo_og_title : '';
        var ogDescription = typeof meta._aiseo_og_description === 'string' ? meta._aiseo_og_description : '';
        var ogImage = parseInt(meta._aiseo_og_image, 10) || 0;
        var twitterCard = typeof meta._aiseo_twitter_card_type === 'string' ? meta._aiseo_twitter_card_type : '';
        var schemaType = typeof meta._aiseo_schema_type === 'string' && meta._aiseo_schema_type !== '' ? meta._aiseo_schema_type : 'auto';
        var eventStart = typeof meta._aiseo_event_start === 'string' ? meta._aiseo_event_start : '';
        var eventLocation = typeof meta._aiseo_event_location === 'string' ? meta._aiseo_event_location : '';
        var jobLocation = typeof meta._aiseo_job_location === 'string' ? meta._aiseo_job_location : '';
        var jobType = typeof meta._aiseo_job_type === 'string' && meta._aiseo_job_type !== '' ? meta._aiseo_job_type : 'FULL_TIME';
        var jobThrough = typeof meta._aiseo_job_through === 'string' ? meta._aiseo_job_through : '';
        var canonical = typeof meta._aiseo_canonical_url === 'string' ? meta._aiseo_canonical_url : '';
        var breadcrumbTitle = typeof meta._aiseo_breadcrumb_title === 'string' ? meta._aiseo_breadcrumb_title : '';
        var redirectTo = typeof meta._aiseo_redirect_to === 'string' ? meta._aiseo_redirect_to : '';
        var redirectType = typeof meta._aiseo_redirect_type === 'string' ? meta._aiseo_redirect_type : '';
        var imageUrl = useSelect(function (select) {
            if (!ogImage || !select('core') || !select('core').getMedia) {
                return '';
            }
            var media = select('core').getMedia(ogImage);
            return media && media.source_url ? media.source_url : '';
        }, [ogImage]);

        var snippetUrl = canonical || editorState.permalink || '';
        snippetUrl = String(snippetUrl).replace(/^https?:\/\//, '').split('?')[0];
        var plain = htmlToPlainText(editorState.content);
        var haystack = (snippetTitle + ' ' + plain).toLowerCase();
        var extraPhrases = extra.split(/[\n,]+/).map(function (item) {
            return item.trim();
        }).filter(Boolean).slice(0, 4);
        var checks = [];
        var titleLen = snippetTitle.length;
        checks.push({
            status: (titleLen >= 50 && titleLen <= 60) ? 'good' : 'warning',
            message: __('SEO title length is', 'aiseo') + ' ' + titleLen + ' ' + __('characters. Aim for 50 to 60.', 'aiseo')
        });
        var descLen = description.length;
        checks.push({
            status: (descLen >= 120 && descLen <= 160) ? 'good' : 'warning',
            message: __('Meta description length is', 'aiseo') + ' ' + descLen + ' ' + __('characters. Aim for 120 to 160.', 'aiseo')
        });
        if (keyword) {
            var hasKeyword = haystack.indexOf(keyword.toLowerCase()) !== -1;
            checks.push({
                status: hasKeyword ? 'good' : 'poor',
                message: hasKeyword
                    ? __('The focus keyword appears in the title or content.', 'aiseo')
                    : __('The focus keyword does not appear in the title or content.', 'aiseo')
            });
        }
        extraPhrases.forEach(function (phrase) {
            if (keyword && phrase.toLowerCase() === keyword.toLowerCase()) {
                return;
            }
            var found = haystack.indexOf(phrase.toLowerCase()) !== -1;
            checks.push({
                status: found ? 'good' : 'warning',
                message: found
                    ? __('Extra keyword appears in the content:', 'aiseo') + ' ' + phrase
                    : __('Extra keyword is missing from the content:', 'aiseo') + ' ' + phrase
            });
        });
        if (flesch !== null) {
            checks.push({
                status: flesch >= 50 ? 'good' : 'warning',
                message: __('Flesch Reading Ease:', 'aiseo') + ' ' + flesch + '. ' + __('60–80 is comfortable to read. This is not a search ranking.', 'aiseo')
            });
        }
        if (noindex) {
            checks.push({
                status: 'warning',
                message: __('This post is set to noindex.', 'aiseo')
            });
        }

        function checkItem(item, index) {
            return el('li', { key: 'check-' + index, className: 'aiseo-check-' + item.status }, item.message);
        }

        return el(
            Fragment,
            null,
            el(
                PluginDocumentSettingPanel,
                {
                    name: 'aiseo-seo-fields',
                    title: __('AISEO', 'aiseo'),
                    icon: 'search'
                },
                el(TextControl, {
                    label: __('SEO title', 'aiseo'),
                    value: title,
                    onChange: function (value) {
                        updateMeta('_aiseo_meta_title', value);
                    },
                    help: title.length + ' / 60'
                }),
                el(TextareaControl, {
                    label: __('Meta description', 'aiseo'),
                    value: description,
                    onChange: function (value) {
                        updateMeta('_aiseo_meta_description', value);
                    },
                    help: description.length + ' / 160'
                }),
                el(TextControl, {
                    label: __('Focus keyword', 'aiseo'),
                    value: keyword,
                    onChange: function (value) {
                        updateMeta('_aiseo_focus_keyword', value);
                    }
                }),
                el(TextareaControl, {
                    label: __('Extra keywords', 'aiseo'),
                    value: extra,
                    onChange: function (value) {
                        updateMeta('_aiseo_additional_keywords', value);
                    },
                    help: __('Up to four more phrases, separated by commas.', 'aiseo')
                }),
                el('div', { className: 'aiseo-preview-content aiseo-gutenberg-snippet' },
                    el('div', { className: 'aiseo-snippet-title' }, snippetTitle || __('Untitled', 'aiseo')),
                    el('div', { className: 'aiseo-snippet-url' }, snippetUrl),
                    el('div', { className: 'aiseo-snippet-description' }, description)
                ),
                el('ul', { className: 'aiseo-gutenberg-checks' }, checks.map(checkItem)),
                el(CheckboxControl, {
                    label: __('Noindex this post', 'aiseo'),
                    checked: noindex,
                    onChange: function (checked) {
                        updateMeta('_aiseo_noindex', checked ? '1' : '0');
                    }
                }),
                el(CheckboxControl, {
                    label: __('Exclude from the XML sitemap', 'aiseo'),
                    checked: excludeSitemap,
                    onChange: function (checked) {
                        updateMeta('_aiseo_exclude_sitemap', checked ? '1' : '0');
                    }
                }),
                relatedList.length ? el('div', { className: 'aiseo-related-links' },
                    el('p', { className: 'aiseo-muted' }, __('Related posts to link', 'aiseo')),
                    relatedList.map(function (item) {
                        return el('p', { key: String(item.post_id) },
                            el('a', { href: item.post_url, target: '_blank', rel: 'noopener noreferrer' }, item.post_title || '')
                        );
                    })
                ) : null
            ),
            el(
                PluginDocumentSettingPanel,
                {
                    name: 'aiseo-social',
                    title: __('AISEO Social', 'aiseo'),
                    icon: 'share'
                },
                el(TextControl, {
                    label: __('Social title', 'aiseo'),
                    value: ogTitle,
                    onChange: function (value) {
                        updateMeta('_aiseo_og_title', value);
                    },
                    help: __('Leave blank to use the SEO title.', 'aiseo')
                }),
                el(TextareaControl, {
                    label: __('Social description', 'aiseo'),
                    value: ogDescription,
                    onChange: function (value) {
                        updateMeta('_aiseo_og_description', value);
                    },
                    help: __('Leave blank to use the meta description.', 'aiseo')
                }),
                el(SelectControl, {
                    label: __('X card type', 'aiseo'),
                    value: twitterCard,
                    options: [
                        { label: __('Automatic', 'aiseo'), value: '' },
                        { label: __('Summary', 'aiseo'), value: 'summary' },
                        { label: __('Summary with large image', 'aiseo'), value: 'summary_large_image' }
                    ],
                    onChange: function (value) {
                        updateMeta('_aiseo_twitter_card_type', value);
                    }
                }),
                imageUrl ? el('img', { src: imageUrl, alt: '', className: 'aiseo-og-preview-img' }) : null,
                MediaUpload ? el(MediaUpload, {
                    onSelect: function (media) {
                        updateMeta('_aiseo_og_image', media && media.id ? media.id : 0);
                    },
                    allowedTypes: ['image'],
                    value: ogImage,
                    render: function (obj) {
                        return el(Button, { isSecondary: true, onClick: obj.open }, ogImage ? __('Change image', 'aiseo') : __('Select social image', 'aiseo'));
                    }
                }) : null,
                ogImage ? el(Button, {
                    isLink: true,
                    isDestructive: true,
                    onClick: function () {
                        updateMeta('_aiseo_og_image', 0);
                    }
                }, __('Remove image', 'aiseo')) : null
            ),
            el(
                PluginDocumentSettingPanel,
                {
                    name: 'aiseo-schema',
                    title: __('AISEO Schema', 'aiseo'),
                    icon: 'media-code'
                },
                el(SelectControl, {
                    label: __('Schema type', 'aiseo'),
                    value: schemaType,
                    options: [
                        { label: __('Automatic', 'aiseo'), value: 'auto' },
                        { label: __('Article', 'aiseo'), value: 'article' },
                        { label: __('Blog posting', 'aiseo'), value: 'blogposting' },
                        { label: __('Web page', 'aiseo'), value: 'webpage' },
                        { label: __('News article', 'aiseo'), value: 'newsarticle' },
                        { label: __('Product', 'aiseo'), value: 'product' },
                        { label: __('How-to', 'aiseo'), value: 'howto' },
                        { label: __('Recipe', 'aiseo'), value: 'recipe' },
                        { label: __('Event', 'aiseo'), value: 'event' },
                        { label: __('Course', 'aiseo'), value: 'course' },
                        { label: __('Job posting', 'aiseo'), value: 'jobposting' }
                    ],
                    onChange: function (value) {
                        updateMeta('_aiseo_schema_type', value);
                    },
                    help: __('Product needs WooCommerce. Event and Job posting use the fields below.', 'aiseo')
                }),
                el(TextControl, {
                    label: __('Event start', 'aiseo'),
                    type: 'datetime-local',
                    value: eventStart,
                    onChange: function (value) {
                        updateMeta('_aiseo_event_start', value);
                    }
                }),
                el(TextControl, {
                    label: __('Event location', 'aiseo'),
                    value: eventLocation,
                    onChange: function (value) {
                        updateMeta('_aiseo_event_location', value);
                    }
                }),
                el(TextControl, {
                    label: __('Job location', 'aiseo'),
                    value: jobLocation,
                    onChange: function (value) {
                        updateMeta('_aiseo_job_location', value);
                    }
                }),
                el(SelectControl, {
                    label: __('Employment type', 'aiseo'),
                    value: jobType,
                    options: [
                        { label: __('Full time', 'aiseo'), value: 'FULL_TIME' },
                        { label: __('Part time', 'aiseo'), value: 'PART_TIME' },
                        { label: __('Contractor', 'aiseo'), value: 'CONTRACTOR' },
                        { label: __('Temporary', 'aiseo'), value: 'TEMPORARY' },
                        { label: __('Intern', 'aiseo'), value: 'INTERN' },
                        { label: __('Volunteer', 'aiseo'), value: 'VOLUNTEER' },
                        { label: __('Other', 'aiseo'), value: 'OTHER' }
                    ],
                    onChange: function (value) {
                        updateMeta('_aiseo_job_type', value);
                    }
                }),
                el(TextControl, {
                    label: __('Application closes', 'aiseo'),
                    type: 'datetime-local',
                    value: jobThrough,
                    onChange: function (value) {
                        updateMeta('_aiseo_job_through', value);
                    }
                })
            ),
            el(
                PluginDocumentSettingPanel,
                {
                    name: 'aiseo-advanced',
                    title: __('AISEO Advanced', 'aiseo'),
                    icon: 'admin-generic'
                },
                el(TextControl, {
                    label: __('Canonical URL', 'aiseo'),
                    type: 'url',
                    value: canonical,
                    onChange: function (value) {
                        updateMeta('_aiseo_canonical_url', value);
                    },
                    help: __('Leave empty to use the default permalink.', 'aiseo')
                }),
                el(TextControl, {
                    label: __('Breadcrumb title', 'aiseo'),
                    value: breadcrumbTitle,
                    onChange: function (value) {
                        updateMeta('_aiseo_breadcrumb_title', value);
                    }
                }),
                el(CheckboxControl, {
                    label: __('No follow', 'aiseo'),
                    checked: flagOn(meta._aiseo_nofollow),
                    onChange: function (checked) {
                        updateMeta('_aiseo_nofollow', checked ? '1' : '0');
                    }
                }),
                el(CheckboxControl, {
                    label: __('No archive', 'aiseo'),
                    checked: flagOn(meta._aiseo_noarchive),
                    onChange: function (checked) {
                        updateMeta('_aiseo_noarchive', checked ? '1' : '0');
                    }
                }),
                el(CheckboxControl, {
                    label: __('No snippet', 'aiseo'),
                    checked: flagOn(meta._aiseo_nosnippet),
                    onChange: function (checked) {
                        updateMeta('_aiseo_nosnippet', checked ? '1' : '0');
                    }
                }),
                el(CheckboxControl, {
                    label: __('Cornerstone content', 'aiseo'),
                    checked: flagOn(meta._aiseo_cornerstone),
                    onChange: function (checked) {
                        updateMeta('_aiseo_cornerstone', checked ? '1' : '0');
                    }
                }),
                window.aiseoSidebar && aiseoSidebar.canRedirect ? el(TextControl, {
                    label: __('Redirect this URL', 'aiseo'),
                    type: 'url',
                    value: redirectTo,
                    onChange: function (value) {
                        updateMeta('_aiseo_redirect_to', value);
                    }
                }) : null,
                window.aiseoSidebar && aiseoSidebar.canRedirect ? el(SelectControl, {
                    label: __('Redirect type', 'aiseo'),
                    value: redirectType,
                    options: [
                        { label: __('None', 'aiseo'), value: '' },
                        { label: '301', value: '301' },
                        { label: '302', value: '302' },
                        { label: '307', value: '307' },
                        { label: '308', value: '308' },
                        { label: '410', value: '410' }
                    ],
                    onChange: function (value) {
                        updateMeta('_aiseo_redirect_type', value);
                    }
                }) : null
            )
        );
    }

    registerPlugin('aiseo-seo-fields', {
        render: SEOFieldsPanel,
        icon: 'search'
    });
})(window.wp);
