<?php
$destFile = $_GET['file'];

if (strpos($destFile, "tmp/convert/") !== 0 || !is_file($destFile)) {
	exit("File does not exist");
}

header('Content-Disposition: inline; filename="' . basename($destFile) . '"');
header("Content-Type: application/x-xpinstall");
header('Content-Length: ' . filesize($destFile));
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

readfile($destFile);
