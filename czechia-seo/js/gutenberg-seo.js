(function(wp) {
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginSidebar = wp.editPost.PluginSidebar;
    var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var CheckboxControl = wp.components.CheckboxControl;
    var PanelBody = wp.components.PanelBody;
    var Button = wp.components.Button;
    var Icon = wp.components.Icon;

    var cfg = window.czechiaSeoData || {};
    var separator = cfg.separator || '–';
    var titleFormat = cfg.titleFormat || 'post_first';
    var blogname = cfg.blogname || '';
    var ajaxUrl = cfg.ajaxUrl || '';
    var aiNonce = cfg.aiNonce || '';
    var settingsUrl = cfg.settingsUrl || '';

    /* Custom SVG icon – "SEO" text */
    function SeoIcon(props) {
        var color = props.color || 'currentColor';
        return el('svg', { width: 24, height: 24, viewBox: '0 0 24 24', xmlns: 'http://www.w3.org/2000/svg' },
            el('text', {
                x: '12', y: '15',
                textAnchor: 'middle',
                fontSize: '9',
                fontWeight: '700',
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                fill: color,
                letterSpacing: '0.5'
            }, 'SEO'),
            /* Status bar at bottom */
            el('rect', { x: '3', y: '20', width: '18', height: '2', rx: '1', fill: color })
        );
    }

    function buildFullTitle(titlePart) {
        if (titleFormat === 'blog_first') {
            return blogname + ' ' + separator + ' ' + titlePart;
        }
        return titlePart + ' ' + separator + ' ' + blogname;
    }

    function getCounterClass(len, min, max, warnMax) {
        if (len >= min && len <= max) return 'good';
        if (len < min || (len > max && len <= warnMax)) return 'warn';
        return 'bad';
    }

    function getCounterText(len, min, max, warnMax) {
        if (len >= min && len <= max) return len + ' znaků – ideální délka';
        if (len < min) return len + ' znaků – příliš krátké (min. ' + min + ')';
        if (len > max && len <= warnMax) return len + ' znaků – mírně dlouhé (max. doporučeno ' + max + ')';
        return len + ' znaků – příliš dlouhé!';
    }

    function CounterBar(props) {
        var len = props.length || 0;
        var min = props.min || 0;
        var max = props.max || 60;
        var warnMax = props.warnMax || max + 10;
        var pct = Math.min(len / warnMax * 100, 100);
        var cls = getCounterClass(len, min, max, warnMax);
        var text = getCounterText(len, min, max, warnMax);

        var colors = { good: '#00a32a', warn: '#dba617', bad: '#d63638', '': '#ccc' };

        return el('div', { style: { marginTop: '4px', marginBottom: '12px' } },
            el('div', { style: { height: '4px', background: '#e0e0e0', borderRadius: '2px', overflow: 'hidden' } },
                el('div', { style: { width: pct + '%', height: '100%', background: colors[cls] || '#ccc', transition: 'width 0.2s, background 0.2s' } })
            ),
            el('span', { style: { fontSize: '12px', color: colors[cls] || '#666', marginTop: '2px', display: 'block' } }, text)
        );
    }


    function SerpPreview(props) {
        var title = props.title || '';
        var desc = props.description || '';
        var url = props.url || '';

        return el('div', { style: { marginTop: '12px', padding: '12px', background: '#fff', border: '1px solid #e0e0e0', borderRadius: '8px' } },
            el('div', { style: { fontSize: '11px', color: '#666', marginBottom: '8px', fontWeight: '600' } }, 'Náhled ve vyhledávači:'),
            el('div', { style: { fontSize: '18px', color: '#1a0dab', lineHeight: '1.3', marginBottom: '2px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }, title),
            el('div', { style: { fontSize: '13px', color: '#006621', marginBottom: '2px' } }, url),
            el('div', { style: { fontSize: '13px', color: '#545454', lineHeight: '1.4', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' } }, desc)
        );
    }

    /* Title input with readonly affix (prefix or suffix) */
    function TitleInputWithAffix(props) {
        var seoTitle = props.seoTitle;
        var postTitle = props.postTitle;
        var onChange = props.onChange;

        var affixText;
        if (titleFormat === 'blog_first') {
            affixText = blogname + ' ' + separator + ' ';
        } else {
            affixText = ' ' + separator + ' ' + blogname;
        }

        var affixEl = el('span', {
            style: {
                display: 'inline-flex',
                alignItems: 'center',
                padding: '0 8px',
                background: '#f0f0f0',
                border: '1px solid #8c8f94',
                color: '#666',
                fontSize: '13px',
                whiteSpace: 'nowrap',
                lineHeight: '30px',
                height: '30px',
                boxSizing: 'border-box',
                borderRadius: titleFormat === 'blog_first' ? '4px 0 0 4px' : '0 4px 4px 0',
                borderRight: titleFormat === 'blog_first' ? 'none' : undefined,
                borderLeft: titleFormat !== 'blog_first' ? 'none' : undefined
            }
        }, affixText);

        var inputEl = el('input', {
            type: 'text',
            value: seoTitle,
            placeholder: postTitle || 'Název příspěvku',
            onChange: function(e) { onChange(e.target.value); },
            style: {
                flex: '1',
                minWidth: '0',
                height: '30px',
                fontSize: '13px',
                padding: '0 8px',
                border: '1px solid #8c8f94',
                borderRadius: titleFormat === 'blog_first' ? '0 4px 4px 0' : '4px 0 0 4px',
                outline: 'none',
                boxSizing: 'border-box'
            }
        });

        var children = titleFormat === 'blog_first'
            ? [affixEl, inputEl]
            : [inputEl, affixEl];

        return el('div', {
            style: {
                display: 'flex',
                alignItems: 'center',
                marginBottom: '4px'
            }
        }, children);
    }

    function CzechiaSeoSidebar() {
        var postTitle = useSelect(function(select) {
            return select('core/editor').getEditedPostAttribute('title') || '';
        });
        var postExcerpt = useSelect(function(select) {
            return select('core/editor').getEditedPostAttribute('excerpt') || '';
        });
        var permalink = useSelect(function(select) {
            return select('core/editor').getPermalink() || '';
        });
        var meta = useSelect(function(select) {
            return select('core/editor').getEditedPostAttribute('meta') || {};
        });
        var editPost = useDispatch('core/editor').editPost;

        var seoTitle = meta._czechia_seo_title || '';
        var seoDesc = meta._czechia_seo_description || '';
        var noindex = meta._czechia_seo_noindex === '1';
        var nofollow = meta._czechia_seo_nofollow === '1';

        var titlePart = seoTitle || postTitle || 'Název příspěvku';
        var fullTitle = buildFullTitle(titlePart);

        var autoDesc = postExcerpt || 'Popis příspěvku…';
        var displayDesc = seoDesc || autoDesc;

        /* Full title length for counter (always counts effective title + affix) */
        var fullTitleLen = buildFullTitle(seoTitle || postTitle || 'Název příspěvku').length;

        /* AI generation state */
        var aiLoadingState = useState(false);
        var aiLoading = aiLoadingState[0];
        var setAiLoading = aiLoadingState[1];

        var aiErrorState = useState('');
        var aiError = aiErrorState[0];
        var setAiError = aiErrorState[1];

        var aiHighlightState = useState('');
        var aiHighlight = aiHighlightState[0];
        var setAiHighlight = aiHighlightState[1];

        /* Clear highlight after 1s */
        useEffect(function() {
            if (!aiHighlight) return;
            var t = setTimeout(function() { setAiHighlight(''); }, 1000);
            return function() { clearTimeout(t); };
        }, [aiHighlight]);

        function setMeta(key, value) {
            var newMeta = {};
            newMeta[key] = value;
            editPost({ meta: newMeta });
        }

        function stripHtml(html) {
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            return (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
        }

        function handleAiGenerate() {
            var text = '';

            /* Grab current blocks from the block editor store */
            var blocks = wp.data.select('core/block-editor').getBlocks();

            if (blocks && blocks.length) {
                var html = wp.blocks.serialize(blocks);
                text = stripHtml(html);
            }

            /* Fallback: read current post content attribute */
            if (!text) {
                var raw = wp.data.select('core/editor').getEditedPostAttribute('content') || '';
                text = stripHtml(raw);
            }

            text = (text || '').replace(/\s+/g, ' ').trim();

            if (!text) {
                setAiError('Článek je prázdný – není co odeslat.');
                return;
            }

            setAiLoading(true);
            setAiError('');

            var formData = new FormData();
            formData.append('action', 'czechia_seo_ai_generate');
            formData.append('nonce', aiNonce);
            formData.append('content', text);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    if (resp.success && resp.data) {
                        var newMeta = {};
                        newMeta._czechia_seo_title = resp.data.title;
                        newMeta._czechia_seo_description = resp.data.description;
                        editPost({ meta: newMeta });
                        setAiHighlight('done');
                    } else {
                        var msg = (resp.data && typeof resp.data === 'string') ? resp.data : 'Nastala chyba. Zkuste to znovu.';
                        setAiError(msg);
                    }
                })
                .catch(function() {
                    setAiError('Nastala chyba při komunikaci. Zkuste to znovu.');
                })
                .then(function() {
                    setAiLoading(false);
                });
        }

        /* Highlight style for inputs after AI fill */
        var highlightStyle = aiHighlight ? {
            transition: 'background-color 1s ease',
            backgroundColor: '#fff'
        } : {};
        var highlightStyleActive = aiHighlight === 'done' ? {
            transition: 'background-color 0.3s ease',
            backgroundColor: '#e8f5e9'
        } : highlightStyle;

        /* SEO status: determine overall color for sidebar icon */
        var titleOk = fullTitleLen >= 30 && fullTitleLen <= 60;
        var descLen = seoDesc.length || 0;
        var descOk = descLen >= 120 && descLen <= 160;
        var seoStatus; /* 'good' | 'warn' | 'bad' */
        if (titleOk && descOk) {
            seoStatus = 'good';
        } else if (seoTitle && seoDesc) {
            seoStatus = 'warn';
        } else {
            seoStatus = 'bad';
        }
        var statusColors = { good: '#00a32a', warn: '#dba617', bad: '#d63638' };
        var seoIconEl = el(SeoIcon, { color: statusColors[seoStatus] });

        return el(Fragment, {},
            el(PluginSidebarMoreMenuItem, { target: 'czechia-seo-sidebar', icon: seoIconEl }, 'CZECHIA SEO'),
            el(PluginSidebar, {
                name: 'czechia-seo-sidebar',
                title: 'CZECHIA – SEO',
                icon: seoIconEl
            },
                el('div', { style: { padding: '12px 16px 0' } },
                    el('p', { style: { fontSize: '12px', color: '#666', lineHeight: '1.5', margin: '0' } },
                        'Správně nastavené SEO meta tagy jsou klíčové pro čitelnost vašeho webu v Googlu či Seznamu. Titulek říká, o čem stránka je, a popisek motivuje k prokliku. Vygenerujte si je pomocí ZONER AI: hlídá technické limity délky: texty generuje tak, aby se vešly do vymezeného prostoru a ve vyhledávačích se zobrazovaly celé a srozumitelné.'
                    )
                ),
                el(PanelBody, { title: 'SEO titulek', initialOpen: true },
                    el('div', { style: highlightStyleActive, className: 'czechia-seo-gutenberg-title-wrap' },
                        el(TitleInputWithAffix, {
                            seoTitle: seoTitle,
                            postTitle: postTitle,
                            onChange: function(val) { setMeta('_czechia_seo_title', val); }
                        })
                    ),
                    el(CounterBar, { length: fullTitleLen, min: 30, max: 60, warnMax: 70 })
                ),
                el(PanelBody, { title: 'SEO popis', initialOpen: true },
                    el('div', { style: highlightStyleActive, className: 'czechia-seo-gutenberg-desc-wrap' },
                        el(TextareaControl, {
                            value: seoDesc,
                            placeholder: autoDesc,
                            rows: 3,
                            onChange: function(val) { setMeta('_czechia_seo_description', val); }
                        })
                    ),
                    el(CounterBar, { length: displayDesc.length, min: 120, max: 160, warnMax: 200 })
                ),
                el('div', { style: { padding: '0 16px 12px', display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' } },
                    el(Button, {
                        variant: 'secondary',
                        icon: aiLoading ? undefined : 'format-status',
                        isBusy: aiLoading,
                        disabled: aiLoading,
                        onClick: handleAiGenerate,
                        style: { fontSize: '12px' }
                    }, aiLoading ? 'Generuji…' : 'Vygenerovat přes AI'),
                    aiError ? el('span', { style: { color: '#d63638', fontSize: '12px' } }, aiError) : null
                ),
                el(PanelBody, { title: 'Robots', initialOpen: false },
                    el(CheckboxControl, {
                        label: 'Noindex (skrýt z vyhledávačů)',
                        checked: noindex,
                        onChange: function(val) { setMeta('_czechia_seo_noindex', val ? '1' : ''); }
                    }),
                    el(CheckboxControl, {
                        label: 'Nofollow (nesledovat odkazy)',
                        checked: nofollow,
                        onChange: function(val) { setMeta('_czechia_seo_nofollow', val ? '1' : ''); }
                    })
                ),
                el(PanelBody, { title: 'Náhled', initialOpen: true },
                    el(SerpPreview, {
                        title: fullTitle,
                        description: displayDesc,
                        url: permalink
                    })
                ),
                settingsUrl ? el('div', { style: { padding: '8px 16px 16px' } },
                    el(Button, {
                        variant: 'link',
                        href: settingsUrl,
                        icon: 'admin-generic',
                        style: { fontSize: '12px', textDecoration: 'none' }
                    }, 'Nastavení SEO')
                ) : null
            )
        );
    }

    registerPlugin('czechia-seo', {
        render: CzechiaSeoSidebar,
        icon: el(SeoIcon, { color: '#666' })
    });

})(window.wp);
