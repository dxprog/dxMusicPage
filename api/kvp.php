<?php

/**
 * API Library for Getting/Setting key/value pairs
 */

namespace Api {

	use Lib;

	class KVP {
		
		/**
		 * KVP key
		 */
		public $key;
		
		/**
		 * KVP Value
		 */
		public $value;
		
		/**
		 * Constructor
		 * @param string $key Key to set for KVP
		 * @param mixed $value Value to set for KVP
		 */
		public function __construct($key, $value) {
			$this->key = $key;
			$this->value = $value;
		}
		
		/**
		 * Gets a KVP from the database
		 * @param string $key The key of the value to retrieve
		 * @return object Unserialized object
		 */
		public static function Get ($vars) {
			
			$retVal = null;

			// KVP access is only allowed from internal calls
			if (defined('API_LOCATION') && API_LOCATION == '_internal') {	
				$key = isset($vars['key']) ? strtolower($vars['key']) : null;
				if ($key) {
					$result = Lib\Db::Query('SELECT kvp_value FROM kvps WHERE kvp_key=:key', array(':key'=>$key));
					if ($result->count == 1) {
						$row = Lib\Db::Fetch($result);
						$retVal = unserialize($row->kvp_value);
					}
				}
			}
			
			return $retVal;
			
		}
		
		/**
		 * Gets all KVPs from the database
		 * @return array Returns an array of KVP objects
		 */
		public static function GetAll($vars) {
			
			// KVP access is only allowed from internal calls
			$retVal = null;
			if (defined('API_LOCATION') && API_LOCATION == '_internal') {
				$retVal = array();
				$result = Lib\Db::Query('SELECT * FROM kvps');
				while ($row = Lib\Db::Fetch($result)) {
					$retVal[] = new KVP($row->kvp_key, unserialize($row->kvp_value));
				}
			}
			return $retVal;
			
		}
		
		/**
		 * Sets a KVP in the database
		 * @param string $key The key of the object to create/update
		 * @param mixed $value Value to store
		 * @return bool Returns whether the set was successful
		 */
		public static function Set ($vars, $value) {
			
			db_Connect();
			$retVal = false;
			
			$key = isset($vars['key']) ? strtolower($vars['key']) : null;
			$value = $value != null ? serialize($value) : null;
			
			if (null !== $key && null !== $value) {
			
				// Check to see if the key already exists
				$result = Lib\Db::Query('SELECT kvp_key FROM kvps WHERE kvp_key=:key', array(':key'=>$key));
				$params = array(':key'=>$key, ':value'=>$value);
				if ($result->count == 1) {
					$query = 'UPDATE kvps SET kvp_value=:value WHERE kvp_key=:key';
				} else {
					$query = 'INSERT INTO kvps VALUES (:key, :value)';
				}
				$retVal = Lib\Db::Query($query, $params) != 0;
				
			}
			
			return $retVal;
			
		}

	}
}