<?php

namespace TypechoPlugin\YumeEditor;

use ReflectionClass;
use Typecho\Common;
use Typecho\Db;
use Typecho\Plugin;
use Typecho\Plugin\Exception;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 插件运行时工具集
 *
 * 负责配置读取、静态资源 URL、镜像与模板辅助。所有对外暴露的方法都保持
 * 无副作用，方便在插件、后台页面和前台模板之间复用。
 *
 * @package YumeEditor
 * @author aayorishy
 * @version 1.0.0
 */
class Util
{
    /**
     * 当前插件目录名（用于拼接 URL，避免硬编码）
     */
    public const NAME = 'YumeEditor';

    /**
     * 旧版插件标识，仅用于读取历史配置
     */
    private const LEGACY = 'AAEditor';

    /**
     * 新配置键 => 旧版配置键
     *
     * @var array<string, string>
     */
    private const LEGACY_OPTIONS = [
        'YumeEditorEnabled' => 'XEditorEnabled',
        'YumeEditorContentStyle' => 'XEditorContentStyle',
        'YumeFrontFenceStyle' => 'XFrontFenceStyle',
        'YumeFrontFenceTabsStyle' => 'XFrontFenceTabsStyle',
        'YumeMathJax' => 'XMathJaxSupport',
        'YumeMermaid' => 'XMermaidSupport',
        'YumeFontAwesome' => 'XLoadFontAwesome',
        'YumeHljs' => 'XHljs',
        'YumeInsertAllImages' => 'XInsertALlImages',
        'YumeJsdelivrMirror' => 'XJsdelivrMirror',
        'YumeDisableAttachAutoInsert' => 'XDisableAttachAutoInsert',
        'YumeCleanDatabase' => 'XCleanDatabase',
    ];

    /**
     * 静态资源清单缓存
     *
     * @var array|null
     */
    private static $manifest;

    /**
     * 需要本地镜像的 CDN 资源
     *
     * @var array<string, string>
     */
    private static $staticMap = [
        'https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css' => 'css/font-awesome.min.css',
        'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11/build/highlight.min.js' => 'js/highlight.min.js',
        'https://cdn.jsdelivr.net/npm/html-to-md@0.8.5/dist/index.min.js' => 'js/html-to-md.min.js',
        'https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css' => 'css/toastify.min.css',
        'https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.js' => 'js/toastify.min.js',
        'https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js' => 'js/toastify.min.js',
        'https://cdn.jsdelivr.net/npm/aplayer@1/dist/APlayer.min.js' => 'js/APlayer.min.js',
        'https://cdn.jsdelivr.net/npm/aplayer@1/dist/APlayer.min.css' => 'css/APlayer.min.css',
        'https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js' => 'js/clipboard.min.js',
    ];

    /**
     * 注册插件钩子
     *
     * @return string
     */
    public static function activate(): string
    {
        // 前台内容增强
        Plugin::factory('\Widget\Archive')->footer = [Front::class, 'assets'];
        Plugin::factory('\Widget\Base\Contents')->markdown = [Content::class, 'markdown'];
        Plugin::factory('\Widget\Base\Contents')->contentEx_99 = [Content::class, 'contentEx'];
        Plugin::factory('\Widget\Base\Contents')->excerptEx_99 = [Content::class, 'excerptEx'];
        Plugin::factory('\Widget\Feedback')->finishComment = [Content::class, 'rememberCommentPermission'];

        // 后台写作页增强
        Plugin::factory('admin/write-post.php')->richEditor = [Editor::class, 'assets'];
        Plugin::factory('admin/write-page.php')->richEditor = [Editor::class, 'assets'];
        Plugin::factory('admin/write-post.php')->option = [Editor::class, 'fakeToolbar'];
        Plugin::factory('admin/write-page.php')->option = [Editor::class, 'fakeToolbar'];
        Plugin::factory('admin/write-post.php')->bottom = [Editor::class, 'footer'];
        Plugin::factory('admin/write-page.php')->bottom = [Editor::class, 'footer'];

        $url = Common::url('options-plugin.php?config=' . self::NAME, Helper::options()->adminUrl);

        return _t('插件已启用，<a href="%s">点此进入插件设置</a>', $url);
    }

    /**
     * 禁用插件
     *
     * @return string
     * @throws Exception|Db\Exception
     */
    public static function deactivate(): string
    {
        if (self::pluginOption('YumeCleanDatabase', 'none') !== 'clean') {
            return _t('插件已禁用，配置与备份数据已保留。');
        }

        $db = Db::get();
        $db->query($db->delete('table.options')->where(
            'name = ?',
            'plugin:' . self::NAME . 'Backup'
        ));

        return _t('插件已禁用，备份数据已清理。');
    }

    /**
     * 获取插件资源 URL
     *
     * @param string $uri 资源相对路径
     * @return string
     */
    public static function pluginUrl(string $uri = ''): string
    {
        return Common::url($uri, Helper::options()->pluginUrl . '/' . self::NAME);
    }

    /**
     * 获取模块资源 URL
     *
     * @param string $moduleName 模块目录名
     * @param string $uri 资源相对路径
     * @return string
     */
    public static function moduleUrl(string $moduleName, string $uri = ''): string
    {
        $relativePath = Common::url(Common::url($uri, $moduleName), 'Modules');
        $absolutePath = Common::url($relativePath, dirname(__FILE__));
        if (is_file($absolutePath)) {
            return self::pluginUrl($relativePath . '?h=' . md5($absolutePath));
        }
        return self::pluginUrl($relativePath);
    }

    /**
     * 读取静态资源清单（构建产物）
     *
     * @return void
     */
    public static function collectManifest(): void
    {
        $manifestPath = self::pluginDir('assets/dist/mix-manifest.json');
        if (!is_file($manifestPath)) {
            self::$manifest = [];
            return;
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        self::$manifest = is_array($decoded) ? $decoded : [];
    }

    /**
     * 获取静态资源 URL（带内容指纹）
     *
     * @param string $type 资源类型 js/css
     * @param string $path 资源相对路径
     * @return string
     */
    public static function pluginStatic(string $type, string $path): string
    {
        self::collectManifest();
        $uri = '/' . Common::url($path, $type);
        $absolutePath = self::pluginDir('assets/dist' . $uri);
        $versioned = self::$manifest[$uri] ?? $uri;

        // A plain manifest entry means the build did not fingerprint the file;
        // fall back to a content hash so browsers never serve stale assets.
        if ($versioned === $uri && strpos($versioned, '?') === false && is_file($absolutePath)) {
            $versioned .= '?h=' . md5_file($absolutePath);
        }

        return self::pluginUrl('assets/dist/' . $versioned);
    }

    /**
     * 获取本地镜像路径
     *
     * @param string $uri 相对路径
     * @return string
     */
    public static function pluginMirror(string $uri): string
    {
        return Common::url($uri, self::pluginUrl('assets/mirror/'));
    }

    /**
     * 获取插件文件绝对路径
     *
     * @param string $uri 相对于插件目录的路径
     * @return string
     */
    public static function pluginDir(string $uri = ''): string
    {
        return dirname(__FILE__) . ($uri === '' ? '' : DIRECTORY_SEPARATOR . str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            ltrim($uri, '/\\')
        ));
    }

    /**
     * 获取插件配置
     *
     * 优先读取 YumeEditor 配置；缺失时回退到旧版插件的同义项，
     * 这样从旧版本升级上来的站点无需重新配置。
     *
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function pluginOption(string $key, $default = null)
    {
        $value = self::readOption(self::NAME, $key);
        if ($value === null || $value === '' || $value === false) {
            $legacyKey = self::LEGACY_OPTIONS[$key] ?? null;
            if ($legacyKey !== null) {
                $value = self::readOption(self::LEGACY, $legacyKey);
            }
        }

        return ($value === null || $value === '' || $value === false) ? $default : $value;
    }

    /**
     * 读取指定插件的配置项
     *
     * @param string $plugin 插件标识
     * @param string $key 配置键
     * @return mixed
     */
    private static function readOption(string $plugin, string $key)
    {
        try {
            $config = Helper::options()->plugin($plugin);
            return $config === null ? null : ($config->{$key} ?? null);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * MathJax 脚本地址
     *
     * 本地镜像只内置了 CHTML 构建，远端镜像使用官方 SVG 构建；两者都由
     * MathJax 3 提供相同的 TeX 输入与公式选中复制能力。
     *
     * @return string
     */
    public static function mathJaxScriptUrl(): string
    {
        if (
            self::pluginOption('YumeJsdelivrMirror', 'local') === 'local'
            && is_file(self::pluginDir('assets/mirror/js/tex-mml-chtml.js'))
        ) {
            return self::pluginMirror('js/tex-mml-chtml.js');
        }

        return self::parseJSD('https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js');
    }

    /**
     * 按镜像设置返回 CDN 资源地址
     *
     * @param string $url 原始资源地址
     * @return string
     */
    public static function parseJSD(string $url): string
    {
        $mirror = self::pluginOption('YumeJsdelivrMirror', 'local');

        if ($mirror !== 'local') {
            return preg_replace('/((https?:)?)\/\/cdn\.jsdelivr\.net/', $mirror, $url);
        }

        if (rtrim($url, '/') === 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11/build/styles') {
            return str_replace(
                'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11/build/styles',
                self::pluginStatic('css', 'highlight.js'),
                $url
            );
        }

        if (isset(self::$staticMap[$url])) {
            return self::pluginMirror(self::$staticMap[$url]);
        }

        return $url;
    }

    /**
     * 截断文本
     *
     * @param string $text 需要截断的文本
     * @param int $length 长度
     * @param string $trim 结尾
     * @return string
     */
    public static function subStr(string $text, int $length = 120, string $trim = '...'): string
    {
        return Common::fixHtml(Common::subStr($text, 0, $length, $trim));
    }

    /**
     * 通过反射读取内部变量
     *
     * @param mixed $object
     * @param string $name
     * @return mixed
     * @throws \ReflectionException
     */
    public static function reflectGetValue($object, string $name)
    {
        $reflect = new ReflectionClass($object);
        $property = $reflect->getProperty($name);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * 列出所有 Highlight.js 样式
     *
     * @return array<string, string>
     */
    public static function listHljsCss(): array
    {
        $cssFiles = [];
        $cssPath = self::pluginDir('assets/dist/css/highlight.js');
        $base16Path = $cssPath . DIRECTORY_SEPARATOR . 'base16';

        $collect = static function (string $path, string $prefix = '') use (&$cssFiles): void {
            foreach (glob($path . DIRECTORY_SEPARATOR . '*.css') ?: [] as $file) {
                $name = basename($file);
                $cssFiles[$prefix . $name] = basename($name, '.css');
            }
        };

        $collect($cssPath);
        $collect($base16Path, 'base16/');
        asort($cssFiles);

        return $cssFiles;
    }

    /**
     * 对象转 HTML 模板
     *
     * @param mixed $widget
     * @param string $template
     * @return string
     */
    public static function toString($widget, string $template): string
    {
        return preg_replace_callback(
            '/\{([_a-z0-9]+)\}/i',
            static function (array $matches) use ($widget) {
                return $widget->{$matches[1]};
            },
            $template
        );
    }
}
