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
	
	if (!is_file($origFile) && !is_file($convFile)) {
		throw new Exception("Cannot find file");
	}
	
	
	if ($file == 'install.rdf') {
		// reformat XML because the diff cannot ignore indentation
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$doc->load($origFile);
		$doc->encoding = 'utf-8';
		
		$content1 = trim($doc->saveXML());
		
	} else {
		if (is_file($origFile)) {
			$content1 = strtr(trim(file_get_contents($origFile)), array(
				"\r\n" => "\n",
			));
		} else {
			$content1 = "";
		}
	}
	
	if (is_file($convFile)) {
		$content2 = strtr(trim(file_get_contents($convFile)), array(
			"\r\n" => "\n",
		));
	} else {
		$content2 = "";
	}
	
	$diff = new FineDiff($content1, $content2, FineDiff::$paragraphGranularity);
	
	$diffHtml = $diff->renderDiffToHTML();
	
	
} catch (Exception $ex) {
	$error = $ex->getMessage();
}

?>

<? $mainClass = "diff-page" ?>
<? include "templates/header.php" ?>


<h2>diff: <?=htmlspecialchars($file) ?></h2>

<? if ($error === null): ?>
<div class="diff"><?=$diffHtml ?></div>


<? else: ?>
	<div class="error"><?=$error ?></div>
<? endif ?>

<? include "templates/footer.php" ?>
