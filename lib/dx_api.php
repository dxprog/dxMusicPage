<?php

/**
 * DxApi
 * @author Matt Hackmann <matt@dxprog.com>
 * @package DXAPI
 * @license GPLv3
 */

require_once($_apiPath . '/config.php');
require_once($_apiPath . '/libs/lib_mysql.php');
require_once($_apiPath . '/libs/dx_cache.php');
require_once($_apiPath . '/apis/api.support.php');
 
/**
 * Data return types
 * @global integer $GLOBALS["_return"]
 * @name $_return
 */
$GLOBALS['_return'] = "json,xml,php";

/**
 * Error code constants
 */
define ('ERR_INVALID_METHOD', 100);
define ('ERR_INVALID_RETURN_TYPE', 101);
define ('ERR_INVALID_LIBRARY', 102);
define ('ERR_INVALID_FUNCTION', 103);
define ('ERR_BAD_LOGIN', 104);
define ('ERR_NEED_SESSION', 105);

/**
 * Error messages associate with the above error codes
 * @global array $GLOBALS['_err']
 * @name $_err
 */
$_err = array (	ERR_INVALID_METHOD=>'An invalid method was invoked.',
				ERR_INVALID_RETURN_TYPE=>'Return type requested is not valid. Valid return types are: ' . $_return,
				ERR_INVALID_LIBRARY=>'The library requested does not exists.',
				ERR_INVALID_FUNCTION=>'Function called does not exist in requested library.',
				ERR_BAD_LOGIN=>'User name and/or password do not match any on record.',
				ERR_NEED_SESSION=>'This page requires a valid user to be logged in.');

// Set the time zone
date_default_timezone_set('America/Chicago');

class DxApi {

	public static function handleRequest($library, $method, $vars, $object = null) {
		
		global $_err, $_apiPath;

		// Check to make sure the library being called exists
		if (!file_exists($_apiPath . '/apis/api.' . $library . '.php')) {
			raiseError(ERR_INVALID_LIBRARY, $_err[ERR_INVALID_LIBRARY]);
		}

		// Include the library
		require_once($_apiPath . '/apis/api.' . $library . '.php');

		// Make sure the function being called exists
		if (!method_exists ($library, $method)) {
			raiseError(ERR_INVALID_FUNCTION, $_err[ERR_INVALID_FUNCTION]);
		}

		// We're all through with error checks. Call the function and gather the results
		$obj = call_user_func(array($library, $method), $vars, $object);
		return self::buildObject(0, 'OK', $obj);
	
	}
	
	public static function buildObject ($code, $msg, $response)
	{

		global $_begin;

		// Calculate the generation time
		$genTime = microtime(true) - $_begin;

		// Construct the headers and return
		$obj = null;
		$obj->metrics = null;
		$obj->status = null;
		$obj->metrics->timestamp = gmdate('U');
		$obj->metrics->gen_time = $genTime;
		$obj->status->method = isset($_GET['method']) ? $_GET['method'] : 'none';
		$obj->status->ret_code = $code;
		$obj->status->message = $msg;
		$obj->body = $response;
		return $obj;
		
	}
	
	/**
	 * Cleans up an resources left open by previous functions
	 */
	public static function clean() {
		db_Close();
	}
	
}