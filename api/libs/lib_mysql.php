<?php

/**
 * DXAPI main staging area
 * @author Matt Hackmann <matt@dxprog.com>
 * @package DXAPI
 * @license GPLv3
 */

/**
 * Data return types
 * @global resource $GLOBALS["_dbconn"]
 * @name $_dbconn
 */
$GLOBALS['_dbconn'] = null;

// Database error codes
define ('DBERR_NOCREDS', 200);
define ('DBERR_CONNECT', 201);
define ('DBERR_DATABASE', 202);
define ('DBERR_BAD_QUERY', 203);
define ('DBERR_NO_CONNECTION', 204);

/**
 * Array of database error messages to be coordinated with the error constants
 * @global array $GLOBALS["_dberr"]
 * @name $_dberr
 */
$GLOBALS['_dberr'] = array (	DBERR_NOCREDS=>'Some database connection credentials have not been defined. Please check your configuration file.',
								DBERR_CONNECT=>'Unable to connect to MySQL server: ',
								DBERR_DATABASE=>'Unable to select MySQL database: ',
								DBERR_BAD_QUERY=>'Error executing SQL statement: ',
								DBERR_NO_CONNECTION=>'Not connected to the database');

/**
 * Connects to a MySQL database. Uses information stored in config.php for connection credentials.
 */
function db_Connect ()
{

	global $_dberr, $_dbconn;
	
	// If we're already connected to the database, jump out
	if (isset ($_dbconn))
		return;
	
	// Check to make sure that all the credentials have been set
	if (!defined ('DB_USER') || !defined ('DB_PASS') || !defined ('DB_HOST') || !defined ('DB_NAME')) {
		raiseError (DBERR_NOCREDS, $_dberr[DBERR_NOCREDS]);
	}
	
	// Attempt a connection to the database server
	if (!($_dbconn = @mysql_connect (DB_HOST, DB_USER, DB_PASS))) {
		raiseError (DBERR_CONNECT, $_dberr[DBERR_CONNECT].mysql_error ());
	}
	
	// Attempt to select the database
	if (!@mysql_select_db (DB_NAME, $_dbconn)) {
		raiseError (DBERR_DATABASE, $_dberr[DBERR_DATABASE].mysql_error ());
	}
	
}

/**
 * Executes and SQL query
 * @return mixed Returns object including row count and resource for SELECT, integer containing rows affected for UPDATE/DELETE and auto_inc number for INSERT
 */
function db_Query ($query, $errSuppress = false)
{

	global $_dbconn, $_dberr;
	
	if (!$_dbconn) {
		raiseError(DBERR_NO_CONNECTION, $_dberr[DBERR_BAD_QUERY]);
	}
	
	// Execute the query
	if (!($result = @mysql_query ($query)) && !$errSuppress) {
		raiseError (DBERR_BAD_QUERY, $_dberr[DBERR_BAD_QUERY].mysql_error ());
	}
		
	// Determine the output based upon the command used
	switch (strtolower (substr ($query, 0, 6))) {
		case 'select':
			$out->result = $result;
			$out->count = mysql_num_rows ($result);
			break;
		case 'insert':
			$out = mysql_insert_id ($_dbconn);
			break;
		case 'delete':
		case 'update':
			$out = mysql_affected_rows ($_dbconn);
			break;
	}

	return $out;

}

/**
 * Returns an object containing row data for the MySQL resource passed
 * @return object MySQL row data
 */
function db_Fetch ($res)
{
	return @mysql_fetch_object ($res->result);
}

/**
 * Wrapper for mysql_real_escape_string
 * @return string Escaped string
 **/
function db_Escape ($string)
{
	if (get_magic_quotes_gpc ()) {
		$string = stripslashes ($string);
	}
	return mysql_real_escape_string ($string);
}

function db_Close() {

}

?>