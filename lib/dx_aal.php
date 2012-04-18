<?php

/**
 * DX API Abstraction Layer
 */

define('API_LOCATION', '_internal');

/**
 * Path to the API libraries
 * @global $GLOBALS['_apiPath']
 * @name $_apiPath
 */
$GLOBALS['_apiPath'] = './api';

/**
 * Number of times the API layer was hit
 * @global $GLOBALS['_apiHits']
 * @name $_apiHits
 */
$GLOBALS['_apiHits'] = 0;

// Include the DX API libraries
require_once('./api/dx_api.php');

class Dx {

	/**
	 * Makes an internal or external API call based upon whether an API url was passed
	 */
	public static function call($module, $method, $params = null, $cache = 600, $apiUri = null) {		
		$retVal = null;
		if ($apiUri === null) {
			$retVal = self::_internal($module, $method, $params, $cache);
		} else {
			$retVal = self::_external($module, $method, $params, $cache, $apiUri);
		}
		return $retVal;
	}
	
	/**
	 * Makes a POST request to the API
	 */
	public static function post($module, $method, $params, $object) {
		$retVal = DxApi::handleRequest($module, $method, $params, $object);
		return $retVal;
	}
	
	public static function urlRewrite()
	{
		
		// Extract the extra URL stuff from the path
		if (isset($_GET['q']) && $_GET['q'] != '/') {
			
			$url = substr($_GET['q'], 1);
			$matchFound = false;
			
			// Load the rewrites configuration
			$xml = simplexml_load_file('./config/rewrites.xml');
			if ($xml) {
				foreach ($xml->rule as $rule) {
					$expr = '@'.$rule->expr.'@is';
					if (preg_match($expr, $url)) {
						$qs = preg_replace($expr, $rule->query_string, $url);
						if ($rule->attributes()->redirect == "true") {
							header('Location: '.$qs);
							exit();
						} else {
							$params = explode('&', $qs);
							foreach ($params as $val) {
								$param = explode('=', $val);
								$_GET[$param[0]] = $param[1];
							}
						}
						$matchFound = true;
						break;
					}
				}
			}
			unset($xml);
		} else {
			// It's the base page, so match found
			$matchFound = true;
		}
		
		return $matchFound;
		
	}
	
	/**
	 * Wrapper to get a KVP via the API
	 * @param string $key Name of option to retrieve
	 * @return mixed Value of the key returned
	 */
	public static function getOption($key) {
		$obj = self::call('kvp', 'get', array('key'=>$key));
		return $obj->body;
	}
	
	/**
	 * Wrapper to set a KVP
	 * @param string $key Name of option to set
	 * @param mixed $value Value to store
	 * @return Returns the success of the set
	 */
	public static function setOption($key, $value) {
		return self::post('kvp', 'set', array('key'=>$key), $value);
	}
	
	private static function _internal($module, $method, $params, $cache) {
		global $_apiHits;
		$cacheKey = md5($module . '-' . $method . '-' . serialize($params));
		$retVal = DxCache::Get($cacheKey);
		if ($retVal === false || $cache == 0) {
			$_apiHits++;
			$retVal = DxApi::handleRequest($module, $method, $params);
			DxCache::Set($cacheKey, $retVal);
		}
		return $retVal;
	}
	
	private static function _external($module, $method, $params, $cache, $apiUri) {
		
		global $_apiHits;
		
		$qs = '/index.php?type=json&method=' . $module . '.' . $method;

		// Build the query string
		if (count($params) > 0) {
			foreach ($params as $key=>$val) {
				$qs .= "&$key=".urlencode($val);
			}
		}

		// Check to see if there is a cached version of this
		$cacheKey = md5($apiUri.$qs);
		$retVal = DxCache::Get($cacheKey);
		if ($retVal === false || $cache == 0) {
			$_apiHits++;
			$file = file_get_contents($apiUri.$qs);
			$retVal = json_decode($file);
			// Only cache on success
			if ($retVal->status->ret_code == 0) {
				DxCache::Set($cacheKey, $retVal, $cache);
			}
		}

		// Return the request
		return $retVal;
	
	}
	
	private static function _xmlEntities($string) {
		return str_replace(array('<', '>'), array('&lt;', '&gt;'), $string);
	}

}
