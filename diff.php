<?php
require "app/Init.php";
require "lib/FineDiff/finediff.php";

$error = null;

try {
	$file = empty($_GET['file']) ? null : $_GET['file'];
	$dirPart = empty($_GET['dir']) ? null : trim($_GET['dir'], './');
	
	if (!$file || !$dirPart) {
		throw new Exception("No required parameters passed");
	}
	
	
	$dir = "tmp/convert/$dirPart/source";
	
	if (!is_dir($dir)) {
		throw new Exception("Folder does not exist - perhaps your session has expired");
	}
	
	$file = strtr($file, array(
		'../' => '',
		'..\\' => '',
	));
	
	$origFile = "$dir/original/$file";
	$convFile = "$dir/converted/$file";
	
	if (!is_file($origFile) || !is_file($convFile)) {
		throw new Exception("Cannot find file");
	}
	
	$content1 = strtr(trim(file_get_contents($origFile)), array(
		"\r\n" => "\n",
	));
	
	$content2 = strtr(trim(file_get_contents($convFile)), array(
		"\r\n" => "\n",
	));
	
	$diff = new FineDiff($content1, $content2, FineDiff::$paragraphGranularity);
	
	$diffHtml = $diff->renderDiffToHTML();
	
	
} catch (Exception $ex) {
	$error = $ex->getMessage();
}

?>

<? include "templates/header.php" ?>


<h1><?=htmlspecialchars($file) ?></h1>

<? if ($error === null): ?>
<div class="diff"><?=$diffHtml ?></div>


<? else: ?>
	<div class="error"><?=$error ?></div>
<? endif ?>

<? include "templates/footer.php" ?>
