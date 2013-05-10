<?php

/**
 * DXMP Main Page Controller
 */

namespace Controller {

	use Api;
	use Lib;

	class Media implements Page {

		public static function render() {

			// Skip an API call and ship the data out with the page
			$initData = Api\DXMP::getData(null);
			Lib\Display::setVariable('init_data', json_encode($initData));

		}

		public static function registerExtension($class, $method, $type) {

		}

	}

}