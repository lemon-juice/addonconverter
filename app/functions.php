<?php
function makeLinkToDiff($file, $dir) {
	$link = "diff.php?file=" .urlencode($file)
		. "&amp;dir=$dir";
	return "<a href='$link' target='_blank' title='view diff'>$file</a>";
}

function emptyXPICache() {
	$dir = "tmp/convert";
	
	if (!is_dir($dir)) {
		return;
	}
	
	$expiry = time() - 20 * 60;
	
	foreach (scandir($dir) as $filename) {
		$path = "$dir/$filename";
		
		if ($filename[0] != '.' && is_dir($path) && filemtime($path) < $expiry) {
			deleteWholeDir($path);
		}
	}
}

function deleteWholeDir($dir) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
		RecursiveIteratorIterator::CHILD_FIRST);

	foreach ($iterator as $file => $fileInfo) {
		if (strpos($file, "tmp/") !== 0) {
			exit("Unsafe file delete attempt!");
		}
		
		if ($fileInfo->isDir()) {
			rmdir($file);

		} else {
			unlink($file);
		}
	}
	
	rmdir($dir);
}
