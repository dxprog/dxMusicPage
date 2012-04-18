<?php
/**
 * DXAPI main staging area
 * @author Matt Hackmann <matt@dxprog.com>
 * @package DXAPI
 * @license GPLv3
 */

/**
 * Used to calculate the page generation time
 * @global integer $GLOBALS['_begin']
 * @name $_begin
 */
$GLOBALS['_begin'] = microtime (true);

/**
 * The session key
 * @global $GLOBALS['_sesskey']
 * @name $_sesskey
 */
$GLOBALS['_sesskey'] = null;

/**
 * Path to the API libraries
 * @global $GLOBALS['_apiPath']
 * @name $_apiPath
 */
$GLOBALS['_apiPath'] = './';

// Include the important libraries
require_once('./dx_api.php');
require_once('./libs/dx_ser2.php');

// Get the type, library and method off the query string
$type = strtolower($_GET['type']);
$method = $_GET['method'];

// Fix multibyte shit
mb_language('uni');
mb_internal_encoding('UTF-8');

// Check to see if a valid return type was supplied
if (@strpos($_return, $type) === false) {
	raiseError(ERR_INVALID_RETURN_TYPE, $_err[ERR_INVALID_RETURN_TYPE]);
}

// Get the library and function call of the incoming request. The result should have exactly two rows
$t = explode ('.', $method, 2);
if (count($t) != 2) {
	raiseError (ERR_INVALID_METHOD, $_err[ERR_INVALID_METHOD]);
}
$library = $t[0];
$method = $t[1];

// Place all the items in the query string into an array for passing to the method
$vars = array();
foreach ($_GET as $var=>$val) {
	if ($var != 'method' && $var != 'type') {
		$vars[$var] = $val;
	}
}

// If this is a POST request, create an object for POST info
$post = _arrayToObject($_POST);

// Parse the request and clean up
$_ret = DxApi::handleRequest($library, $method, $vars, $post);
DxApi::clean();

// Begin constructing the response
_constructResponse ($type, $_ret);

?>
