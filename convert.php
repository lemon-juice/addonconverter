<?php
require "app/Init.php";

try {
	if (empty($_FILES) || empty($_FILES['xpi']['name'])) {
		throw new Exception("No file to upload");
	}
	
	$tmpDir = "tmp/upload/" . uniqid();
	mkdir($tmpDir);
	
	$tmpSourceDir = "$tmpDir/source";
	$tmpDestDir = "$tmpDir/dest";
	
	mkdir($tmpSourceDir);
	mkdir($tmpDestDir);
	
	$tmpFile = "$tmpSourceDir/" .$_FILES['xpi']['name'];
	
	if (!@move_uploaded_file($_FILES['xpi']['tmp_name'], $tmpFile)) {
		throw new Exception("Error moving file to temporary folder");
	}
	
	
	$conv = new AddOnConverter($tmpFile);
	$conv->convert($tmpDestDir);
	
	
} catch (Exception $ex) {
	$error = $ex->getMessage();
	include "index.php";
	exit;
}

?>

convert
