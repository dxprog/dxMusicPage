<?php

define('DEVICE_STATUS_BORN', 0);
define('DEVICE_STATUS_LIVE', 1);
define('DEVICE_STATUS_DEAD', 2);
define('DEVICE_CACHE_KEY', 'dxmpDevices');

class Device {

	/**
	 * Registers, refreshes, or removes an available DXMP capable device
	 */
	public static function register($vars) {
		
		$ip = $_SERVER['REMOTE_ADDR'];
		$port = isset($vars['port']) && is_numeric($vars['port']) ? $vars['port'] : false;
		$retVal = false;
		
		if ($port) {
			
			$devices = DxCache::Get(DEVICE_CACHE_KEY) ?: array();
			
			$id = str_replace('.', '', $ip) . $port;
			$name = isset($vars['name']) ? $vars['name'] : $ip . ':' . $port;
			$status = isset($vars['status']) && is_numeric($vars['status']) ? $vars['status'] : DEVICE_STATUS_LIVE;
			
			// Ping the device and make sure it's really reachable
			$file = @json_decode(@file_get_contents('http://' . $ip . ':' . $port . '/?action=ping'));
			
			if (isset($file->alive) && $file->alive == true) {
				switch ($status) {
					case DEVICE_STATUS_BORN:
						$obj = new stdClass;
						$obj->id = $id;
						$obj->name = $name;
						$obj->status = $status;
						$obj->port = $port;
						$obj->ip = $ip;
						$obj->ttl = time() + 600;
						$devices[$id] = $obj;
						$retVal = true;
						DxCache::Set(DEVICE_CACHE_KEY, $devices);
						break;
					case DEVICE_STATUS_LIVE:
						if (isset($devices[$id])) {
							$devices[$id]->ttl = time() + 600;
							DxCache::Set(DEVICE_CACHE_KEY, $devices);
							$retVal = true;
						}
						break;
					case DEVICE_STATUS_DEAD:
						if (isset($devices[$id])) {
							unset($devices[$id]);
							DxCache::Set(DEVICE_CACHE_KEY, $devices);
							$retVal = true;
						}
						break;
				}
			}
			
		}
		
		return $retVal;
		
	}
	
	/**
	 * Returns a list of available devices
	 */
	public static function getDevices() {
		
		$devices = DxCache::Get(DEVICE_CACHE_KEY);
		$retVal = $devices;
		
		// Run through and prune dead devices
		if (false !== $devices) {
			$retVal = array();
			foreach ($devices as $id => $device) {
				if (time() < $device->ttl) {
					$retVal[$id] = $device;
				}
			}
			DxCache::Set(DEVICE_CACHE_KEY, $retVal);
		}
		
		return $retVal;
	}
	
}