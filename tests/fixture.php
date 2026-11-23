<?php

/**
 * 输出设置页 HTML，供 JavaScript 侧的行为测试与本地预览复用。
 *
 * 用法：
 *   php tests/fixture.php            # 无备份
 *   php tests/fixture.php --backup   # 已有备份
 */

define('__TYPECHO_ROOT_DIR__', dirname(__DIR__));
require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/Plugin.php';
require_once dirname(__DIR__) . '/Config.php';

\Utils\Helper::$options = (object) [
    'adminUrl' => 'https://example.test/admin/',
    'pluginUrl' => 'https://example.test/usr/plugins',
    'version' => '1.2.1',
    'secret' => 'test-only-site-secret',
    'theme' => 'yume',
];

$hasBackup = in_array('--backup', $argv ?? [], true);
$html = ym_run_config('GET', null, null, $hasBackup)[2];

if (in_array('--fragment', $argv ?? [], true)) {
    echo $html;
    return;
}

echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
    . '<title>YumeEditor settings fixture</title></head><body>'
    . '<div class="row typecho-page-main"><div class="col-mb-12 col-tb-8 col-tb-offset-2">'
    . $html
    . '</div></div></body></html>';
