<?php
require "app/Init.php";
require "app/functions.php";

try {
	$amoURL = isset($_POST['url']) ? trim($_POST['url']) : null;
	
	$uploadFileName = (empty($_FILES) || empty($_FILES['xpi']['name'])) ? '' : $_FILES['xpi']['name'];
	
	if (!$uploadFileName && !$amoURL) {
		throw new Exception("No file to upload");
	}
	
	$dirPart = uniqid("", true);
	$tmpDir = "tmp/convert/$dirPart";
	mkdir($tmpDir);
	
	$tmpSourceDir = "$tmpDir/source";
	$tmpDestDir = "$tmpDir/dest";
	
	mkdir($tmpSourceDir);
	mkdir($tmpDestDir);
	
	$tmpFile = "$tmpSourceDir/$uploadFileName";
	
	
	if ($uploadFileName) {
		if (!@move_uploaded_file($_FILES['xpi']['tmp_name'], $tmpFile)) {
			throw new Exception("Error moving file to temporary folder");
		}
		
	} elseif ($amoURL) {
		$ag = new AMOGrabber;
		$tmpFile = $ag->fetch($amoURL, $tmpSourceDir);
	}
	
	
	$conv = new AddOnConverter($tmpFile);
	$conv->maxVersionStr = $_POST['maxVersion'];
	$conv->convertChromeURLsInExt = array(
		'xul',
		'rdf',
		'js',
		'xml',
		'html',
		'xhtml',
	);
	$conv->jsShortcuts = true;
	$conv->jsKeywords = true;
	
	$destFile = $conv->convert($tmpDestDir);
	$result = $conv->getLogMessages();
	
	unlink($tmpFile);
	
	
} catch (Exception $ex) {
	$error = $ex->getMessage();
	include "index.php";
	exit;
}

?>

<? include "templates/header.php" ?>

<h1>Conversion Result</h1>

<? if ($destFile): ?>
	<ol>
		<? foreach ($result as $msg): ?>
		<li><?=parseLogMsg($msg, $dirPart) ?></li>
		<? endforeach ?>
	</ol>

	<h2>Your converted add-on is available for download here:</h2>
	<p>
		<a href="<?=htmlspecialchars($destFile) ?>"><?=htmlspecialchars(basename($destFile)) ?></a>
	</p>


<? else: ?>
	<p>I didn't find anything to convert in this add-on.</p>
<? endif ?>

<? include "templates/footer.php" ?>
