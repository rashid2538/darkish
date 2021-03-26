<?php

    namespace Darkish\Database;

    use Darkish\Component;
    use Darkish\Application;

    class Db extends Component {

		private static $_instances = [];
		private $_connection;
		private $_lastInsertId;
		private $_sets = [];
		private $_connectionString;

		static function execute( $callback ) {
			if( is_callable( $callback ) ) {
				$args = func_get_args();
				$args[ 0 ] = self::getInstance();
				return call_user_func_array( $callback, $args );
			}
		}

		static function getInstance( $config = null ) {
			$connectionString = [
				'host' => is_null( $config ) ? Application::getInstance()->getConfig('db.host') : $config[ 'host' ],
				'database' => is_null( $config ) ? Application::getInstance()->getConfig('db.database') : $config[ 'database' ],
				'user' => is_null( $config ) ? Application::getInstance()->getConfig('db.user') : $config[ 'user' ],
				'password' => is_null( $config ) ? Application::getInstance()->getConfig('db.password') : $config[ 'password' ],
				'prefix' => is_null( $config ) ? Application::getInstance()->getConfig('db.prefix') : $config[ 'prefix' ]
			];
			$key = md5( serialize( $connectionString ) );
			if( !isset( self::$_instances[ $key ] ) ) {
				self::$_instances[ $key ] = new self( $connectionString );
			}
			return self::$_instances[ $key ];
		}

		private function __construct( $connectionString ) {
			$this->_connectionString = $connectionString;
		}

		private function _connect() {
			$this->_connection = new \PDO( 'mysql:host=' . $this->_connectionString[ 'host' ] . ';dbname=' . $this->_connectionString[ 'database' ], $this->_connectionString[ 'user' ], $this->_connectionString[ 'password' ] );
			$this->_connection->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
			$this->trigger( 'databaseConnected', $this->_connection );
			$this->debug( 'Database connection opened.' );
		}

		private function _disconnect() {
			unset( $this->_connection );
			$this->_connection = null;
			$this->_sets = [];
			$this->trigger( 'databaseDisconnected', $this->_connection );
			$this->debug( 'Database connection closed.' );
		}

		function newId() {
			return $this->_lastInsertId;
		}

		function getError() {
			return $this->_connection ? [
				'code' => intval( $this->_connection->errorCode() ),
				'info' => $this->_connection->errorInfo()
			] : [
				'code' => -1,
				'info' => 'Database not connected!'
			];
		}

		function dispose() {
			self::$_instances[ md5( serialize( $this->_connectionString ) ) ] = null;
		}

		function beat( $fromSelf = false ) {
			if( $this->_connection === null ) {
				$key = md5( serialize( $this->_connectionString ) );
				if( $fromSelf ) {
					$this->_connect();
				} else if( self::$_instances[ $key ]->getConnection() == null ) {
					self::$_instances[ $key ]->beat( true );
					$this->_connection = self::$_instances[ $key ]->getConnection();
				}
			}
			return $this;
		}

		function __destruct() {
			$this->dispose();
		}

		function __get( $dbSetName ) {
			if( !isset( $this->_sets[ $dbSetName ] ) ) {
				$this->_sets[ $dbSetName ] = new Set( $dbSetName, $this );
			}
			return $this->_sets[ $dbSetName ];
		}

		function escape( $str ) {
			return $this->_connection->quote( $str );
		}

		function select( $sql, $params = [], $name = null, $totalCount, $quantity, $page ) {
			return strtolower( substr( trim( $sql ), 0, 7 ) ) == 'select ' ? new Result( $name, $this->query( $sql, $params ), $this, $totalCount, $quantity, $page ) : null;
		}

		function query( $sql, $params = [] ) {
			$sql = $this->trigger( 'executingQuery', $sql, $params );
			$this->debug( $sql, $params );
			$this->_connect();
			$result = null;
			if( empty( $params ) ) {
				$statement = $this->_connection->query( $sql );
				$this->trigger( 'queryExecuted', $sql, $params, $statement );
				$result = strtolower( substr( trim( $sql ), 0, 7 ) ) == 'select ' ? $statement->fetchAll( \PDO::FETCH_ASSOC ) : $statement;
			} else {
				$statement = $this->_connection->prepare( $sql );
				$statement->execute( $params );
				$this->trigger( 'queryExecuted', $sql, $params, $statement );
				$result = strtolower( substr( trim( $sql ), 0, 7 ) ) == 'select ' ? $statement->fetchAll( \PDO::FETCH_ASSOC ) : $statement;
			}
			$lastInsertId = $this->_connection->lastInsertId();
			if( $lastInsertId > 0 ) {
				$this->_lastInsertId = $lastInsertId;
			}
			$this->_disconnect();
			return $result;
		}
	}