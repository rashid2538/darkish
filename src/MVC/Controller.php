<?php

    namespace Darkish\MVC;
    
	use Darkish\Component;
	use Symfony\Component\HttpFoundation\Response;

	abstract class Controller extends Component {

		protected $_layout;
		protected $_name;
		protected $_viewBag;
		protected $_authorize = false;
		protected $_roles = [];
		protected $_assets = [ 'css' => [], 'js' => [] ];
		protected $_title = '';
		protected $_action;
		public $path;
		public $model;
		public $template;
		public $html;

		function title() {
			if(empty($this->_title)) {
				$class = explode( '\\', get_class( $this ) );
				$this->_title = end( $class ) . ' ' . $this->_action;
			}
			return $this->_title;
		}

		protected function beforeExecute() {}
		protected function beforeRender() {}

		function __construct( $name, $action ) {
			$this->_name = $name;
			$this->_action = $action;
			$this->_viewBag = new \StdClass();
			$this->path = $this->getApplication()->getConfig( 'app.view.directory', 'views/' );
			if( $this->_authorize ) {
				$this->debug( 'checking authorization', $this->getApplication()->isAuthorized() );
				if( !$this->getApplication()->isAuthorized() ) {
					$this->redirect( $this->getApplication()->getConfig( 'app.loginPath', 'account/login' ) . '?next=' . urlencode( $_SERVER[ 'REQUEST_URI' ] ), true );
				} else if( !empty( $this->_roles ) ) {
					if( empty( array_intersect( $this->_roles, $this->getUserRoles() ) ) ) {
						$this->redirect( $this->getApplication()->getConfig( 'app.loginPath', 'account/login' ) );
					}
				}
			}
			$this->beforeExecute();
		}

		function __set( $prop, $val ) {
			$this->_viewBag->$prop = $val;
		}

		protected function view( $model = null, $options = [] ) {
			if( isset( $options[ 'view' ] ) ) {
				$this->_action = $options[ 'view' ];
			}
			$this->_action = strtolower( $this->_action );
			$this->template = $this->getApplication()->getConfig( 'app.view.directory', 'views/' ) . $this->_name . '/' . $this->_action . '.' . $this->getConfig( 'app.view.extension', 'html' );
			$this->model = $model;
			$this->html = new HTML( $model );
			$renderer = $this->trigger( 'getRenderer' );
			$this->beforeRender();
			return $renderer && is_a( $renderer, '\\Closure' ) ? \Closure::bind( $renderer, $this )->__invoke() : $this->_render();
		}

		private function _render() {
			ob_start();
			$this->template = $this->getApplication()->getConfig( 'app.view.directory', 'views/' ) . $this->_name . '/' . $this->_action . '.' . $this->getConfig( 'app.view.extension', 'html' );
			include $this->_layout ? $this->getApplication()->getConfig( 'app.view.directory', 'views/' ) . $this->_layout . '.' . $this->getConfig( 'app.view.extension', 'html' ) : $this->getApplication()->getConfig( 'app.view.directory', 'views/' ) . $this->_name . '/' . $this->_action . '.' . $this->getConfig( 'app.view.extension', 'html' );
			$page = ob_get_contents();
			ob_end_clean();
			return $page;
		}

		// 200
		protected function ok( $response ) {
			return $response;
		}

		// 200
		protected function json( $response ) {
			$this->response->headers->set('Content-Type', 'application/json');
			return is_string( $response ) ? $response : json_encode( $response );
		}

		// 500
		protected function internalError( $ex = null ) {
			$this->response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
			if( $ex ) {
				return $ex->getMessage() . "\n" . $ex->getTraceAsString();
			}
			return '500 Internal Server Error';
		}

		// 403
		protected function unauthorized( $ex = null ) {
			$this->response->setStatusCode(Response::HTTP_UNAUTHORIZED);
			if( $ex ) {
				return $ex->getMessage() . "\n" . $ex->getTraceAsString();
			}
			return '403 Unauthorized';
		}

		// 404
		protected function notFound( $resp = '' ) {
			$this->response->setStatusCode(Response::HTTP_NOT_FOUND);
			return $resp;
		}

		protected function htmlCss( $file, $position = null ) {
			is_null( $position ) ? array_push( $this->_assets[ 'css' ], $file ) : array_splice( $this->_assets[ 'css' ], $position, 0, $file );
		}

		protected function htmlJs( $file, $position = null ) {
			is_null( $position ) ? array_push( $this->_assets[ 'js' ], $file ) : array_splice( $this->_assets[ 'js' ], $position, 0, $file );
		}

		function renderCss() {
			$result = [];
			$base = $this->getConfig( 'app.default.base', '' );
			$base = $base ? "/$base" : '';
			foreach( $this->_assets[ 'css' ] as $styleSheet ) {
				if(substr($styleSheet,0,4)=='http' ||substr($styleSheet,0,4)=='//') {
					$result[] = '<link rel="stylesheet" href="' . $styleSheet . '" />';
				} else {
					$filePath = rtrim(rtrim($_SERVER['DOCUMENT_ROOT'],'/'),'\\') . $base . '/assets/' . $styleSheet . '.css';
					if(file_exists($filePath)) {
						$result[] = '<link rel="stylesheet" href="' . $this->url() . 'assets/' . $styleSheet . '.css?_h=' . md5_file($filePath) . '" />';
					} else {
						$result[] = "<!-- CSS file '$filePath' not found! -->";
					}
				}
			}
			return implode( "\n\t\t", $result );
		}

		function renderJs() {
			$result = [];
			$base = $this->getConfig( 'app.default.base', '' );
			$base = $base ? "/$base" : '';
			foreach( $this->_assets[ 'js' ] as $script ) {
				if(substr($script,0,4)=='http' ||substr($script,0,4)=='//') {
					$result[] = '<script src="' . $script . '"></script>';
				} else {
					$filePath = rtrim(rtrim($_SERVER['DOCUMENT_ROOT'],'/'),'\\') . $base . '/assets/' . $script . '.js';
					if( file_exists( $filePath ) ) {
						$result[] = '<script src="' . $this->url() . 'assets/' . $script . '.js?_h=' . md5_file($filePath) . '"></script>';
					} else {
						$result[] = "<!-- JS file '$filePath' not found! -->";
					}
				}
			}
			return implode( "\n\t\t", $result );
		}

		function csrf( $justToken = false ) {
			$_SESSION[ 'CSRF_TOKEN' ] = substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' ), 0, 20 );
			return $justToken ? $_SESSION[ 'CSRF_TOKEN' ] : '<input type="hidden" name="CSRF_TOKEN" value="' . $_SESSION[ 'CSRF_TOKEN' ] . '" />';
		}
	}