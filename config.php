<?php

// Database credentials
define ("DB_HOST", "localhost");
define ("DB_USER", "root");
define ("DB_PASS", "__DB_PASS__");
define ("DB_NAME", "dxmp");

// AWS S3 Credentials
define('AWS_LOCATION', '__AWS_LOCATION__');
define('AWS_KEY', '__AWS_KEY__');
define('AWS_SECRET', '__AWS_SECRET__');
define('AWS_ENABLED', false);

// Local paths
define('LOCAL_CACHE_SONGS', '/home/www/dxmpv2/songs');
define('LOCAL_CACHE_IMAGES', '/home/www/dxmpv2/images');

// Location of the local cache folder
$GLOBALS["_cache"] = "./cache";

// View directory
define('VIEW_PATH', './view/');