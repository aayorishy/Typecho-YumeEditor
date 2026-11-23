<?php

/**
 * 设置备份接口的安全契约：
 *   · 只接受带 CSRF token 的 POST；
 *   · 校验管理员权限与来源页面；
 *   · 只有在合法请求下才写库。
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

$request = new Typecho\Request();
$url = $request->url;
$token = (new Widget\Security())->getToken($url);

foreach (['backup', 'restore', 'delete'] as $type) {
    foreach ([['GET', $token], ['GET', null], ['POST', null], ['POST', 'wrong-token']] as $invalid) {
        $result = ym_run_config($invalid[0], $type, $invalid[1]);
        ym_check(empty($result[0]->writes), $type . ' rejects writes for invalid request ' . $invalid[0]);
        ym_check($result[3] !== null, $type . ' rejects invalid request ' . $invalid[0]);
    }

    $result = ym_run_config('POST', $type, $token);
    ym_check(count($result[0]->writes) === 1, $type . ' accepts one protected POST');
    ym_check($result[3] instanceof YumeEditorRedirect, $type . ' redirects after success');

    if ($type === 'backup') {
        ym_check($result[0]->options['plugin:YumeEditorBackup'] === 'current-options', 'backup stores current settings');
    }
    if ($type === 'restore') {
        ym_check($result[0]->options['plugin:YumeEditor'] === 'backup-options', 'restore writes backup into settings');
    }
    if ($type === 'delete') {
        ym_check(!isset($result[0]->options['plugin:YumeEditorBackup']), 'delete removes backup');
    }
}

$fresh = ym_run_config('POST', 'backup', $token, false);
ym_check($fresh[0]->writes === ['insert'], 'missing backup is created with insert');
ym_check($fresh[0]->options['plugin:YumeEditorBackup'] === 'current-options', 'created backup holds settings');

$guest = runAsGuest(static function () use ($token) {
    return ym_run_config('POST', 'backup', $token);
});
ym_check(empty($guest[0]->writes) && $guest[3] !== null, 'non-administrator cannot change backups');

$get = ym_run_config('GET');
ym_check(empty($get[0]->writes) && $get[3] === null, 'rendering the settings page does not write');

/**
 * 以非管理员身份执行回调。
 *
 * @param callable $callback
 * @return array
 */
function runAsGuest(callable $callback): array
{
    Widget\User::$admin = false;
    try {
        return $callback();
    } finally {
        Widget\User::$admin = true;
    }
}

echo "backup CSRF tests passed\n";
