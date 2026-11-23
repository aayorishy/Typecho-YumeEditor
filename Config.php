<?php

namespace TypechoPlugin\YumeEditor;

use Typecho\Common;
use Typecho\Db;
use Typecho\Plugin\Exception;
use Typecho\Request;
use Typecho\Response as HttpResponse;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Layout;
use Typecho\Widget\Response as WidgetResponse;
use Utils\Helper;
use Widget\Notice;
use Widget\Security;
use Widget\User;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 插件设置页
 *
 * 视觉语言完全对齐 MoePanel（暖纸底色 / 橙色强调 / 大字圆角卡片），
 * 未安装 MoePanel 时使用内置的同款设计变量，观感保持一致。
 *
 * @package YumeEditor
 * @author aayorishy
 * @version 1.0.0
 */
class Config
{
    /**
     * 设置分区
     *
     * @var array<string, array{index: string, label: string, title: string, note: string}>
     */
    private const SECTIONS = [
        'overview' => [
            'index' => '01',
            'label' => '概览',
            'title' => 'YumeEditor',
            'note' => '版本信息、运行状态与语法速查',
        ],
        'editor' => [
            'index' => '02',
            'label' => '编辑器',
            'title' => '写作体验',
            'note' => '是否接管写作页，以及附件插入行为',
        ],
        'render' => [
            'index' => '03',
            'label' => '前台渲染',
            'title' => '前台渲染',
            'note' => '正文样式、围栏容器、公式与代码高亮',
        ],
        'system' => [
            'index' => '04',
            'label' => '资源与数据',
            'title' => '资源与数据',
            'note' => '静态资源镜像与卸载时的数据清理',
        ],
    ];

    /**
     * 设置项定义
     *
     * @var array<int, array<string, mixed>>
     */
    private const FIELDS = [
        [
            'key' => 'YumeEditorEnabled',
            'section' => 'editor',
            'type' => 'select',
            'control' => 'switch',
            'options' => ['on' => '开启', 'off' => '关闭'],
            'default' => 'on',
            'label' => '启用编辑器',
            'description' => '关闭后写作页回到 Typecho 原生输入框，仅保留正文与围栏的前台渲染能力。',
        ],
        [
            'key' => 'YumeInsertAllImages',
            'section' => 'editor',
            'type' => 'select',
            'control' => 'switch',
            'options' => ['on' => '开启', 'off' => '关闭'],
            'default' => 'on',
            'label' => '一键插入全部图片',
            'description' => '在附件面板增加「插入所有图片」按钮，按顺序生成 Markdown 图片语法。',
        ],
        [
            'key' => 'YumeDisableAttachAutoInsert',
            'section' => 'editor',
            'type' => 'text',
            'default' => '',
            'placeholder' => 'zip|pdf',
            'label' => '禁止自动插入附件的后缀',
            'description' => '填写扩展名，用 <code>|</code> 分隔，例如 <code>zip|pdf</code>。命中的附件上传后不会自动写入正文。',
        ],
        [
            'key' => 'YumeEditorContentStyle',
            'section' => 'render',
            'type' => 'select',
            'control' => 'switch',
            'options' => ['on' => '开启', 'off' => '关闭'],
            'default' => 'on',
            'label' => '前台正文排版样式',
            'description' => '为标题、段落、表格、引用等元素提供统一排版。Yume 主题的 <code>.prose</code> 与常见 <code>.post-content</code> 均已适配。',
        ],
        [
            'key' => 'YumeFrontFenceStyle',
            'section' => 'render',
            'type' => 'select',
            'control' => 'switch',
            'options' => ['on' => '开启', 'off' => '关闭'],
            'default' => 'on',
            'label' => '围栏容器样式',
            'description' => '为 <code>::: info</code>、<code>::: note</code>、<code>::: warning</code>、<code>::: details</code>、<code>::: pass</code> 等围栏提供样式。',
        ],
        [
            'key' => 'YumeFrontFenceTabsStyle',
            'section' => 'render',
            'type' => 'select',
            'control' => 'switch',
            'options' => ['on' => '开启', 'off' => '关闭'],
            'default' => 'on',
            'label' => '标签页围栏样式',
            'description' => '仅影响 <code>::: tabs</code> / <code>::: tab</code> 生成的标签页外观与基础布局。',
        ],
        [
            'key' => 'YumeMathJax',
            'section' => 'render',
            'type' => 'select',
            'control' => 'switch',
            'options' => ['on' => '开启', 'off' => '关闭'],
            'default' => 'on',
            'label' => '数学公式',
            'description' => '使用 MathJax 渲染公式，不依赖额外字体；双击公式可复制原始 TeX 源码。',
        ],
        [
            'key' => 'YumeMermaid',
            'section' => 'render',
            'type' => 'select',
            'control' => 'switch',
            'options' => ['on' => '开启', 'off' => '关闭'],
            'default' => 'on',
            'label' => 'Mermaid 图表',
            'description' => '前台渲染 Mermaid 代码块；关闭后前台保留源码，后台预览始终可用。',
        ],
        [
            'key' => 'YumeFontAwesome',
            'section' => 'render',
            'type' => 'select',
            'control' => 'segmented',
            'options' => ['auto' => '自动', 'on' => '总是', 'off' => '从不'],
            'default' => 'auto',
            'label' => 'Font Awesome 图标',
            'description' => '自动模式下仅在页面出现 <code>.fa</code> 图标时加载，减少一次无用的网络请求。',
        ],
        [
            'key' => 'YumeHljs',
            'section' => 'render',
            'type' => 'select',
            'control' => 'select',
            'options' => ['off' => '关闭（默认）'],
            'default' => 'off',
            'label' => '代码高亮主题',
            'description' => '选择 Highlight.js 主题后前台加载对应样式；关闭则使用编辑器自带的中性配色。',
        ],
        [
            'key' => 'YumeJsdelivrMirror',
            'section' => 'system',
            'type' => 'select',
            'control' => 'select',
            'options' => ['local' => '本地镜像（推荐）'],
            'default' => 'local',
            'label' => 'jsDelivr 镜像',
            'description' => '本地镜像随插件分发，无需外网；如需更新脚本可切换到公共镜像。',
        ],
        [
            'key' => 'YumeCleanDatabase',
            'section' => 'system',
            'type' => 'radio',
            'control' => 'segmented',
            'options' => ['none' => '保留数据', 'clean' => '卸载时清理'],
            'default' => 'none',
            'label' => '禁用插件时的数据策略',
            'description' => '选择清理会删除本插件生成的设置备份，设置项本身仍由 Typecho 管理。',
        ],
    ];

    /**
     * 渲染设置页
     *
     * @param Form $form
     * @return void
     */
    public static function render(Form $form): void
    {
        self::handleBackupActions();

        Util::collectManifest();
        $version = Plugin::version();
        $backupAction = htmlspecialchars(
            Common::url('options-plugin.php?config=' . Util::NAME, Helper::options()->adminUrl),
            ENT_QUOTES,
            'UTF-8'
        );
        $token = htmlspecialchars(
            Security::alloc()->getToken(Request::getInstance()->getRequestUrl()),
            ENT_QUOTES,
            'UTF-8'
        );
        $hasBackup = self::hasBackup();

        ?>
        <link rel="stylesheet" href="<?php echo Util::pluginStatic('css', 'config.css'); ?>">
        <script src="<?php echo Util::pluginStatic('js', 'config.js'); ?>" defer></script>
        <div class="ym-config" data-version="<?php echo htmlspecialchars($version, ENT_QUOTES, 'UTF-8'); ?>">
            <aside class="ym-aside">
                <div class="ym-brand">
                    <span class="ym-brand__mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 20h4L20 8a2.8 2.8 0 0 0-4-4L4 16v4z"/>
                            <path d="M14.5 5.5 18.5 9.5"/>
                        </svg>
                    </span>
                    <span class="ym-brand__text">
                        <strong>YumeEditor</strong>
                        <small>v<?php echo htmlspecialchars($version, ENT_QUOTES, 'UTF-8'); ?> · aayorishy</small>
                    </span>
                </div>

                <nav class="ym-nav" role="tablist" aria-label="YumeEditor 设置导航">
                    <?php foreach (self::SECTIONS as $key => $section): ?>
                        <button type="button"
                                class="ym-nav__item<?php echo $key === 'overview' ? ' is-active' : ''; ?>"
                                role="tab"
                                data-target="<?php echo $key; ?>"
                                aria-selected="<?php echo $key === 'overview' ? 'true' : 'false'; ?>">
                            <i><?php echo $section['index']; ?></i>
                            <span><?php echo $section['label']; ?></span>
                        </button>
                    <?php endforeach; ?>
                </nav>

                <div class="ym-aside__foot">
                    <p class="ym-kicker">设置备份</p>
                    <div class="ym-backup">
                        <?php foreach ([
                            'backup' => ['label' => '备份当前设置', 'tone' => 'accent'],
                            'restore' => ['label' => '还原上次备份', 'tone' => 'plain'],
                            'delete' => ['label' => '删除备份', 'tone' => 'danger'],
                        ] as $type => $action): ?>
                            <form id="ym-backup-<?php echo $type; ?>"
                                  class="ym-backup__form"
                                  method="post"
                                  action="<?php echo $backupAction; ?>">
                                <input type="hidden" name="type" value="<?php echo $type; ?>">
                                <input type="hidden" name="_" value="<?php echo $token; ?>">
                                <button type="submit"
                                        class="ym-btn ym-btn--<?php echo $action['tone']; ?><?php echo $type === 'delete' ? ' ym-btn--confirm' : ''; ?>"
                                        data-confirm="<?php echo $type === 'delete' ? '确定删除设置备份吗？' : ''; ?>">
                                    <?php echo $action['label']; ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                    <p class="ym-aside__note">
                        <?php echo $hasBackup ? '已存在一份设置备份，可随时还原。' : '尚未创建备份，建议保存设置后备份一次。'; ?>
                    </p>
                </div>
            </aside>

            <div class="ym-main">
                <header class="ym-head">
                    <p class="ym-eyebrow">
                        <span class="ym-dot"></span>TYPECHO PLUGIN<span class="ym-rule"></span>EDITOR
                    </p>
                    <h1 class="ym-title">让写作更像<em>写作</em></h1>
                    <p class="ym-lede">
                        YumeEditor 在 Typecho 原生 Markdown 之上补充代码块、围栏容器、公式与图表，
                        后台面板与 MoePanel 同源，前台样式贴合 Yume 主题。
                    </p>
                    <ul class="ym-chips">
                        <li class="ym-chip">Markdown</li>
                        <li class="ym-chip">代码高亮</li>
                        <li class="ym-chip">::: 围栏</li>
                        <li class="ym-chip">MathJax</li>
                        <li class="ym-chip">Mermaid</li>
                        <li class="ym-chip ym-chip--acid">Yume 主题</li>
                        <li class="ym-chip ym-chip--acid">MoePanel</li>
                    </ul>
                </header>

                <?php if (!$hasBackup): ?>
                    <div class="ym-alert">
                        <span class="ym-alert__icon" aria-hidden="true">!</span>
                        <p class="ym-alert__text">
                            当前没有设置备份。保存设置后点击 <b>备份当前设置</b>，可以随时回滚到这一版配置。
                        </p>
                        <button type="submit" form="ym-backup-backup" class="ym-btn ym-btn--accent ym-btn--s">
                            立即备份
                        </button>
                    </div>
                <?php endif; ?>

                <section class="ym-panel is-active" data-panel="overview" role="tabpanel">
                    <div class="ym-grid">
                        <article class="ym-card ym-card--wide">
                            <p class="ym-card__kicker">CURRENT STATE</p>
                            <h2 class="ym-card__title">运行状态</h2>
                            <ul class="ym-status">
                                <?php foreach (self::statusItems() as $item): ?>
                                    <li class="ym-status__item">
                                        <span class="ym-status__label"><?php echo $item['label']; ?></span>
                                        <span class="ym-status__value<?php echo $item['on'] ? ' is-on' : ' is-off'; ?>">
                                            <?php echo $item['value']; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>

                        <article class="ym-card">
                            <p class="ym-card__kicker">ABOUT</p>
                            <h2 class="ym-card__title">关于本插件</h2>
                            <p class="ym-card__text">
                                YumeEditor 由 aayorishy 维护，定位于「不打扰写作」的轻量增强：
                                保留原生编辑器的一切操作习惯，把复杂排版交给 Markdown 围栏语法。
                            </p>
                            <dl class="ym-meta">
                                <div><dt>版本</dt><dd>1.0.0</dd></div>
                                <div><dt>作者</dt><dd>aayorishy</dd></div>
                                <div><dt>依赖</dt><dd>PHP 7.2+ / Typecho 1.2+</dd></div>
                            </dl>
                        </article>

                        <article class="ym-card">
                            <p class="ym-card__kicker">QUICK START</p>
                            <h2 class="ym-card__title">三步写出想要的排版</h2>
                            <ol class="ym-steps">
                                <li><b>写正文</b><span>照常用 Markdown 书写，标题、列表、表格无需额外语法。</span></li>
                                <li><b>加围栏</b><span>用 <code>::: note</code> 块做提示，用 <code>::: tabs</code> 做切换内容。</span></li>
                                <li><b>发出去</b><span>前台自动渲染，Yume 主题与 MoePanel 均已适配。</span></li>
                            </ol>
                        </article>

                        <article class="ym-card ym-card--wide">
                            <p class="ym-card__kicker">SYNTAX</p>
                            <h2 class="ym-card__title">常用语法速查</h2>
                            <div class="ym-syntax">
                                <div class="ym-syntax__row"><code>```php 标题</code><span>带标题的代码块</span></div>
                                <div class="ym-syntax__row"><code>::: note 提示</code><span>提示 / 信息 / 警告容器</span></div>
                                <div class="ym-syntax__row"><code>::: tabs</code><span>标签页，内部用 <code>::: tab 名称</code></span></div>
                                <div class="ym-syntax__row"><code>::: details 说明</code><span>可折叠的详情块</span></div>
                                <div class="ym-syntax__row"><code>::: grid {columns: 3}</code><span>图片宫格与瀑布流</span></div>
                                <div class="ym-syntax__row"><code>$$ E=mc^2 $$</code><span>独立行公式</span></div>
                            </div>
                        </article>
                    </div>
                </section>

                <div class="ym-form" id="ym-form-host"></div>
            </div>
        </div>
        <?php
        self::buildFormItems($form);
    }

    /**
     * 向 Typecho 表单注册设置项
     *
     * @param Form $form
     * @return void
     */
    private static function buildFormItems(Form $form): void
    {
        $groups = [];
        foreach (self::FIELDS as $field) {
            $groups[$field['section']][] = $field;
        }

        foreach ($groups as $section => $fields) {
            if ($section === 'overview') {
                continue;
            }

            $meta = self::SECTIONS[$section];
            $head = new Layout();
            $head->setTagName('ul');
            $head->setAttribute('class', 'ym-section-head');
            $head->setAttribute('data-ym-section', $section);
            $head->html(
                '<li>'
                . '<span class="ym-section-head__index">' . $meta['index'] . '</span>'
                . '<span class="ym-section-head__text">'
                . '<strong>' . htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') . '</strong>'
                . '<small>' . htmlspecialchars($meta['note'], ENT_QUOTES, 'UTF-8') . '</small>'
                . '</span></li>'
            );
            $form->addItem($head);

            foreach ($fields as $field) {
                $element = self::createElement($field);
                $element->setAttribute('class', 'ym-field typecho-option');
                $element->setAttribute('data-ym-section', $section);
                $element->setAttribute('data-ym-control', $field['control'] ?? 'select');
                $form->addInput($element);
            }
        }
    }

    /**
     * 创建单个表单元素
     *
     * @param array<string, mixed> $field
     * @return Select|Radio|Text
     */
    private static function createElement(array $field)
    {
        $key = $field['key'];
        $value = Util::pluginOption($key, $field['default'] ?? null);
        $options = $field['options'] ?? [];

        // 代码高亮主题随安装包内的 highlight.js 样式动态生成
        if ($key === 'YumeHljs') {
            $options = ['off' => '关闭（默认）'] + Util::listHljsCss();
        }

        switch ($field['type']) {
            case 'text':
                $element = new Text($key, null, $value, $field['label'], $field['description']);
                if (!empty($field['placeholder'])) {
                    $element->setInputsAttribute('placeholder', $field['placeholder']);
                }
                return $element;
            case 'radio':
                return new Radio($key, $options, $value, $field['label'], $field['description']);
            default:
                return new Select($key, $options, $value, $field['label'], $field['description']);
        }
    }

    /**
     * 概览页运行状态
     *
     * @return array<int, array{label: string, value: string, on: bool}>
     */
    private static function statusItems(): array
    {
        $hljs = (string) Util::pluginOption('YumeHljs', 'off');
        $mirror = (string) Util::pluginOption('YumeJsdelivrMirror', 'local');

        return [
            self::status('编辑器', Util::pluginOption('YumeEditorEnabled', 'on') === 'on', '已启用', '使用原生输入框'),
            self::status('正文排版', Util::pluginOption('YumeEditorContentStyle', 'on') === 'on', '已开启', '已关闭'),
            self::status('围栏容器', Util::pluginOption('YumeFrontFenceStyle', 'on') === 'on', '已开启', '已关闭'),
            self::status('标签页', Util::pluginOption('YumeFrontFenceTabsStyle', 'on') === 'on', '已开启', '已关闭'),
            self::status('数学公式', Util::pluginOption('YumeMathJax', 'on') === 'on', 'MathJax', '已关闭'),
            self::status('Mermaid', Util::pluginOption('YumeMermaid', 'on') === 'on', '已开启', '已关闭'),
            [
                'label' => '代码高亮',
                'value' => $hljs === 'off' ? '跟随编辑器配色' : basename($hljs, '.css'),
                'on' => $hljs !== 'off',
            ],
            [
                'label' => '资源来源',
                'value' => $mirror === 'local' ? '本地镜像' : (parse_url($mirror, PHP_URL_HOST) ?: $mirror),
                'on' => $mirror === 'local',
            ],
        ];
    }

    /**
     * 构造一条状态
     *
     * @param string $label
     * @param bool $on
     * @param string $onText
     * @param string $offText
     * @return array{label: string, value: string, on: bool}
     */
    private static function status(string $label, bool $on, string $onText, string $offText): array
    {
        return ['label' => $label, 'value' => $on ? $onText : $offText, 'on' => $on];
    }

    /**
     * 是否已有设置备份
     *
     * @return bool
     */
    private static function hasBackup(): bool
    {
        $db = Db::get();
        return (bool) $db->fetchRow($db->select()->from('table.options')->where(
            'name = ?',
            self::backupOption()
        ));
    }

    /**
     * 备份数据在 options 表中的键名
     *
     * @return string
     */
    private static function backupOption(): string
    {
        return 'plugin:' . Util::NAME . 'Backup';
    }

    /**
     * 处理备份 / 还原 / 删除请求
     *
     * @return void
     * @throws Exception|Db\Exception
     */
    private static function handleBackupActions(): void
    {
        $request = Request::getInstance();
        $type = (string) $request->get('type');
        if (!in_array($type, ['backup', 'restore', 'delete'], true)) {
            return;
        }

        User::alloc()->pass('administrator');
        if (!$request->isPost()) {
            throw new Exception(_t('备份操作仅支持 POST 请求'), 405);
        }
        Security::alloc()->protect();

        $db = Db::get();
        $notice = Notice::alloc();
        $response = new WidgetResponse(Request::getInstance(), HttpResponse::getInstance());
        $backupName = self::backupOption();
        $currentRow = $db->fetchRow($db->select()->from('table.options')->where(
            'name = ?',
            'plugin:' . Util::NAME
        ));
        $backupRow = $db->fetchRow($db->select()->from('table.options')->where('name = ?', $backupName));

        switch ($type) {
            case 'backup':
                $currentValue = $currentRow['value'] ?? null;
                if ($currentValue === null) {
                    $notice->set(_t('还没有可备份的设置，请先保存一次配置。'), 'error');
                    break;
                }
                if ($backupRow) {
                    $db->query($db->update('table.options')
                        ->rows(['value' => $currentValue])
                        ->where('name = ?', $backupName));
                    $notice->set(_t('设置备份已更新。'), 'success');
                } else {
                    $db->query($db->insert('table.options')->rows([
                        'name' => $backupName,
                        'user' => '0',
                        'value' => $currentValue,
                    ]));
                    $notice->set(_t('设置备份完成。'), 'success');
                }
                break;

            case 'restore':
                if ($backupRow && ($backupRow['value'] ?? '') !== '') {
                    $db->query($db->update('table.options')
                        ->rows(['value' => $backupRow['value']])
                        ->where('name = ?', 'plugin:' . Util::NAME));
                    $notice->set(_t('已从备份还原设置。'), 'success');
                } else {
                    $notice->set(_t('没有可用的设置备份。'), 'error');
                }
                break;

            case 'delete':
                if ($backupRow) {
                    $db->query($db->delete('table.options')->where('name = ?', $backupName));
                    $notice->set(_t('设置备份已删除。'), 'success');
                } else {
                    $notice->set(_t('备份不存在，无需删除。'), 'error');
                }
                break;
        }

        $response->goBack();
    }
}
