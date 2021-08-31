<?php

namespace Darkish\Database;

use Darkish\Application;
use Darkish\Component;

class Db extends Component
{

    /**
     * Holds 
     */
    private static $_instances = [];
    private $_connection;
    private $_lastInsertId;
    private $_sets = [];
    private $_connectionString;

    public static function execute($callback)
    {
        if (is_callable($callback)) {
            $args = func_get_args();
            $args[0] = self::getInstance();
            return call_user_func_array($callback, $args);
        }
    }

    public static function getInstance($config = null)
    {
        $connectionString = [
            'host' => is_null($config) ? Application::getInstance()->getConfig('db.host') : $config['host'],
            'database' => is_null($config) ? Application::getInstance()->getConfig('db.database') : $config['database'],
            'user' => is_null($config) ? Application::getInstance()->getConfig('db.user') : $config['user'],
            'password' => is_null($config) ? Application::getInstance()->getConfig('db.password') : $config['password'],
            'prefix' => is_null($config) ? Application::getInstance()->getConfig('db.prefix') : $config['prefix'],
        ];
        $key = md5(serialize($connectionString));
        if (!isset(self::$_instances[$key])) {
            self::$_instances[$key] = new self($connectionString);
        }
        return self::$_instances[$key];
    }

    private function __construct($connectionString)
    {
        $this->_connectionString = $connectionString;
    }

    private function _connect()
    {
        $this->_connection = new \PDO('mysql:host=' . $this->_connectionString['host'] . ';dbname=' . $this->_connectionString['database'], $this->_connectionString['user'], $this->_connectionString['password']);
        $this->_connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->trigger('databaseConnected', $this->_connection);
        $this->debug('Database connection opened.');
    }

    private function _disconnect()
    {
        unset($this->_connection);
        $this->_connection = null;
        $this->_sets = [];
        $this->trigger('databaseDisconnected', $this->_connection);
        $this->debug('Database connection closed.');
    }

    public function newId()
    {
        return $this->_lastInsertId;
    }

    public function getError()
    {
        return $this->_connection ? [
            'code' => intval($this->_connection->errorCode()),
            'info' => $this->_connection->errorInfo(),
        ] : [
            'code' => -1,
            'info' => 'Database not connected!',
        ];
    }

    public function dispose()
    {
        self::$_instances[md5(serialize($this->_connectionString))] = null;
        unset(self::$_instances[md5(serialize($this->_connectionString))]);
    }

    public function __destruct()
    {
        $this->dispose();
    }

    public function __get($dbSetName)
    {
        if (!isset($this->_sets[$dbSetName])) {
            $this->_sets[$dbSetName] = new Set($dbSetName, $this);
        } else {
            $this->_sets[$dbSetName]->reset();
        }
        return $this->_sets[$dbSetName];
    }

    public function escape($str)
    {
        return $this->_connection->quote($str);
    }

    public function select($sql, $params = [], $name = null, $totalCount = null, $quantity = 10, $page = 1)
    {
        return strtolower(substr(trim($sql), 0, 7)) == 'select ' ? new Result($name, $this->query($sql, $params), $this, $totalCount, $quantity, $page) : null;
    }

    public function query($sql, $params = [])
    {
        $sql = $this->trigger('executingQuery', $sql, $params);
        $this->debug($sql, $params);
        $this->_connect();
        $result = null;
        if (empty($params)) {
            $statement = $this->_connection->query($sql);
            $this->trigger('queryExecuted', $sql, $statement);
            $result = strtolower(substr(trim($sql), 0, 7)) == 'select ' ? $statement->fetchAll(\PDO::FETCH_ASSOC) : $statement;
        } else {
            $statement = $this->_connection->prepare($sql);
            $statement->execute($params);
            $this->trigger('queryExecuted', $sql, $params, $statement);
            $result = strtolower(substr(trim($sql), 0, 7)) == 'select ' ? $statement->fetchAll(\PDO::FETCH_ASSOC) : $statement;
        }
        $lastInsertId = $this->_connection->lastInsertId();
        if ($lastInsertId > 0) {
            $this->_lastInsertId = $lastInsertId;
        }
        $this->_disconnect();
        return $result;
    }

    public function call($name, ...$args)
    {
        $placeHolders = [];
        $params = [];
        foreach($args as $i => $arg) {
            $placeHolders[] = ":arg$i";
            $params["arg$i"] = $arg;
        }
        $placeHolders = implode(', ', $placeHolders);
        return $this->query("CALL $name($placeHolders)", $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function __call($func, $args)
    {
        try {
            $query = $this->query("SHOW CREATE PROCEDURE `$func`");
        } catch(\Exception $ex) {
            return parent::__call($func, $args);
        }
        array_unshift($args, $func);
        return call_user_func_array([$this, 'call'], $args);
    }
}
