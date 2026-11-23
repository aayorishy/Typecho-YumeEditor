<?php

namespace TypechoPlugin\YumeEditor;

use Typecho\Plugin\Exception;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 前台数学公式支持（MathJax）
 *
 * 负责注入 MathJax 配置与公式源码复制桥接脚本。
 *
 * @package YumeEditor
 * @author aayorishy
 * @version 1.0.0
 */
class MathJax
{

    /**
     * 输出 MathJax 初始化脚本，并为公式追加源码复制支持
     *
     * @param string $refreshScript
     * @return void
     * @throws Exception
     */
    public static function render(string $refreshScript): void
    {
        ?>
        <style>
            mjx-container[data-yumeeditor-tex-source] {
                cursor: text;
                position: relative;
            }

            mjx-container[data-yumeeditor-tex-source][data-yumeeditor-math-display="true"] {
                display: block;
                width: 100%;
                margin: 1em auto;
                text-align: center;
                overflow-x: auto;
                overflow-y: hidden;
            }

            mjx-container[data-yumeeditor-tex-source] .yumeeditor-math-source {
                position: absolute;
                inset: 0;
                display: block;
                padding: 0;
                overflow: hidden;
                clip: auto;
                white-space: pre-wrap;
                border: 0;
                pointer-events: none;
                user-select: text;
                color: transparent;
                background: transparent;
                font: 13px/1.6 Consolas, Monaco, monospace;
                text-align: left;
                z-index: 2;
                -webkit-text-fill-color: transparent;
            }

            mjx-container[data-yumeeditor-tex-source][data-yumeeditor-math-display="true"] .yumeeditor-math-source {
                text-align: center;
            }

            mjx-container[data-yumeeditor-tex-source] .yumeeditor-math-source::selection {
                background: rgba(59, 130, 246, 0.35);
                color: transparent;
                -webkit-text-fill-color: transparent;
            }
        </style>
        <script>
            (function () {
                if (window.YumeEditorMathSource) {
                    return;
                }

                const MATH_SELECTOR = 'mjx-container[data-yumeeditor-tex-source]';

                function wrapMathSource(mathItem) {
                    const source = typeof mathItem?.math === 'string'
                        ? mathItem.math.trim()
                        : '';
                    if (!source) {
                        return '';
                    }
                    return mathItem.display
                        ? '$$\n' + source + '\n$$'
                        : '$' + source + '$';
                }

                function selectMathSource(container) {
                    const sourceElement = container.querySelector('.yumeeditor-math-source');
                    if (!sourceElement) {
                        return;
                    }
                    const selection = window.getSelection();
                    if (!selection) {
                        return;
                    }
                    const range = document.createRange();
                    range.selectNodeContents(sourceElement);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }

                function getSelectedMathContainers(selection) {
                    if (!selection || !selection.rangeCount) {
                        return [];
                    }
                    const containers = new Set();
                    const addClosestContainer = (node) => {
                        const element = node instanceof Element
                            ? node
                            : node?.parentElement;
                        const container = element?.closest?.(MATH_SELECTOR);
                        if (container) {
                            containers.add(container);
                        }
                    };

                    addClosestContainer(selection.anchorNode);
                    addClosestContainer(selection.focusNode);

                    document.querySelectorAll(MATH_SELECTOR).forEach((container) => {
                        try {
                            if (selection.containsNode(container, true)) {
                                containers.add(container);
                            }
                        } catch (e) {
                        }
                    });

                    return Array.from(containers);
                }

                function bindCopyBridge() {
                    if (document.documentElement.hasAttribute('data-yumeeditor-math-copy-bound')) {
                        return;
                    }
                    document.documentElement.setAttribute('data-yumeeditor-math-copy-bound', 'true');

                    document.addEventListener('dblclick', (event) => {
                        const container = event.target instanceof Element
                            ? event.target.closest(MATH_SELECTOR)
                            : null;
                        if (!container) {
                            return;
                        }
                        selectMathSource(container);
                    });

                    document.addEventListener('copy', (event) => {
                        const selection = window.getSelection();
                        const containers = getSelectedMathContainers(selection);
                        if (!containers.length) {
                            return;
                        }

                        const wrappedSource = containers
                            .map((container) => container.dataset.yumeeditorTexWrappedSource || '')
                            .filter(Boolean);

                        if (!wrappedSource.length) {
                            return;
                        }

                        const selectedText = selection ? selection.toString().trim() : '';
                        const plainSource = containers
                            .map((container) => container.dataset.yumeeditorTexSource || '')
                            .filter(Boolean)
                            .join('\n\n');
                        const wrappedText = wrappedSource.join('\n\n');
                        const shouldOverrideCopy = !selectedText
                            || selectedText === plainSource
                            || selectedText === wrappedText
                            || selection?.anchorNode?.parentElement?.classList?.contains('yumeeditor-math-source');

                        if (!shouldOverrideCopy) {
                            return;
                        }

                        event.preventDefault();
                        event.clipboardData.setData('text/plain', wrappedText);
                    }, true);
                }

                function enhance(root) {
                    const mathDocument = window.MathJax?.startup?.document;
                    if (!mathDocument || typeof mathDocument.getMathItemsWithin !== 'function') {
                        return;
                    }

                    const scope = root instanceof Element ? root : document;
                    mathDocument.getMathItemsWithin(scope).forEach((mathItem) => {
                        const container = mathItem?.typesetRoot;
                        const wrappedSource = wrapMathSource(mathItem);
                        if (!container || !wrappedSource) {
                            return;
                        }

                        container.dataset.yumeeditorTexSource = (mathItem.math || '').trim();
                        container.dataset.yumeeditorTexWrappedSource = wrappedSource;
                        container.dataset.yumeeditorMathDisplay = mathItem.display ? 'true' : 'false';
                        container.setAttribute('title', '双击即可选中公式源码');

                        let sourceElement = null;
                        for (const child of container.children) {
                            if (child.classList?.contains('yumeeditor-math-source')) {
                                sourceElement = child;
                            }
                        }

                        if (!sourceElement) {
                            sourceElement = document.createElement('span');
                            sourceElement.className = 'yumeeditor-math-source';
                            sourceElement.setAttribute('aria-hidden', 'true');
                            container.appendChild(sourceElement);
                        }

                        sourceElement.textContent = wrappedSource;
                    });

                    bindCopyBridge();
                }

                window.YumeEditorMathSource = {
                    enhance,
                    async typeset(root) {
                        const scope = root instanceof Element ? root : document.body;
                        if (!window.MathJax || typeof MathJax.typesetPromise !== 'function') {
                            return;
                        }
                        await MathJax.typesetPromise([scope]);
                        enhance(scope);
                    },
                };
            })();
        </script>
        <script>
            MathJax = {
                tex: {
                    inlineMath: [['$', '$']],
                    displayMath: [['$$', '$$']],
                    processEscapes: true,
                    processEnvironments: true,
                },
                options: {
                    skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre'],
                },
                startup: {
                    pageReady() {
                        return MathJax.startup.defaultPageReady().then(() => {
                            try {
                                window.YumeEditorMathSource?.enhance(document);
                            } catch (e) {
                            }
                        });
                    },
                },
            };
        </script>
        <script id="MathJax-script" async src="<?php echo Util::mathJaxScriptUrl(); ?>"></script>
        <script>
            <?php echo $refreshScript; ?>
        </script>
        <?php
    }
}
