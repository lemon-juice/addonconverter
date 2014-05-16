<?php
function parseLogMsg($msg, $dir) {
	return preg_replace_callback('#<a>([^<]+)</a>#i', function($matches) use ($dir) {
		$link = "diff.php?file=" .urlencode($matches[1])
			. "&amp;dir=$dir";
		return "<a href='$link' target='_blank' title='view diff'>$matches[1]</a>";
	}, $msg);
	
}
