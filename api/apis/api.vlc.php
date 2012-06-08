<?php

/**
 * SMP VLC Control Module
 * @author Matt Hackmann <matt@dxprog.com>
 * @package SMP
 */

define ('MP3_LOCATION', '/var/www/_dxmp/songs/');

class VLC {

	public static function playSong($vars) {

		$_id = isset($vars['id']) && is_numeric($vars['id']) ? $vars['id'] : false;
		
		if ($_id) {
			
			// Get the track location from the database and resolve it to an absolute path
			db_Connect();
			$row = db_Fetch(db_Query('SELECT content_meta FROM content WHERE content_id=' . $_id));
			$meta = json_decode($row->content_meta);
			$file = $meta->filename;
			$tmp = end(explode('/', $file));
			$file = MP3_LOCATION . $tmp;
			
			$call = self::_vlcCall('in_play', array('input'=>$file));
		}	

		return self::getStatus();
		
	}

	public static function togglePause() {
		self::_vlcCall('pl_pause', null);
		return self::getStatus();
	}

	public static function getStatus() {
		
		$retVal = self::_vlcCall(null, null);
		
		// Prepare an object for JSON/PHP output
		$xml = simplexml_load_string($retVal);
		$obj = null;
		$obj->length = (int)$xml->length;
		$obj->position = (int)$xml->time;
		$obj->state = (string)$xml->state;
		
		return $obj;
		
	}

	function seek($vars)
	{
		
		self::_vlcCall('seek', array('val'=>$vars['val']));
	}

	function fullscreen() {
		self::_vlcCall('fullscreen', null);
	}

	function stop()
	{
		self::_vlcCall('pl_stop', null);
	}

	private static function _vlcCall($command, $params) {

		// Set up the query string
		$qs = '';
		if (is_array($params)) {
			foreach ($params as $key=>$val) {
				$qs .= '&' . $key . '=' . rawurlencode($val);
			}
		}
		
		// Fire off the command to VLC and return the XML status
		$retVal = @file_get_contents('http://127.0.0.1:8000/requests/status.xml?command=' . $command . $qs);
		return $retVal;

	}

}
