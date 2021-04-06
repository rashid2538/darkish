<?php

namespace Darkish;

use Darkish\Application;
use Darkish\Helper;

abstract class Component
{

    protected static $_dependencies = [];
    protected static $_config = [];
    protected static $_eventHandlers = [];
    protected static $_callbacks = [];
    protected static $_route = [];

    function setDependency($name, $dependencyOrDependencyGetter)
    {
        self::$_dependencies[$name] = $dependencyOrDependencyGetter;
        return $this;
    }

    function setConfig($config)
    {
        self::$_config = is_string($config) && file_exists($config) ? parse_ini_file($config) : $config;
        $this->trigger('config_loaded');
        return $this;
    }

    function mergeConfig($newConfig)
    {
        self::$_config = array_merge(self::$_config, $newConfig);
        return $this;
    }

    function getConfig($key, $default = null)
    {
        return isset(self::$_config[$key]) ? self::$_config[$key] : $default;
    }

    function on($event, $handler)
    {
        if (is_callable($handler)) {
            if (!isset(self::$_eventHandlers[$event])) {
                self::$_eventHandlers[$event] = [];
            }
            self::$_eventHandlers[$event][] = $handler;
            return true;
        }
        throw new \Exception('2nd argument should be a callable!');
    }

    function trigger()
    {
        $args = func_get_args();
        $result = isset($args[1]) ? $args[1] : null;
        if (isset(self::$_eventHandlers[$args[0]]) && !empty(self::$_eventHandlers[$args[0]])) {
            $event = $args[0];
            unset($args[0]);
            $args = array_values($args);
            foreach (self::$_eventHandlers[$event] as $handler) {
                $resp = call_user_func_array($handler, $args);
                if (!is_null($resp)) {
                    $result = $resp;
                    if (!empty($args)) {
                        $args[0] = $result;
                    }
                }
                if ($result === false) {
                    break;
                }
            }
        }
        return $result;
    }

    function debug()
    {
        if ($this->getConfig('app.debug', false) == 'true' || isset($_GET['debug'])) {
            $args = func_get_args();
            array_unshift($args, date('Y-m-d H:i:s') . substr((string) microtime(), 1, 8));
            echo '<pre>';
            call_user_func_array('var_dump', $args);
            echo '</pre>';
        }
    }

    function getDependency($name)
    {
        if (isset(self::$_dependencies[$name])) {
            if (is_callable(self::$_dependencies[$name])) {
                self::$_dependencies[$name] = call_user_func(self::$_dependencies[$name]);
            }
            return self::$_dependencies[$name];
        }
        throw new \Exception('Unable to resolve dependency!');
    }

    function __get($name)
    {
        return $this->getDependency($name);
    }

    function getApplication()
    {
        return Application::getInstance();
    }

    function url($url = '', $asItIs = false)
    {
        if (empty($url)) {
            return $this->getHomePath();
        }
        if (!$asItIs) {
            $url = $this->trigger('makeUrl', $url);
        }
        $last = explode('/', $url);
        $last = end($last);
        $finalUrl = $this->trigger('urlMapping', $this->getHomePath() . $url . ((strpos($last, '.') !== false || $asItIs) ? '' : '/'));
        return $finalUrl;
    }

    protected function redirect($url, $asItIs = false)
    {
        header('Location: ' . $this->url($url, $asItIs), true, 301);
        $this->response->setStatusCode(301);
        $this->getApplication()->end();
    }

    protected function getHomePath()
    {
        // server protocol
        $protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';

        // domain name
        $domain = $_SERVER['SERVER_NAME'];

        // doc root
        $docRoot = str_replace(DIRECTORY_SEPARATOR, '/', preg_replace("!${_SERVER['SCRIPT_NAME']}$!", '', $_SERVER['SCRIPT_FILENAME']));

        // base url
        $baseUrl = $this->getConfig('app.default.base', '/');
        $baseUrl = ($baseUrl && $baseUrl != '/') ? "$baseUrl/" : '';

        // server port
        $port = $_SERVER['SERVER_PORT'];
        $disp_port = ($protocol == 'http' && $port == 80 || $protocol == 'https' && $port == 443) ? '' : ":$port";

        // put em all together to get the complete base URL
        return "${protocol}://${domain}${disp_port}/${baseUrl}";
    }

    protected function isAjaxRequest()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $this->getHomePath()) === 0;
    }

    function __isset($prop)
    {
        return isset(self::$_dependencies[$prop]) || isset(self::$_callbacks[$prop]);
    }

    function setCallback($name, $value)
    {
        if (is_callable($value)) {
            self::$_callbacks[$name] = $value;
        } else {
            throw new \Exception("Unable to set callback as it should be a callable!");
        }
        return $this;
    }

    function getMessages()
    {
        $this->startSession();
        $messages = isset($_SESSION['messages']) ? $_SESSION['messages'] : [];
        unset($_SESSION['messages']);
        return $messages;
    }

    function startSession() {
        if(session_status() === PHP_SESSION_DISABLED) {
            throw new \Exception('Sessions are disabled by server settings!');
        }
        if(session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
            session_regenerate_id();
        }
    }

    function dummy()
    {
        return new Dummy();
    }

    function setMessage($message, $type = 'info')
    {
        $this->startSession();
        $_SESSION['messages'][] = [
            'message' => $message,
            'type' => $type
        ];
        return $this;
    }

    function getUser()
    {
        try {
            $auth = $this->auth;
            return $auth ? $auth->getUser() : null;
        } catch (\Exception $ex) {
            return null;
        }
    }

    function getUserRoles()
    {
        try {
            $auth = $this->auth;
            return $auth ? $auth->getUserRoles() : null;
        } catch (\Exception $ex) {
            return null;
        }
    }

    function userHasRole($role)
    {
        return in_array($role, $this->getUserRoles());
    }

    function isValidCsrf()
    {
        $this->startSession();
        return $_SESSION['CSRF_TOKEN'] == $_POST['CSRF_TOKEN'];
    }

    function __call($func, $args)
    {
        if (empty($args) && substr($func, 0, 3) == 'get') {
            $prop = '_' . lcfirst(substr($func, 3));
            if (property_exists($this, $prop)) {
                return $this->$prop;
            }
        } else if (isset(self::$_callbacks[$func])) {
            return call_user_func_array(self::$_callbacks[$func], $args);
        }
        throw new \Exception("Call to undefined function `$func`!");
    }

    public function isAuthorized()
    {
        return !is_null($this->getUser());
    }

    function sanitize( $data, $properties ) {
        $result = [];
        foreach( $properties as $property ) {
            $result[ $property ] = isset( $data[ $property ] ) ? ( empty( $data[ $property ] ) ? null : $data[ $property ] ) : null;
        }
        return $result;
    }

    public function defineRoute()
    {
        global $argv;
        if (defined('STDOUT')) {
            $_SERVER['REQUEST_METHOD'] = 'CLI';
        }
        $url = trim(explode('?', defined('STDOUT') ? (isset($argv[1]) ? $argv[1] : '') : $_SERVER['REQUEST_URI'], 2)[0], '/');
        if ($this->getConfig('app.default.base', '')) {
            $url = str_replace([$this->getConfig('app.default.base', '') . '/', $this->getConfig('app.default.base', '')], '', $url);
        }
        $url = explode('/', trim($this->trigger('beforeRouting', $url), '/'));

        self::$_route['controller'] = empty($url[0]) ? $this->getConfig('app.default.controller', 'home') : Helper::slugToCamel($url[0]);
        self::$_route['action'] = isset($url[1]) ? Helper::slugToCamel($url[1]) : $this->getConfig('app.default.action', 'main');
        unset($url[0], $url[1]);
        $this->debug('route', self::$_route);
        self::$_route['params'] = array_map('urldecode', $url);
        self::$_route = $this->trigger('afterRouting', self::$_route);
    }
}
