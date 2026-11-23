<?php

/**
 * 测试宿主
 *
 * 用内存中的最小 Typecho 实现验证插件逻辑，不连接真实站点、数据库或模板。
 * 各测试脚本在定义好 `__TYPECHO_ROOT_DIR__` 后 require 本文件即可。
 */

namespace Typecho\Plugin {
    interface PluginInterface
    {
    }

    class Exception extends \RuntimeException
    {
    }
}

namespace Typecho {
    class Config
    {
        public function __get($name)
        {
            return null;
        }
    }

    class Plugin
    {
        public static function parseInfo($file)
        {
            return ['version' => '1.0.0'];
        }
    }

    class Common
    {
        public static function url($path, $base)
        {
            return rtrim((string) $base, '/') . '/' . ltrim((string) $path, '/');
        }

        public static function fixHtml($html)
        {
            return $html;
        }

        public static function subStr($text, $start, $length, $trim = '...')
        {
            return $text;
        }
    }

    class Request
    {
        public static $instance;
        public $values = [];
        public $method = 'GET';
        public $url = 'https://example.test/admin/options-plugin.php?config=YumeEditor';

        public static function getInstance()
        {
            return self::$instance;
        }

        public function get($name, $default = null, &$exists = null)
        {
            return $this->values[$name] ?? $default;
        }

        public function isPost()
        {
            return $this->method === 'POST';
        }

        public function getRequestUrl()
        {
            return $this->url;
        }

        public function getReferer()
        {
            return $this->url;
        }
    }

    class Response
    {
        public static function getInstance()
        {
            return new self();
        }

        public function goBack($suffix = null, $default = null)
        {
        }

        public function redirect($location)
        {
        }
    }

    class Query
    {
        public $operation;
        public $name;
        public $values = [];
        public $conditions = [];

        public function __construct($operation)
        {
            $this->operation = $operation;
        }

        public function from($table)
        {
            return $this;
        }

        public function where($condition, $name = null)
        {
            $this->conditions[] = [$condition, $name];
            $this->name = $name;
            return $this;
        }

        public function rows($values)
        {
            $this->values = $values;
            return $this;
        }
    }

    class Db
    {
        public static $instance;
        public $options = [];
        public $writes = [];

        public static function get()
        {
            return self::$instance;
        }

        public function select()
        {
            return new Query('select');
        }

        public function update($table)
        {
            return new Query('update');
        }

        public function insert($table)
        {
            return new Query('insert');
        }

        public function delete($table)
        {
            return new Query('delete');
        }

        public function fetchRow($query)
        {
            return isset($this->options[$query->name])
                ? ['name' => $query->name, 'value' => $this->options[$query->name]]
                : [];
        }

        public function query($query)
        {
            $this->writes[] = $query->operation;
            $name = $query->operation === 'insert' ? $query->values['name'] : $query->name;
            if ($query->operation === 'delete') {
                unset($this->options[$name]);
                return;
            }
            $this->options[$name] = $query->values['value'];
        }
    }
}

namespace Typecho\Widget {
    class Request
    {
        private $request;

        public function __construct($request, $params = null)
        {
            $this->request = $request;
        }

        public function __get($name)
        {
            return $this->request->get($name);
        }

        public function isPost()
        {
            return $this->request->isPost();
        }

        public function getRequestUrl()
        {
            return $this->request->getRequestUrl();
        }
    }

    class Response
    {
        public function __construct($request, $response)
        {
        }

        public function goBack($suffix = null, $default = null)
        {
            throw new \YumeEditorRedirect();
        }
    }
}

namespace Typecho\Widget\Helper {
    class Layout
    {
        public $tag = 'div';
        public $attributes = [];
        public $content = '';
        public $items = [];

        public function __construct($tag = 'div', $attributes = [])
        {
            $this->tag = $tag;
            $this->attributes = $attributes;
        }

        public function setTagName($name)
        {
            $this->tag = $name;
            return $this;
        }

        public function setAttribute($name, $value)
        {
            $this->attributes[$name] = $value;
            return $this;
        }

        public function html($html)
        {
            $this->content .= $html;
            return $this;
        }

        public function addItem($item)
        {
            $this->items[] = $item;
            return $item;
        }

        public function render()
        {
            $attributes = '';
            foreach ($this->attributes as $name => $value) {
                $attributes .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
            }
            $inner = $this->content;
            foreach ($this->items as $item) {
                $inner .= $item instanceof self ? $item->render() : (string) $item;
            }
            return '<' . $this->tag . $attributes . '>' . $inner . '</' . $this->tag . '>';
        }
    }

    class Form extends Layout
    {
        public $action = '/action/plugins-edit?config=YumeEditor';
        public $inputs = [];
        public $items = [];

        public function __construct($method = 'post', $action = null)
        {
            parent::__construct('form', ['method' => 'post', 'id' => 'normal-config-form']);
            if ($action) {
                $this->action = $action;
            }
        }

        public function addItem($item)
        {
            $this->items[] = $item;
            return $this;
        }

        public function addInput($input)
        {
            $this->inputs[$input->name] = $input;
            $this->addItem($input);
            return $this;
        }

        public function getInput($name)
        {
            return $this->inputs[$name] ?? null;
        }

        public function getAllRequest()
        {
            $values = [];
            foreach ($this->inputs as $name => $input) {
                $values[$name] = \Typecho\Request::getInstance()->get($name);
            }
            return $values;
        }

        public function render()
        {
            echo '<form id="normal-config-form" method="post" action="' . $this->action . '">';
            foreach ($this->items as $item) {
                if ($item instanceof Layout) {
                    echo $item->render();
                } elseif ($item instanceof \Typecho\Widget\Helper\Form\Element\Element) {
                    echo $item->render();
                } else {
                    echo (string) $item;
                }
            }
            echo '<ul class="typecho-option typecho-option-submit"><li><button type="submit">保存设置</button></li></ul>';
            echo '</form>';
        }
    }
}

namespace Typecho\Widget\Helper\Form\Element {
    use Typecho\Widget\Helper\Layout;

    abstract class Element
    {
        public $name;
        public $options = [];
        public $value;
        public $label;
        public $description;
        public $attributes = [];
        public $inputAttributes = [];

        public function __construct(
            $name = null,
            $options = null,
            $value = null,
            $label = null,
            $description = null
        ) {
            $this->name = $name;
            $this->options = is_array($options) ? $options : [];
            $this->value = $value;
            $this->label = $label;
            $this->description = $description;
        }

        public function setAttribute($name, $value)
        {
            $this->attributes[$name] = $value;
            return $this;
        }

        public function multiMode()
        {
            return $this;
        }

        public function setInputsAttribute($name, $value)
        {
            $this->inputAttributes[$name] = $value;
        }

        abstract protected function renderControl();

        public function render()
        {
            $class = $this->attributes['class'] ?? 'typecho-option';
            $extra = '';
            foreach ($this->attributes as $name => $value) {
                if ($name === 'class') {
                    continue;
                }
                $extra .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
            }

            $control = $this->renderControl();
            $label = $this->label === null
                ? ''
                : '<label class="typecho-label">' . $this->label . '</label>';
            $description = $this->description === null
                ? ''
                : '<p class="description">' . $this->description . '</p>';

            return '<ul class="' . $class . '"' . $extra . '><li>'
                . $label . $control . $description
                . '</li></ul>';
        }
    }

    class Select extends Element
    {
        protected function renderControl()
        {
            $html = '<select name="' . $this->name . '" id="' . $this->name . '-0-1">';
            foreach ($this->options as $value => $label) {
                $selected = (string) $value === (string) $this->value ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"'
                    . $selected . '>' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            return $html . '</select>';
        }
    }

    class Text extends Element
    {
        protected function renderControl()
        {
            $placeholder = isset($this->inputAttributes['placeholder'])
                ? ' placeholder="' . htmlspecialchars((string) $this->inputAttributes['placeholder'], ENT_QUOTES, 'UTF-8') . '"'
                : '';
            return '<input type="text" name="' . $this->name . '" value="'
                . htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8') . '"' . $placeholder . '>';
        }
    }

    class Radio extends Element
    {
        protected function renderControl()
        {
            $html = '';
            foreach ($this->options as $value => $label) {
                $id = $this->name . '-' . $value;
                $checked = (string) $value === (string) $this->value ? ' checked' : '';
                $html .= '<span><input type="radio" name="' . $this->name . '" id="' . $id
                    . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"' . $checked
                    . '><label for="' . $id . '">'
                    . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</label></span>';
            }
            return $html;
        }
    }
}

namespace Utils {
    class Helper
    {
        public static $secret = 'test-only-site-secret';
        public static $options;

        public static function options()
        {
            return self::$options;
        }

        public static function addAction($name, $class)
        {
        }

        public static function removeAction($name)
        {
        }
    }
}

namespace Widget {
    class Notice
    {
        public $messages = [];

        public static function alloc()
        {
            return new self();
        }

        public function set($message, $status)
        {
            $this->messages[] = [$status, $message];
        }
    }

    class User
    {
        public static $admin = true;
        public static $loggedIn = false;
        public $uid = 7;

        public static function alloc()
        {
            return new self();
        }

        public function pass($role, $return = false)
        {
            if (self::$admin) {
                return true;
            }
            if ($return) {
                return false;
            }
            throw new \RuntimeException('Administrator required');
        }

        public function hasLogin()
        {
            return self::$loggedIn;
        }
    }

    class Security
    {
        public static function alloc()
        {
            return new self();
        }

        public function getToken($suffix = null)
        {
            return md5('test-fixture&' . $suffix);
        }

        public function protect()
        {
            $request = \Typecho\Request::getInstance();
            if ($request->get('_') !== $this->getToken($request->getReferer())) {
                throw new \YumeEditorRedirect();
            }
        }
    }
}

namespace TypechoPlugin\YumeEditor {
    /**
     * 只用于测试的 Util 替身：Config 只需要这几个纯函数。
     */
    class Util
    {
        public const NAME = 'YumeEditor';

        public static $options = [
            'YumeEditorEnabled' => 'on',
            'YumeInsertAllImages' => 'on',
            'YumeEditorContentStyle' => 'on',
            'YumeFrontFenceStyle' => 'on',
            'YumeFrontFenceTabsStyle' => 'on',
            'YumeMathJax' => 'on',
            'YumeMermaid' => 'on',
            'YumeFontAwesome' => 'auto',
            'YumeHljs' => 'github.css',
            'YumeJsdelivrMirror' => 'local',
            'YumeCleanDatabase' => 'none',
        ];

        public static function collectManifest()
        {
        }

        public static function pluginStatic($type, $file)
        {
            return 'https://example.test/usr/plugins/YumeEditor/assets/dist/' . $type . '/' . $file;
        }

        public static function pluginDir($uri = '')
        {
            return dirname(__DIR__) . '/' . ltrim($uri, '/');
        }

        public static function listHljsCss()
        {
            return ['github.css' => 'github', 'base16/nord.css' => 'nord'];
        }

        public static function pluginOption($key, $default = null)
        {
            return self::$options[$key] ?? $default;
        }
    }
}

namespace {
    class YumeEditorRedirect extends RuntimeException
    {
    }

    function _t($message, ...$args)
    {
        return $args ? vsprintf($message, $args) : $message;
    }

    function _e($message)
    {
        echo $message;
    }

    function ym_check($condition, $name)
    {
        if (!$condition) {
            throw new RuntimeException('Failed: ' . $name);
        }
    }

    /**
     * 构造一次插件配置请求。
     *
     * @return array{0: \Typecho\Db, 1: \Typecho\Widget\Helper\Form, 2: string, 3: \Throwable|null}
     */
    function ym_run_config($method = 'GET', $type = null, $token = null, $hasBackup = true)
    {
        $request = new \Typecho\Request();
        $request->method = $method;
        $request->values = ['type' => $type, '_' => $token];
        \Typecho\Request::$instance = $request;

        $db = new \Typecho\Db();
        $db->options = ['plugin:YumeEditor' => 'current-options'];
        if ($hasBackup) {
            $db->options['plugin:YumeEditorBackup'] = 'backup-options';
        }
        \Typecho\Db::$instance = $db;

        $form = new \Typecho\Widget\Helper\Form();
        $exception = null;
        ob_start();
        try {
            \TypechoPlugin\YumeEditor\Plugin::config($form);
            $form->render();
        } catch (\Throwable $error) {
            $exception = $error;
        }
        $html = ob_get_clean();

        return [$db, $form, $html, $exception];
    }
}
