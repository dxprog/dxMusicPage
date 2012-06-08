<?php

/**
 * dxprog.com Twitter user APIs
 */

/**
 * Gets a user based on the hash provided
 * @param string $_hash The md5 hash to match the database against
 */
function rpc_getUser ()
{
	
	global $_hash, $_type;
	
	// Get the user info based on the has provided
	db_Connect ();
	$result = db_Query ("SELECT * FROM users WHERE user_hash='$_hash'");
	
	// If there were no results, return null for all paramaters
	$t = null;
	if (!$result->count) {
		$t->id = 0;
		$t->name = 0;
		$t->perms = 0;
	}
	else {
		$row = db_Fetch ($result);
		$t->id = $row->user_id;
		$t->name = $row->user_name;
		$t->avatar = $row->user_avatar;
		$t->handle = $row->user_handle;
		$t->perms = $row->user_permissions;
	}
	
	// Format and return the object based on the output type
	switch ($_type) {
		case "xml":
			$out = "<user id=\"$t->id\"><name handle=\"$t->handle\">$t->name</name><avatar>$t->avatar</avatar><permissions>$t->perms</permissions></user>";
			break;
		case "json":
		default:
			$out = json_encode ($t);
	}
	return $out;
	
}

/**
 * Adds a user to the database and returns the user ID
 **/
function rpc_addUser ()
{

	global $_name, $_avatar, $_handle, $_hash, $_id, $_type;
	
	// Verify the hash
	$hash = md5("$_handle:$_id");
	if ($_hash != $hash && $hash != md5(":"))
		raiseError (300, "Invalid hash");
	
	// Add the user to the database
	db_Connect ();
	$id = db_Query ("INSERT INTO users (user_name, user_handle, user_avatar, user_hash, user_twitter_id) VALUES ('".db_Escape ($_name)."', '".db_Escape ($_handle)."', '".db_Escape ($_avatar)."', '$hash', '".db_Escape ($_id)."')");
	
	// Return the ID
	switch ($_type) {
		case "xml":
			$out = "<user>$id</user>";
			break;
		case "json":
		default:
			$t->id = $id;
			$out = json_encode ($t);
	}
	return $out;

}

/**
 */
?>