<?php

// Database credentials
define ("DB_HOST", "localhost");
define ("DB_USER", "__USER__");
define ("DB_PASS", "__PASS__");
define ("DB_NAME", "dxmp");

// AWS S3 Credentials
define('AWS_LOCATION', 'AWS_LOCATION');
define('AWS_KEY', 'AWS_KEY');
define('AWS_SECRET', 'AWS_SECRET');
define('AWS_ENABLED', false);

// Local paths
define('LOCAL_CACHE_SONGS', '/var/www/dxmp/songs');
define('LOCAL_CACHE_IMAGES', '/var/www/dxmp/images');

// Location of the local cache folder
$GLOBALS["_cache"] = "./cache";

?>
