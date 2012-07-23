<?php

class Actions {

	private static $_userArrayCacheKey = 'DXMP_Users';

	/**
	 * Registers a user in the cache so that commands may be sent
	 */
	public static function registerUser() {
		
		if (isset($_SERVER['HTTP_USER_AGENT'])) {
		
			// Get the users array from cache
			$users = DxCache::Get(self::$_userArrayCacheKey);
			if (false === $users) {
				$users = array();
			}
			
			// Generate the ID for this user
			$id = self::_getUserId();
			
			// Iterate through all the users to see if this one is already registered
			$obj = false;
			foreach ($users as &$user) {
				if ($user->id === $id) {
					$obj = $user;
					$user->last_seen = time();
					break;
				}
			}
			
			// If the user object was blank, create a new one
			if (false === $obj) {
				$obj = new stdClass();
				$obj->id = $id;
				$obj->ip = $_SERVER['REMOTE_ADDR'];
				$obj->user_agent = $_SERVER['HTTP_USER_AGENT'];
				$obj->actions = array();
				$obj->last_seen = time();
				$users[] = $obj;
			}
			
			// Update the users array and save back to cache
			DxCache::Set(self::$_userArrayCacheKey, $users);
			return $obj;
			
		}
		
	}

	/**
	 * Sets an action for a user
	 */
	public static function setUserAction($vars) {
	
		$retVal = false;
	
		// Validate the ID
		if (preg_match('/[a-f0-9]{32}/', $vars['id']) && isset($vars['action'])) {
		
			$users = self::_getUserArray();
			
			// Find the user in question and add the action to the queue
			foreach ($users as &$user) {
				if ($user->id === $vars['id']) {
					$action = new stdClass();
					$action->name = $vars['action'];
					$action->param = isset($vars['param']) ? $vars['param'] : null;
					$action->timestamp = time();
					$user->actions[] = $action;
					$retVal = true;
					DxCache::Set(self::$_userArrayCacheKey, $users);
					break;
				}
			}
			
		}
		
		return $retVal;
	
	}
	
	/**
	 * Sends an action to all users except the current ont
	 */
	public static function setGlobalAction($vars) {
		
		$retVal = false;
		$id = self::_getUserId();
		
		if (isset($vars['action'])) {
			
			$users = self::_getUserArray();
			
			// Find the user in question and add the action to the queue
			foreach ($users as &$user) {
				if ($user->id !== $id) {
					$action = new stdClass();
					$action->name = $vars['action'];
					$action->param = isset($vars['param']) ? $vars['param'] : null;
					$action->timestamp = time();
					$user->actions[] = $action;
					$retVal = true;
				}
			}
			
			// Recache the data
			DxCache::Set(self::$_userArrayCacheKey, $users);
			
		}
		
	}
	
	/**
	 * Gets a list of waiting actions for the current user
	 */
	public static function getCurrentActions($vars) {
		
		$id = self::_getUserId();
		$retVal = false;
		$users = self::_getUserArray();
		foreach ($users as &$user) {
			if ($user->id === $id) {
				$retVal = $user->actions;
				$user->actions = array();
				break;
			}
		}
		
		// Update the cache
		DxCache::Set(self::$_userArrayCacheKey, $users);
		
		return $retVal;
		
	}
	
	/**
	 * Returns all the currently active users
	 */
	public static function getActiveUsers() {
		return self::_getUserArray();
	}
	
	/**
	 * Gets the cached users array or creates a new one if none exists
	 */
	private static function _getUserArray() {
		$retVal = DxCache::Get(self::$_userArrayCacheKey);
		return false === $retVal ? array() : $retVal;
	}
	
	private static function _getUserId() {
		return md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] . $_COOKIE['userName']);
	}

}

Actions::registerUser();