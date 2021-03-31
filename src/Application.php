<?php

namespace Darkish;

use Darkish\Database\Db;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Application extends Component
{

    private static $_instance;
    private $_requestStartTime;

    private function __construct()
    {
        $this->_requestStartTime = microtime(true);
        $this->trigger('start');
        $this->setDependency('db', function () {
            return Db::getInstance();
        });
        $this->setDependency('request', function () {
            return Request::createFromGlobals();
        });
        $this->setDependency('response', function () {
            return new Response(
                'Content',
                Response::HTTP_OK,
                ['content-type' => 'text/html']
            );
        });
        $this->on('config_loaded', function () {
            $this->loadPlugins();
            $this->defineRoute();
        });
        register_shutdown_function([$this, 'checkError']);
    }

    function checkError()
    {
        $lastError = error_get_last();
        if ($lastError) $this->debug($lastError);
    }

    public static function getInstance()
    {
        if (!defined('APP_PATH')) {
            throw new \Exception('Call to app initialization without setting APP_PATH!');
        }
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function run()
    {
        $controllerClass = $this->getConfig('app.default.namespace', 'App\\') . 'Controller\\' . ucfirst(parent::$_route['controller']);
        $controller = null;
        if (class_exists($controllerClass)) {
            $controller = new $controllerClass(parent::$_route['controller'], parent::$_route['action']);
        } else {
            $errorControllerClass = $this->getConfig('app.default.namespace') . 'Controller\\' . ucfirst($this->getConfig('app.default.errorContrller', 'error'));
            if (class_exists($errorControllerClass)) {
                parent::$_route['controller'] = $this->getConfig('app.default.errorController', 'error');
                parent::$_route['action'] = $this->getConfig('app.default.action', 'main');
                $controller = new $errorControllerClass(parent::$_route['controller'], parent::$_route['action']);
            } else {
                throw new \Exception('Unable to find the controller!');
            }
        }
        $this->debug($controller);

        if (strtolower($_SERVER['REQUEST_METHOD']) != 'get' && method_exists($controller, parent::$_route['action'] . ucfirst($_SERVER['REQUEST_METHOD']))) {
            parent::$_route['params'][] = $_REQUEST;
            $this->end(call_user_func_array([$controller, parent::$_route['action'] . ucfirst($_SERVER['REQUEST_METHOD'])], parent::$_route['params']));
        } else if (method_exists($controller, parent::$_route['action'])) {
            $this->end(call_user_func_array([$controller, parent::$_route['action']], parent::$_route['params']));
        } else {
            header('HTTP/1.0 404 Not Found', true, 404);
            $errorControllerClass = $this->getConfig('app.default.namespace') . 'Controller\\' . ucfirst($this->getConfig('app.default.errorController', 'error'));
            if (class_exists($errorControllerClass)) {
                parent::$_route['controller'] = $this->getConfig('app.default.errorController', 'error');
                parent::$_route['action'] = $this->getConfig('app.default.action', 'main');
                $controller = new $errorControllerClass(parent::$_route['controller'], parent::$_route['action']);
                if (method_exists($controller, parent::$_route['action'])) {
                    $this->end(call_user_func_array([$controller, parent::$_route['action']], ['Unable to find the route!']));
                }
            }
        }
        throw new \Exception('Unable to find the error controller to show action not found error!');
    }

    public function setAuthProvider(IAuth $auth)
    {
        $this->setDependency('auth', $auth);
        return $this;
    }

    function loadPlugins()
    {
        $plugins = explode(',', $this->getConfig('app.plugins'));
        if (!empty($plugins)) {
            foreach ($plugins as $pluginClass) {
                if (class_exists($pluginClass)) $this->on('pluginsLoaded', [new $pluginClass(), 'activate']);
            }
        }
        $this->trigger('pluginsLoaded');
    }

    function end($response = '')
    {
        $this->response->prepare($this->request);
        $this->response->setCharset('UTF-8');
        $response = $this->trigger('end', $response, $this->_requestStartTime);
        $this->response->setContent($response);
        $this->response->send();
        die;
    }
}
