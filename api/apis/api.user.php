<?php

class User {

	// Properties
	public $name;
	public $sess_key;
	public $privileges;
	
	public function __construct($name, $sess_key, $privileges) {
		$this->name = $name;
		$this->sess_key = $sess_key;
		$this->privileges = $privileges;
	}
	
	// Login
	public static function login($vars) {
	
		$retVal = false;
		$user = $vars['user'];
		$pass = $vars['pass'];
		
		db_Connect();
		$row = db_Fetch(db_Query('SELECT * FROM users WHERE user_name="' . db_Escape($user) . '" AND user_pass="' . db_Escape($pass) . '"'));
		if ($row) {
			$row->user_sess = md5($user . microtime(true));
			echo $row->user_sess;
			setcookie('sess_key', $row->user_sess, time() + 3600, '/admin/');
			db_Query('UPDATE users SET user_sess="' . $row->user_sess . '" WHERE user_name="' . $row->user_name . '"');
			$retVal = self::_populateUser($row);
		}
		
		return $retVal;
	
	}
	
	// Checks the session cookie against the database and populates stuff if necessary
	public static function getUserFromSession() {
		
		$retVal = false;
		$sess_key = isset($_COOKIE['sess_key']) ? $_COOKIE['sess_key'] : null;
		if ($sess_key) {
			db_Connect();
			$row = db_Fetch(db_Query('SELECT * FROM users WHERE user_sess="' . $sess_key . '"'));
			if ($row) {
				setcookie('sess_key', $row->user_sess, time() + 3600, '/admin/');
				$retVal = self::_populateUser($row);
			}
		}
		
		return $retVal;
		
	}
	
	// Populates user properties
	private static function _populateUser($user) {
		return new User($user->user_name, $user->user_sess, $user->user_privileges);
	}

}