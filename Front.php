<?php

namespace TypechoPlugin\YumeEditor;

use Typecho\Common;
use Typecho\Plugin\Exception;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 前台资源注入
 *
 * 统一管理正文样式、围栏样式、代码高亮、Mermaid 与公式脚本。
 *
 * @package YumeEditor
 * @author aayorishy
 * @version 1.0.0
 */
class Front
{

    /**
     * 前台附加 CSS JS
     * @return void
     * @throws Exception
     */
    public static function assets($archive)
    {
        Util::collectManifest();
        ?>
        <script src="<?php echo Util::pluginStatic('js', 'masonry.pkgd.min.js'); ?>"></script>
        <script src="<?php echo Util::pluginStatic('js', 'front.js'); ?>"></script>
        <?php
        if (Util::pluginOption('YumeMermaid', 'on') === 'on') {
            ?>
            <link rel="stylesheet" href="<?php echo Util::pluginStatic('css', 'mermaid.css'); ?>">
            <script src="<?php echo Util::pluginStatic('js', 'mermaid.min.js'); ?>"></script>
            <script src="<?php echo Util::pluginStatic('js', 'mermaid.js'); ?>"></script>
            <?php
        }
        if (Util::pluginOption('YumeEditorContentStyle', 'on') === 'on') {
            ?>
            <link rel="stylesheet"
                  href="<?php echo Util::pluginStatic('css', 'front-content.css'); ?>">
            <?php
        }
        if (Util::pluginOption('YumeFrontFenceStyle', 'on') === 'on') {
            ?>
            <link rel="stylesheet"
                  href="<?php echo Util::pluginStatic('css', 'front-fence.css'); ?>">
            <?php
        }
        if (Util::pluginOption('YumeFrontFenceTabsStyle', 'on') === 'on') {
            ?>
            <link rel="stylesheet"
                  href="<?php echo Util::pluginStatic('css', 'front-tabs.css'); ?>">
            <?php
        }
        $lfa = Util::pluginOption('YumeFontAwesome', 'auto');
        if ($lfa === 'on') {
            // 引入字体图标
            ?>
            <link rel="stylesheet"
                  href="<?php echo Util::parseJSD('https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css'); ?>">
            <?php
        } else if ($lfa === 'auto') {
            ?>
            <script>
                (function () {
                    function loadFontAwesome() {
                        if (document.querySelector('.fa')) {
                            let rel = document.createElement('link');
                            rel.rel = 'stylesheet';
                            rel.href = '<?php echo Util::parseJSD('https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css'); ?>';
                            document.body.appendChild(rel);
                        }
                    }

                    loadFontAwesome();
                    document.addEventListener('pjax:complete', () => {
                        loadFontAwesome();
                    });
                })();
            </script>
            <?php
        }
        if (Util::pluginOption('YumeMathJax', 'on') === 'on') {
            MathJax::render(<<<'JS'
document.addEventListener('pjax:complete', async () => {
    try {
        await window.YumeEditorMathSource?.typeset(document.body);
    } catch (e) {
    }
});
JS);
        }
        $cssFiles = Util::listHljsCss();
        $filename = Util::pluginOption('YumeHljs', 'off');
        if ($filename !== "off" && array_key_exists($filename, $cssFiles)) {
            ?>
            <script
                    src="<?php echo Util::parseJSD('https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11/build/highlight.min.js') ?>"></script>
            <link rel="stylesheet"
                  href="<?php echo Common::url(preg_replace("/(?<!\.min)\.css$/", ".min.css", $filename), Util::parseJSD('https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11/build/styles/')) ?>">
            <link rel="stylesheet" href="<?php echo Util::pluginStatic('css', 'hljs.css') ?>"/>
            <script>
                window.YumeEditorCodeBlocksConfig = Object.assign({}, window.YumeEditorCodeBlocksConfig || {}, {
                    messages: {
                        copyButtonTitle: '<?php _e("点击复制"); ?>',
                        copySuccessTitle: '<?php _e("复制成功"); ?>',
                        copyErrorTitle: '<?php _e("复制失败，请手动复制"); ?>'
                    }
                });
            </script>
            <?php
        }
    }
}
