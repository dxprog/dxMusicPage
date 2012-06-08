<?php

$Max = 280;

// ob_start();

$_WIDTH = $Max;
$_HEIGHT = $Max;
$w = isset($_GET['width']) ? $_GET['width'] : null;
$h = isset($_GET['height']) ? $_GET['height'] : null;
if ($w)
	$_WIDTH = $w;
if (is_numeric($h))
	$_HEIGHT = $h;
$outFile = md5 ($_GET["file"]."_{$_WIDTH}_{$_HEIGHT}");
$file = $_GET["file"];

if (!strpos('images/', $file)) {
	$file = 'images/' . $file;
}

// Check to see if there's a cached thumbnail already
if (file_exists ("cache/" . $outFile . '.png') && filemtime('cache/' . $outFile . '.png') > filemtime($file) && filesize('cache/' . $outFile . '.png') > 1024) {
	header ("Location: cache/". $outFile .".png");
	exit;
}

// Load the image based on extension
switch (strtolower(end(explode('.', $file)))) {
case 'jpeg':
case "jpg":
	$Image = imagecreatefromjpeg ($file);
	break;
case "png":
	$Image = imagecreatefrompng ($file);
	break;
}

// Get the dimensions of the image and figure out the appropriate, rescaled size
$Width = imagesx ($Image);
$Height = imagesy ($Image);
$NWidth = $Width < $_WIDTH ? $Width : $_WIDTH;
$NHeight = $Height < $_HEIGHT ? $Height : $_HEIGHT;

if ($Width > $_WIDTH && $Width > $Height) {
	$NWidth = $_WIDTH;
	$NHeight = $_WIDTH * ($Height / $Width);
}

if ($Height > $_HEIGHT && $Height > $Width) {
	$NHeight = $_HEIGHT;
	$NWidth = $_HEIGHT * ($Width / $Height);
}

$x = ($_WIDTH - $NWidth) / 2;
$y = ($_HEIGHT - $NHeight) / 2;

// Create the new image and copy the resized one over
$Out = imagecreatetruecolor ($w, $h);
imagecopyresampled ($Out, $Image, $x, $y, 0, 0, $NWidth, $NHeight, $Width, $Height);

// Save out the file and do a redirect
imagepng ($Out, "./cache/" . $outFile . ".png");
header ("Location: ./cache/" . $outFile . ".png");

?>