<?php
require "app/Init.php";
require "app/functions.php";

emptyXPICache();

try {
	$url = isset($_POST['url']) ? trim($_POST['url']) : null;
	
	$uploadFileName = (empty($_FILES) || empty($_FILES['xpi']['name'])) ? '' : $_FILES['xpi']['name'];
	
	if (!$uploadFileName && !$url) {
		throw new Exception("No file to process");
	}
	
	$dirPart = uniqid("", true);
	$tmpDir = "tmp/convert/$dirPart";
	mkdir($tmpDir);
	
	$tmpSourceDir = "$tmpDir/source";
	$tmpDestDir = "$tmpDir/dest";
	
	mkdir($tmpSourceDir);
	mkdir($tmpDestDir);
	
	$tmpFile = "$tmpSourceDir/$uploadFileName";
	$maxFileSize = 16 * 1024 * 1024;
	
	if ($uploadFileName) {
		logFormSubmission($uploadFileName);
		
		if (!@move_uploaded_file($_FILES['xpi']['tmp_name'], $tmpFile)) {
			throw new Exception("Error moving file to temporary folder");
		}
		
		if (filesize($tmpFile) > $maxFileSize) {
			unlink($tmpFile);
			$maxMB = round($maxFileSize / 1024 / 1024, 1);
			throw new Exception("Input file too large. Maximum $maxMB MB is allowed");
		}
		
	} elseif ($url) {
		logFormSubmission($url);
		
		$ag = new AMOGrabber($maxFileSize);
		$tmpFile = $ag->fetch($url, $tmpSourceDir);
	}
	
	
	$conv = new AddOnConverter($tmpFile);
	
	$conv->maxVersionStr = substr(trim($_POST['maxVersion']), 0, 10);
	$conv->convertChromeURLsInExt = array();
	
	if (isset($_POST['convertChromeExtensions'])
		&& is_array($_POST['convertChromeExtensions'])
		&& count($_POST['convertChromeExtensions'] < 30)
	) {
		$conv->convertChromeURLsInExt = $_POST['convertChromeExtensions'];
	}
	
	$conv->xulIds = !empty($_POST['xulIds']);
	$conv->jsShortcuts = !empty($_POST['jsShortcuts']);
	$conv->jsKeywords = !empty($_POST['jsKeywords']);
	
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

<h2>Conversion Results (click on file names too see changes):</h2>

<? if ($destFile): ?>
	<ol>
		<? foreach ($result as $file => $messages): ?>
		<li><?=makeLinkToDiff($file, $dirPart) ?>
			<ul>
				<? foreach ($messages as $msg): ?>
					<li><?=$msg; ?></li>
				<? endforeach ?>
			</ul>
		</li>
		<? endforeach ?>
	</ol>

	<h2>Your converted add-on is available for download here:</h2>
	<p>
		<a href="<?=htmlspecialchars($destFile) ?>" class="download"><?=htmlspecialchars(basename($destFile)) ?></a>
		&mdash;
		<span class="filesize">
			<?=round(filesize($destFile) / 1024) ?> KB
		</span>
	</p>


<? else: ?>
	<p>I didn't find anything to convert in this add-on.</p>
<? endif ?>

	<p style="margin-top: 2em"><a href=".">« perform another conversion</a></p>

<? include "templates/footer.php" ?>
