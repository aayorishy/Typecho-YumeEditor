<?php

namespace TypechoPlugin\YumeEditor;

use Typecho\Common;
use Typecho\Plugin\Exception;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 后台编辑器
 *
 * 负责写作页的资源注入、工具栏与提交栏增强。
 *
 * @package YumeEditor
 * @author aayorishy
 * @version 1.0.0
 */
class Editor
{

    /**
     * 编辑页面附加 CSS JS
     * @return void
     * @throws Exception
     */
    public static function footer()
    {
        $admin_dir = Common::url(trim(defined('__TYPECHO_ADMIN_DIR__') ? __TYPECHO_ADMIN_DIR__ : '/admin/', '/'), __TYPECHO_ROOT_DIR__);
        $css_path = Common::url('css/style.css', $admin_dir);
        if (is_file($css_path)): ?>
            <script>
                (function () {
                    <?php if (Util::pluginOption('YumeInsertAllImages', 'on')): ?>
                    if ($('#ph-insert-images').length == 0)
                        $('#upload-panel').append(`<span id="ph-insert-images" class="ph-btn"><?php _e("插入所有图片") ?></span>`);

                    $('#ph-insert-images').off('click').on('click', function () {
                        let fileList = $('#file-list').children('li'),
                            text = "";
                        fileList.each((num, el) => {
                            let item = $(el);
                            if (item.data('image')) {
                                text += "\n" + "![{name}]({url})".replace('{name}', item.find('.insert').text()).replace('{url}', item.data('url'));
                            }
                        });
                        $('body').trigger('YumeEditorReplaceSelection', [text]);
                    });
                    <?php endif; ?>
                })();

            </script>
        <?php
        endif;
    }


    /**
     * 自定义编辑器
     * @param $content
     * @return void
     * @throws Exception
     */
    public static function assets($content)
    {
        $options = Helper::options();
        Util::collectManifest();
        if (Util::pluginOption('YumeEditorEnabled', 'on') === 'on') {
            ?>
            <link rel="stylesheet" href="<?php echo Util::pluginStatic('css', 'main.css'); ?>"/>
            <link rel="stylesheet" href="<?php echo Util::pluginStatic('css', 'mermaid.css'); ?>"/>
            <script src="<?php echo Util::pluginStatic('js', 'hyperdown.js') ?>"></script>
            <script src="<?php $options->adminStaticUrl('js', 'pagedown.js'); ?>"></script>
            <?php if (!\TypechoPlugin\YumeEditor\Plugin::isTypechoGe13()): ?>
                <script src="<?php $options->adminStaticUrl('js', 'paste.js'); ?>"></script>
            <?php endif; ?>
            <script src="<?php $options->adminStaticUrl('js', 'purify.js'); ?>"></script>
            <script src="<?php echo Util::pluginStatic('js', 'previewUtils.js'); ?>"></script>
            <script src="<?php echo Util::pluginStatic('js', 'mermaid.min.js'); ?>"></script>
            <script src="<?php echo Util::pluginStatic('js', 'mermaid.js'); ?>"></script>
            <script
                    src="<?php echo Util::parseJSD('https://cdn.jsdelivr.net/npm/html-to-md@0.8.5/dist/index.min.js'); ?>"></script>
            <link rel="stylesheet" type="text/css"
                  href="<?php echo Util::parseJSD('https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css'); ?>">
            <script type="text/javascript"
                    src="<?php echo Util::parseJSD('https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js'); ?>"></script>
            <script type="text/javascript"
                    src="<?php echo Util::parseJSD('https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js'); ?>"></script>
            <script>
                $(document).ready(function () {
                    var options = {}, isMarkdown = <?php echo intval($content->isMarkdown || !$content->have()); ?>;

                    options.strings = {
                        bold: '<?php _e('加粗'); ?> <strong> Ctrl+B',
                        boldexample: '<?php _e('加粗文字'); ?>',

                        italic: '<?php _e('斜体'); ?> <em> Ctrl+I',
                        italicexample: '<?php _e('斜体文字'); ?>',

                        link: '<?php _e('链接'); ?> <a> Ctrl+L',
                        linkdescription: '<?php _e('请输入链接描述'); ?>',

                        quote: '<?php _e('引用'); ?> <blockquote> Ctrl+Q',
                        quoteexample: '<?php _e('引用文字'); ?>',

                        code: '<?php _e('代码'); ?> <pre><code> Ctrl+K',
                        codeexample: '<?php _e('请输入代码'); ?>',

                        image: '<?php _e('图片'); ?> <img> Ctrl+G',
                        imagedescription: '<?php _e('请输入图片描述'); ?>',

                        olist: '<?php _e('数字列表'); ?> <ol> Ctrl+O',
                        ulist: '<?php _e('普通列表'); ?> <ul> Ctrl+U',
                        litem: '<?php _e('列表项目'); ?>',

                        heading: '<?php _e('标题'); ?> <h1>/<h2> Ctrl+H',
                        headingexample: '<?php _e('标题文字'); ?>',

                        hr: '<?php _e('分割线'); ?> <hr> Ctrl+R',
                        more: '<?php _e('摘要分割线'); ?> <!--more--> Ctrl+M',

                        undo: '<?php _e('撤销'); ?> - Ctrl+Z',
                        redo: '<?php _e('重做'); ?> - Ctrl+Y',
                        redomac: '<?php _e('重做'); ?> - Ctrl+Shift+Z',

                        fullscreen: '<?php _e('全屏'); ?> - Ctrl+J',
                        exitFullscreen: '<?php _e('退出全屏'); ?> - Ctrl+E',
                        fullscreenUnsupport: '<?php _e('此浏览器不支持全屏操作'); ?>',

                        imagedialog: '<p><b><?php _e('插入图片'); ?></b></p><p><?php _e('请在下方的输入框内输入要插入的远程图片地址'); ?></p><p><?php _e('您也可以使用附件功能插入上传的本地图片'); ?></p>',
                        linkdialog: '<p><b><?php _e('插入链接'); ?></b></p><p><?php _e('请在下方的输入框内输入要插入的链接地址'); ?></p>',

                        ok: '<?php _e('确定'); ?>',
                        cancel: '<?php _e('取消'); ?>',

                        help: '<?php _e('Markdown语法帮助'); ?>'
                    };

                    var textarea = $('#text'),
                        toolbar = $('<div class="editor" id="wmd-button-bar-yumeeditor" />').insertBefore(textarea.parent()),
                        pWrapper = $('<div id="wmd-preview-wrapper-yumeeditor" />').insertAfter("#text"),
                        preview = $('<div id="wmd-preview-yumeeditor" />').appendTo(pWrapper),
                        editor;

                    function initMarkdownEditor() {
                        var converter = new HyperDown();
                        editor = new Markdown.Editor(converter, '-yumeeditor', options);

                        // 自动跟随
                        converter.enableHtml(true);
                        converter.enableLine(true);

                        let additionalTags = ["x-tabs"];

                        converter.blockHtmlTags = additionalTags.length ?
                            converter.blockHtmlTags.concat("|" + additionalTags.join("|")) :
                            converter.blockHtmlTags;

                        // 修正白名单
                        converter.hook('makeHtml', function (html) {
                            // 不处理 pre/code
                            const blocks = new Map();
                            let prefix;
                            do {
                                const random = crypto.getRandomValues(new Uint32Array(4));
                                prefix = '__YUMEEDITOR_CODE_' + Array.from(random, (value) => value.toString(16)).join('_') + '_';
                            } while (html.includes(prefix));
                            html = html.replace(/(?:<pre\b[^>]*>.*?<\/pre>|<code\b[^>]*>.*?<\/code>)/gims, function (match) {
                                const holder = '<pre>' + prefix + blocks.size + '__</pre>';
                                blocks.set(holder, match);
                                return holder;
                            });

                            html = html.replace('<p><!--more--></p>', '<!--more-->');

                            if (html.indexOf('<!--more-->') > 0) {
                                var parts = html.split(/\s*<\!\-\-more\-\->\s*/),
                                    summary = parts.shift(),
                                    details = parts.join('');

                                html = '<div class="summary">' + summary + '</div>'
                                    + '<div class="details">' + details + '</div>';
                            }

                            // 替换block
                            html = html.replace(/<(iframe|embed)\s+([^>]*)>/ig, function (all, tag, src) {
                                if (src[src.length - 1] == '/') {
                                    src = src.substring(0, src.length - 1);
                                }

                                return '<div class="embed"><strong>'
                                    + tag + '</strong> : ' + $.trim(src) + '</div>';
                            });

                            // 处理短代码
                            if ("YumePreviewUtils" in window && typeof window.YumePreviewUtils.processHtml === "function") {
                                let processedHtml = window.YumePreviewUtils.processHtml(html);
                                if (processedHtml && typeof html === "string") html = processedHtml;
                            }

                            // 还原 pre/code
                            html = html.replace(/<pre>__YUMEEDITOR_CODE_[a-f0-9_]+__<\/pre>/g, function (holder) {
                                return blocks.has(holder) ? blocks.get(holder) : holder;
                            });

                            // 不注释塞不了 HTML
                            // return DOMPurify.sanitize(html, {USE_PROFILES: {html: true}});
                            return html;
                        });

                        editor.hooks.chain('onPreviewRefresh', function () {
                            var images = $('img', preview), count = images.length;

                            if (count > 0) {
                                images.bind('load error', function () {
                                    count--;

                                    if (count == 0) {
                                        $('body').trigger('YumeEditorPreviewEnd');
                                    }
                                });
                            }

                            // 修复 p 包裹问题
                            if (preview.children().length === 1 && preview.children().first().is('p')) {
                                preview.html(preview.children().first().html());
                            }

                            $('body').trigger('YumeEditorPreviewEnd');
                        });

                        <?php \Typecho\Plugin::factory('admin/editor-js.php')->markdownEditor($content); ?>

                        var th = textarea.height(),
                            uploadBtn = $('<button type="button" id="btn-fullscreen-upload" class="btn btn-link">'
                                + '<i class="i-upload"><?php _e('附件'); ?></i></button>')
                                .prependTo('.submit .right')
                                .click(function () {
                                    $('a', $('.typecho-option-tabs li').not('.active')).trigger('click');
                                    return false;
                                });

                        $('.typecho-option-tabs li').click(function () {
                            uploadBtn.find('i').toggleClass('i-upload-active',
                                $('#tab-files-btn', this).length > 0);
                        });

                        editor.hooks.chain('enterFakeFullScreen', function () {
                            th = textarea.height();
                            let tbh = toolbar.outerHeight();
                            $(document.body).addClass('fullscreen');
                            var h = $(window).height() - tbh;
                            textarea.css('height', h).get(0).style.setProperty('--offset-top', tbh + 'px');
                            document.querySelector('p.submit').style.setProperty('--offset-height', tbh + 'px');
                            preview.parent().css('height', h).get(0).style.setProperty('--offset-top', tbh + 'px');
                        });

                        editor.hooks.chain('enterFullScreen', function () {
                            $(document.body).addClass('fullscreen');
                            var h = window.screen.height - toolbar.outerHeight();
                            textarea.css('height', h);
                            preview.parent().css('height', h);
                        });

                        editor.hooks.chain('exitFullScreen', function () {
                            $(document.body).removeClass('fullscreen');
                            textarea.height(th);
                            preview.parent().height(textarea.outerHeight());
                        });

                        editor.hooks.chain('commandExecuted', function () {
                            textarea.trigger('input');
                        });

                        editor.run();

                        textarea.get(0).pagedown = editor;

                        textarea.parent().addClass('edit-area');

                        // 移动按钮（如有需要）
                        let originalBar = $('#wmd-button-row');
                        let targetBar = $('#wmd-button-row-yumeeditor');

                        // 初始移动所有子节点
                        originalBar.children().toArray().reverse().forEach((el) => {
                            targetBar.append(el);
                        });

                        // 创建观察器实例
                        let observer = new MutationObserver(function (mutations) {
                            mutations.forEach(function (mutation) {
                                // 检查是否有新增的节点
                                if (mutation.addedNodes.length > 0) {
                                    // 将新增的节点按相反顺序移动到targetBar
                                    $(mutation.addedNodes).toArray().reverse().forEach((el) => {
                                        targetBar.append(el);
                                    });
                                }
                            });
                        });

                        // 配置观察选项
                        const config = {
                            childList: true  // 观察子节点的添加或删除
                        };

                        // 开始观察目标节点
                        observer.observe(originalBar[0], config);


                        // 初始化预览区域大小
                        let previewArea = $('#wmd-preview-wrapper-yumeeditor');
                        previewArea.css('height', (parseInt($('#text').outerHeight())) + 'px');

                        // 拖拽实时改变大小
                        let resizeBtn = $('.edit-area .resize'),
                            btnPress = false;
                        resizeBtn.on('mousedown', () => {
                            document.addEventListener('mousemove', mouseMove);
                            document.addEventListener('mouseup', mouseUp);
                            btnPress = true;
                            previewArea.css("opacity", 0.25);

                            function mouseMove() {
                                if (btnPress) {
                                    previewArea.css('height', (parseInt(textarea.outerHeight()) + 'px'));
                                }
                            }

                            function mouseUp() {
                                btnPress = false;
                                previewArea.css("opacity", 1);
                                previewArea.css('height', (parseInt(textarea.outerHeight()) + 'px'));
                                document.removeEventListener('mousemove', mouseMove);
                                document.removeEventListener('mouseup', mouseUp);
                            }
                        });

                        // 优化图片及文件附件插入 Thanks to Markxuxiao
                        Typecho.insertFileToEditor = function (file, url, isImage) {
                            let html = isImage ? '![' + file + '](' + url + ')'
                                : '[' + file + '](' + url + ')';
                            if (YumeEditor.insertProcessors && Array.isArray(YumeEditor.insertProcessors)) {
                                for (let i = 0; i < YumeEditor.insertProcessors.length; i++) {
                                    if (typeof YumeEditor.insertProcessors[i] === 'function') {
                                        let {
                                            html: _html,
                                            done
                                        } = YumeEditor.insertProcessors[i].call(YumeEditor, file, url, isImage, html);
                                        if (typeof _html === "string") {
                                            html = _html;
                                        }
                                        if (done) {
                                            break;
                                        }
                                    }
                                }
                            }
                            $('body').trigger('YumeEditorReplaceSelection', [html]);
                        };

                        <?php if (\TypechoPlugin\YumeEditor\Plugin::isTypechoGe13()): ?>
                        textarea.bind('paste', function (e) {
                            const items = (e.clipboardData || e.originalEvent.clipboardData).items;

                            for (const item of items) {
                                if (item.kind === 'file') {
                                    const file = item.getAsFile();

                                    if (file.size > 0) {
                                        if (!file.name) {
                                            file.name = (new Date()).toISOString().replace(/\..+$/, '')
                                                + '.' + file.type.split('/').pop();
                                        }

                                        Typecho.uploadFile(file);
                                    }
                                }
                            }
                        });
                        <?php else: ?>
                        // 剪贴板复制图片
                        textarea.pastableTextarea().on('pasteImage', function (e, data) {
                            var name = data.name ? data.name.replace(/[\(\)\[\]\*#!]/g, '') : (new Date()).toISOString().replace(/\..+$/, '');
                            if (!name.match(/\.[a-z0-9]{2,}$/i)) {
                                var ext = data.blob.type.split('/').pop();
                                name += '.' + ext;
                            }

                            Typecho.uploadFile(new File([data.blob], name), name);
                        });
                        <?php endif; ?>

                        // 上传完成后自动插入
                        const splitRegex = /[\s,|]/gm;
                        const types = '<?php echo Util::pluginOption('YumeDisableAttachAutoInsert', ''); ?>'.replace(splitRegex, '|').split('|');
                        Typecho.uploadComplete = function (file) {
                            if (file.type.length && types.includes(file.type)) return;
                            Typecho.insertFileToEditor(file.title, file.url, file.isImage);
                        };
                    }

                    // 非 Markdown 提示
                    if (isMarkdown) {
                        $('<input type="hidden" name="markdown" value="1" />').appendTo('.submit');
                        textarea.attr('markdown', 1);
                        initMarkdownEditor();
                    } else {
                        let notice = $('<div class="message notice"><?php _e('这篇文章不是由Markdown语法创建的，为你转换为 Markdown 并继续编辑？'); ?> '
                            + '<button class="btn btn-xs primary yes"><?php _e('是'); ?></button> '
                            + '<button class="btn btn-xs no"><?php _e('否'); ?></button></div>')
                            .hide().insertBefore($("#text")).slideDown();

                        $('.yes', notice).click(function () {
                            notice.remove();
                            textarea.attr('markdown', 1)
                            $('<input type="hidden" name="markdown" value="1" />').appendTo('.submit');
                            let text = textarea.val(),
                                newText = html2md(text);
                            initMarkdownEditor();
                            editor.textOperation(function () {
                                textarea.val(newText);
                            });
                            $('body').trigger('YumeEditorInit', []);
                        });

                        $('.no', notice).click(function () {
                            textarea.attr('markdown', 0)
                            notice.remove();
                        });
                    }

                    // 增加发布工具栏显示方向切换
                    let toggleDirection = $(`<button id="toggle-vertical" class="btn square secondary" type="button" title="<?php _e("切换显示方向") ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="20" height="20"><path d="M17.699219 14.099609C15.999219 14.099609 14.699219 15.399609 14.699219 17.099609L14.699219 94C14.699219 105 23.599609 113.90039 34.599609 113.90039C45.599609 113.90039 54.5 105 54.5 94L54.5 17.099609C54.5 15.499609 53.2 14.099609 51.5 14.099609L17.699219 14.099609 z M 20.699219 20.099609L48.5 20.099609L48.5 33.699219L20.699219 33.699219L20.699219 20.099609 z M 76.199219 24.599609C75.399219 24.599609 74.599609 24.9 74.099609 25.5L58.300781 41.5C57.100781 42.7 57.200781 44.599219 58.300781 45.699219C59.500781 46.899219 61.4 46.799219 62.5 45.699219L76.199219 31.800781L96 51.599609L58.400391 89.900391C57.200391 91.100391 57.300391 92.999609 58.400391 94.099609C59.000391 94.699609 59.7 95 60.5 95C61.3 95 62.099609 94.699609 62.599609 94.099609L88.099609 80L107.40039 80L107.40039 108L54.300781 108.09961C52.700781 108.09961 51.400391 109.49961 51.400391 111.09961C51.400391 112.79961 52.700391 114.09961 54.400391 114.09961L110.5 114C112.2 114 113.5 112.7 113.5 111L113.40039 76.900391C113.40039 76.100391 113.1 75.300781 112.5 74.800781C111.9 74.200781 111.20039 73.900391 110.40039 73.900391L82.400391 74L102.30078 53.699219C103.40078 52.499219 103.40078 50.6 102.30078 49.5L78.300781 25.5C77.700781 24.9 76.999219 24.599609 76.199219 24.599609 z M 20.699219 39.699219L48.5 39.699219L48.5 54.199219L20.699219 54.199219L20.699219 39.699219 z M 20.699219 60.199219L48.5 60.199219L48.5 73.800781L20.699219 73.800781L20.699219 60.199219 z M 20.699219 79.800781L48.5 79.800781L48.5 94C48.5 101.7 42.299609 107.90039 34.599609 107.90039C26.899609 107.90039 20.699219 101.7 20.699219 94L20.699219 79.800781 z M 34.599609 91 A 3 3 0 0 0 31.599609 94 A 3 3 0 0 0 34.599609 97 A 3 3 0 0 0 37.599609 94 A 3 3 0 0 0 34.599609 91 z"/></svg></button`)
                        .on('click', function () {
                            let newVerticalStatus = (localStorage.getItem('submit-vertical') || "false") === 'true' ? 'false' : 'true';
                            localStorage.setItem('submit-vertical', newVerticalStatus);
                            $("p.submit").attr('vertical', newVerticalStatus);
                        });
                    $("p.submit").attr('vertical', localStorage.getItem('submit-vertical') || "false")
                        .prepend(toggleDirection);
                    setOffsetLeft();
                    window.addEventListener('resize', setOffsetLeft);

                    function setOffsetLeft() {
                        document.querySelector("p.submit").style.setProperty('--offset-left', (document.querySelector('.container').offsetLeft - 35) + 'px');
                    }

                    let toggleWideScreen = $(`<button id="toggle-widescreen" class="btn square secondary" type="button" title="<?php _e("切换宽屏显示") ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="20" height="20"><path d="M4 8L4 24L6 24L6 8 Z M 8 8C7.449219 8 7 8.449219 7 9C7 9.550781 7.449219 10 8 10C8.550781 10 9 9.550781 9 9C9 8.449219 8.550781 8 8 8 Z M 12 8C11.449219 8 11 8.449219 11 9C11 9.550781 11.449219 10 12 10C12.550781 10 13 9.550781 13 9C13 8.449219 12.550781 8 12 8 Z M 16 8C15.449219 8 15 8.449219 15 9C15 9.550781 15.449219 10 16 10C16.550781 10 17 9.550781 17 9C17 8.449219 16.550781 8 16 8 Z M 20 8C19.449219 8 19 8.449219 19 9C19 9.550781 19.449219 10 20 10C20.550781 10 21 9.550781 21 9C21 8.449219 20.550781 8 20 8 Z M 24 8C23.449219 8 23 8.449219 23 9C23 9.550781 23.449219 10 24 10C24.550781 10 25 9.550781 25 9C25 8.449219 24.550781 8 24 8 Z M 26 8L26 24L28 24L28 8 Z M 11 13L7 16L11 19L11 17L21 17L21 19L25 16L21 13L21 15L11 15 Z M 8 22C7.449219 22 7 22.449219 7 23C7 23.550781 7.449219 24 8 24C8.550781 24 9 23.550781 9 23C9 22.449219 8.550781 22 8 22 Z M 12 22C11.449219 22 11 22.449219 11 23C11 23.550781 11.449219 24 12 24C12.550781 24 13 23.550781 13 23C13 22.449219 12.550781 22 12 22 Z M 16 22C15.449219 22 15 22.449219 15 23C15 23.550781 15.449219 24 16 24C16.550781 24 17 23.550781 17 23C17 22.449219 16.550781 22 16 22 Z M 20 22C19.449219 22 19 22.449219 19 23C19 23.550781 19.449219 24 20 24C20.550781 24 21 23.550781 21 23C21 22.449219 20.550781 22 20 22 Z M 24 22C23.449219 22 23 22.449219 23 23C23 23.550781 23.449219 24 24 24C24.550781 24 25 23.550781 25 23C25 22.449219 24.550781 22 24 22Z"/></svg></button`)
                    toggleWideScreen.on('click', () => {
                        if (currentIsWidescreen()) {
                            $('.container').removeClass('widescreen');
                            localStorage.setItem('editor-widescreen', false);
                        } else {
                            $('.container').addClass('widescreen');
                            localStorage.setItem('editor-widescreen', true);
                        }
                        setOffsetLeft();
                    });
                    toggleDirection.after(toggleWideScreen);
                    if (currentIsWidescreen()) {
                        $('.container').addClass('widescreen');
                        setOffsetLeft();
                    }

                    function currentIsWidescreen() {
                        return (localStorage.getItem('editor-widescreen') || 'false') === 'true';
                    }
                });
            </script>
            <script src="<?php echo Util::pluginStatic('js', 'main.js'); ?>"></script>
            <link rel="stylesheet"
                  href="<?php echo Util::parseJSD('https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css') ?>">
            <?php
            if (Util::pluginOption('YumeMathJax', 'on') === 'on') {
                MathJax::render(<<<'JS'
$('body').on('YumeEditorPreviewEnd', async () => {
    try {
        const previewArea = document.getElementById('wmd-preview-yumeeditor');
        await window.YumeEditorMathSource?.typeset(previewArea || document.body);
    } catch (e) {
    }
});
JS);
            }
            require_once Util::pluginDir('assets/dist/js/editor-js.php');
        }
    }


    /**
     * 增加一个假工具条，给适配原生编辑的的插件增加插入点
     *
     * @param Contents\Post\Edit|Contents\Page\Edit $single
     * @return void
     */
    public static function fakeToolbar($single)
    {
        ?>
        <script>
            let p = document.querySelector('.url-slug').nextElementSibling;
            let toolbar = p.parentNode.insertBefore($C('div', {
                class: 'editor hidden',
                id: 'wmd-button-bar'
            }), p);

            toolbar.appendChild($C('div', {
                id: 'wmd-button-row',
                class: 'wmd-button-row'
            }));

            function $C(tag = 'div', attrs = {}, skipAttrs = []) {
                if (tag) {
                    let el = document.createElement(tag);
                    for (let a in attrs) {
                        if (skipAttrs.includes(a)) continue;
                        el.setAttribute(a, attrs[a]);
                    }
                    return el;
                }
            }
        </script>
    <?php }
}
