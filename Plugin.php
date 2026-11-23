<?php

namespace TypechoPlugin\YumeEditor;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 为 Typecho 打造的轻量 Markdown 编辑器
 *
 * 保留 Typecho 原生写作习惯，同时补齐代码块、围栏容器、Mermaid、
 * 数学公式与图片宫格等现代写作能力，后台观感与 MoePanel 保持一致，
 * 前台样式可无缝嵌入 Yume 主题。
 *
 * @package YumeEditor
 * @author aayorishy
 * @version 1.0.0
 * @since 1.0.0
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件
     *
     * @return string
     */
    public static function activate(): string
    {
        return Util::activate();
    }

    /**
     * 禁用插件
     *
     * @return string
     */
    public static function deactivate(): string
    {
        return Util::deactivate();
    }

    /**
     * 插件配置面板
     *
     * @param Form $form
     * @return void
     */
    public static function config(Form $form)
    {
        Config::render($form);
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     * @return void
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 是否 Typecho 1.3 及以上版本
     *
     * @return bool
     */
    public static function isTypechoGe13(): bool
    {
        return version_compare((string) Helper::options()->version, '1.3.0', '>=');
    }

    /**
     * 读取插件版本号
     *
     * @return string
     */
    public static function version(): string
    {
        $info = \Typecho\Plugin::parseInfo(__FILE__);
        return (string) ($info['version'] ?? '0.0.0');
    }
}
