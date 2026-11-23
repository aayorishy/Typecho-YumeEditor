/**
 * 设置页前端行为：
 *   · 原生表单被移动进 MoePanel 风格的外壳；
 *   · on/off 下拉变成开关、枚举变成分段控件；
 *   · 侧栏 Tab 控制字段显隐，保存栏固定在底部。
 *
 * 同时校验源码与构建产物，避免 dist 与 src 脱节。
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { JSDOM } = require('jsdom');

const PHP = process.env.PHP_BINARY || 'php';
const fixture = execFileSync(PHP, [path.join(__dirname, 'fixture.php')], {
    encoding: 'utf8',
});

const sources = ['assets/src/config/config.js', 'assets/dist/js/config.js'];

function boot(relativeFile) {
    const dom = new JSDOM(fixture, {
        url: 'https://example.test/admin/options-plugin.php?config=YumeEditor',
        runScripts: 'outside-only',
        pretendToBeVisual: true,
    });
    const { window } = dom;
    window.eval(fs.readFileSync(path.join(__dirname, '..', relativeFile), 'utf8'));
    window.document.dispatchEvent(new window.Event('DOMContentLoaded', { bubbles: true }));
    return window;
}

for (const file of sources) {
    const window = boot(file);
    const { document } = window;

    // --- form relocation -------------------------------------------------
    const host = document.querySelector('#ym-form-host');
    assert.ok(host, `${file}: form host`);
    const form = host.querySelector('form');
    assert.ok(form, `${file}: native form moved into host`);
    assert.strictEqual(document.querySelectorAll('form form').length, 0, `${file}: no nested forms`);

    // --- field rows ------------------------------------------------------
    const fields = Array.from(document.querySelectorAll('.ym-field'));
    assert.strictEqual(fields.length, 12, `${file}: all settings rendered`);
    fields.forEach((field) => {
        assert.ok(field.querySelector(':scope > li.ym-field__inner'), `${file}: field row`);
        assert.ok(field.querySelector('.ym-field__meta'), `${file}: field meta`);
        assert.ok(field.querySelector('.ym-field__control'), `${file}: field control`);
    });

    // --- switch ----------------------------------------------------------
    const switches = Array.from(document.querySelectorAll('.ym-switch'));
    assert.strictEqual(switches.length, 7, `${file}: on/off selects become switches`);

    const enabledField = document.querySelector(
        '.ym-field[data-ym-section="editor"] select[name="YumeEditorEnabled"]'
    );
    assert.ok(enabledField, `${file}: editor switch select`);
    assert.ok(enabledField.classList.contains('ym-field__hidden'), `${file}: native select hidden`);
    const enabledSwitch = enabledField.closest('.ym-field').querySelector('.ym-switch');
    assert.strictEqual(enabledSwitch.getAttribute('aria-checked'), 'true', `${file}: switch starts on`);
    enabledSwitch.click();
    assert.strictEqual(enabledField.value, 'off', `${file}: switch writes the select value`);
    assert.strictEqual(enabledSwitch.getAttribute('aria-checked'), 'false', `${file}: switch updates state`);

    // --- segmented select ------------------------------------------------
    const fontAwesome = document.querySelector('select[name="YumeFontAwesome"]');
    const fontSegments = fontAwesome.closest('.ym-field').querySelectorAll('.ym-seg__btn');
    assert.strictEqual(fontSegments.length, 3, `${file}: tri-state control`);
    fontSegments[2].click();
    assert.strictEqual(fontAwesome.value, 'off', `${file}: segmented select writes value`);
    assert.ok(fontSegments[2].classList.contains('is-active'), `${file}: active segment`);

    // --- segmented radio -------------------------------------------------
    const radioGroup = document.querySelectorAll('input[name="YumeCleanDatabase"]');
    assert.strictEqual(radioGroup.length, 2, `${file}: radio group`);
    const radioSegments = radioGroup[0].closest('.ym-field').querySelectorAll('.ym-seg__btn');
    radioSegments[1].click();
    assert.strictEqual(radioGroup[1].checked, true, `${file}: segmented radio checked`);
    assert.strictEqual(radioGroup[0].checked, false, `${file}: other radio cleared`);

    // --- tabs ------------------------------------------------------------
    const navItems = Array.from(document.querySelectorAll('.ym-nav__item'));
    assert.strictEqual(navItems.length, 4, `${file}: four sections`);
    assert.strictEqual(document.querySelectorAll('.ym-panel.is-active').length, 1, `${file}: one active panel`);
    assert.strictEqual(
        document.querySelectorAll('form > .ym-field:not(.ym-field__hidden)').length,
        0,
        `${file}: overview hides the form`
    );
    assert.ok(
        document.querySelector('.ym-actions').classList.contains('ym-field__hidden'),
        `${file}: save bar hidden on overview`
    );

    navItems.find((item) => item.dataset.target === 'editor').click();
    const visible = Array.from(form.children).filter(
        (element) =>
            element.hasAttribute('data-ym-section') && !element.classList.contains('ym-field__hidden')
    );
    assert.ok(visible.length > 0, `${file}: editor section visible`);
    assert.ok(
        visible.every((element) => element.getAttribute('data-ym-section') === 'editor'),
        `${file}: other sections stay hidden`
    );
    assert.strictEqual(
        visible.filter((element) => element.classList.contains('ym-field')).length,
        3,
        `${file}: editor exposes three fields`
    );
    assert.ok(
        !document.querySelector('.ym-actions').classList.contains('ym-field__hidden'),
        `${file}: save bar visible on settings tabs`
    );
    assert.strictEqual(
        window.sessionStorage.getItem('yume-editor-active-section'),
        'editor',
        `${file}: remembers the active section`
    );

    // --- save bar --------------------------------------------------------
    assert.ok(document.querySelector('.ym-actions button[type="submit"]'), `${file}: submit button in bar`);
    assert.strictEqual(document.querySelectorAll('.typecho-option-submit').length, 0, `${file}: old row removed`);

    // --- backup actions --------------------------------------------------
    assert.strictEqual(document.querySelectorAll('.ym-backup__form').length, 3, `${file}: backup forms kept`);
    const destructive = document.querySelector('.ym-btn--confirm');
    assert.ok(destructive, `${file}: destructive action`);
    assert.ok(destructive.hasAttribute('data-confirm'), `${file}: destructive action asks first`);

    window.close();
}

const manifest = require('../assets/dist/mix-manifest.json');
assert.ok(
    /^\/js\/config\.js\?id=[a-f0-9]{32}$/.test(manifest['/js/config.js']),
    'built config bundle is fingerprinted'
);

console.log('configuration panel tests passed');
