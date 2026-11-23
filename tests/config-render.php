<?php

/**
 * 设置页结构契约：分区、字段、状态卡片与品牌信息都必须由服务端渲染，
 * 这样在没有 JavaScript 的环境里也能保存配置。
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

/**
 * 把设置页 HTML 解析成可查询的 DOM。
 *
 * @param string $html
 * @return array{dom: DOMDocument, xpath: DOMXPath}
 */
function parseConfigHtml(string $html): array
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return ['dom' => $dom, 'xpath' => new DOMXPath($dom)];
}

[$db, $form, $html, $error] = ym_run_config('GET', null, null, false);
ym_check($error === null, 'configuration renders without error');
ym_check(empty($db->writes), 'configuration render does not write');

$parsed = parseConfigHtml($html);
$xpath = $parsed['xpath'];

// 品牌与版本
ym_check($xpath->query('//div[contains(@class,"ym-brand")]')->length === 1, 'brand block');
ym_check($xpath->query('//div[@class="ym-config"]')->item(0)->getAttribute('data-version') === '1.0.0', 'version badge');
ym_check(strpos($html, 'ayorishy') !== false, 'author credit');
ym_check(strpos($html, 'AAEditor') === false, 'no legacy plugin name');

// 导航与静态面板
ym_check($xpath->query('//button[contains(@class,"ym-nav__item")]')->length === 4, 'four navigation sections');
ym_check($xpath->query('//section[@data-panel="overview"]')->length === 1, 'overview panel');
ym_check($xpath->query('//ul[contains(@class,"ym-status")]//li')->length === 8, 'status list');
ym_check($xpath->query('//article[contains(@class,"ym-card")]')->length === 4, 'overview cards');
ym_check($xpath->query('//ul[contains(@class,"ym-chips")]/li')->length === 7, 'feature chips');
ym_check($xpath->query('//div[@id="ym-form-host"]')->length === 1, 'form host');

// 资源引用
ym_check($xpath->query('//link[contains(@href,"config.css")]')->length === 1, 'config stylesheet');
ym_check($xpath->query('//script[contains(@src,"config.js")]')->length === 1, 'config script');

// 设置分区与字段
$sections = ['editor', 'render', 'system'];
foreach ($sections as $section) {
    $heads = $xpath->query('//ul[contains(@class,"ym-section-head")][@data-ym-section="' . $section . '"]');
    ym_check($heads->length === 1, 'section head ' . $section);
}

$fields = $xpath->query('//ul[contains(@class,"ym-field")]');
ym_check($fields->length === 12, 'twelve settings fields');

$controls = [];
$bySection = ['editor' => 0, 'render' => 0, 'system' => 0];
$switchFields = 0;
foreach ($fields as $field) {
    $section = $field->getAttribute('data-ym-section');
    ym_check(isset($bySection[$section]), 'field belongs to a section');
    $bySection[$section]++;
    $control = $field->getAttribute('data-ym-control');
    $controls[$control] = true;
    if ($control === 'switch') {
        $switchFields++;
        ym_check($xpath->query('.//select/option', $field)->length === 2, 'switch field has two states');
    }
    ym_check(strpos($field->getAttribute('class'), 'typecho-option') !== false, 'field keeps typecho-option class');
    ym_check($xpath->query('.//label[@class="typecho-label"]', $field)->length === 1, 'field label');
    ym_check($xpath->query('.//p[@class="description"]', $field)->length === 1, 'field description');
    ym_check($xpath->query('.//*[@name]', $field)->length >= 1, 'field control is named for submission');
}

ym_check($bySection === ['editor' => 3, 'render' => 7, 'system' => 2], 'fields are grouped 3/7/2');
ym_check($switchFields === 7, 'seven switch fields');
ym_check(isset($controls['segmented']), 'segmented control present');
ym_check(isset($controls['select']), 'select control present');

// 代码高亮主题来自安装包内的 highlight.js 样式
$hljs = $xpath->query('//ul[contains(@class,"ym-field")]//select[@name="YumeHljs"]/option')->item(0);
ym_check($hljs !== null && $hljs->getAttribute('value') === 'off', 'highlight theme defaults to off first');
ym_check($xpath->query('//select[@name="YumeHljs"]/option[@value="github.css"]')->length === 1, 'highlight themes listed');

// 备份入口
$backupForms = $xpath->query('//form[starts-with(@id,"ym-backup-")]');
ym_check($backupForms->length === 3, 'three backup forms');
foreach (['backup', 'restore', 'delete'] as $type) {
    $backupForm = $xpath->query('//form[@id="ym-backup-' . $type . '"]')->item(0);
    ym_check($backupForm !== null && $backupForm->getAttribute('method') === 'post', $type . ' form posts');
    ym_check($xpath->query('.//input[@name="type"]', $backupForm)->item(0)->getAttribute('value') === $type, $type . ' operation');
    ym_check($xpath->query('.//input[@name="_"]', $backupForm)->item(0)->getAttribute('value') !== '', $type . ' token');
    ym_check($xpath->query('.//button[@type="submit"]', $backupForm)->length === 1, $type . ' submit button');
}

// 没有备份时给出提示，并允许一键备份
$alert = $xpath->query('//div[contains(@class,"ym-alert")]');
ym_check($alert->length === 1, 'backup hint without backup');
ym_check($xpath->query('//button[@form="ym-backup-backup"]')->length === 1, 'hint can trigger backup');
ym_check($xpath->query('//form[@id="normal-config-form"]')->length === 1, 'native settings form rendered');
ym_check($xpath->query('//form//form')->length === 0, 'no nested forms');

// 已有备份时不再提示
$withBackup = parseConfigHtml(ym_run_config('GET', null, null, true)[2])['xpath'];
ym_check($withBackup->query('//div[contains(@class,"ym-alert")]')->length === 0, 'no hint when backup exists');

echo "config render tests passed\n";
