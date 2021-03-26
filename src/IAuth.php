<?php

	namespace Darkish;

	interface IAuth {
		public function getUser();
		public function getUserRoles();
		public function login( $data );
		public function logout();
	}