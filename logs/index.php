<?php
function extLink($url) {
	return htmlspecialchars("../redirect.php?url=" . urlencode($url));
}

function linkToAMO($url) {
	// e.g.: https://addons.mozilla.org/firefox/downloads/latest/11617/addon-11617-latest.xpi
	// https://addons.cdn.mozilla.net/user-media/addons/201/downthemall-2.0.17-fx+sm.xpi
	$count = preg_match('#^https?://[^/]+\.mozilla\.[a-z]+/.+/(\d{1,8})/.+\.xpi#i', $url, $matches);
	
	if ($count) {
		return "https://addons.mozilla.org/addon/" . $matches[1] . "/";
	}
	
	// change to English, e.g.:
	// https://addons.mozilla.org/ru/firefox/addon/firepicker/
	$count = preg_match('#^https?://[^/]+\.mozilla\.org/([a-z-]+)/(firefox|thunderbird|seamonkey)/addon/(.*)#i', $url, $matches);
	
	if ($count) {
		return "https://addons.mozilla.org/en-US/addon/$matches[3]";
	}
	
	return $url;
}


// **********************************************************************
$files = glob("submit*.txt");
rsort($files);

$selectedFile = empty($_GET['file']) ? null : $_GET['file'];

if (!$selectedFile && $files) {
	$selectedFile = $files[0];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Logs</title>
<style type="text/css">
body, table td {
	font-family: 'tahoma', 'arial';
	font-size: 9pt;
}
a {
	text-decoration: none;
}
a:hover {
	color: red;
}
a:focus {
	outline: 1px dotted #444;
	background-color: rgba(255,255,255,0.7);
}
ul {
	list-style-type: none;
	padding: 0;
	margin: 0 0 2em 0;
}
ul li {
	display: inline;
	margin: 0 2em 0 0;
	padding: 0;
}
.selected {
	font-weight: bold;
}
table {
	border-spacing: 0;
	border-top: 1px solid #ccc;
	border-left: 1px solid #ccc;
}
table td {
	padding: 3px 4px;
	border-bottom: 1px solid #ccc;
	border-right: 1px solid #ccc;
	vertical-align: top;
}
table tr:nth-child(even) {
	background-color: #e0f5ff;
}
table tr:hover td {
	background-color: rgba(0,0,0,0.05);
}
table td.date {
	white-space: nowrap;
}
</style>
</head>

<body>
	<ul>
		<? foreach ($files as $file): ?>
		<li>
			<a href="?file=<?=$file ?>"<? if ($file === $selectedFile): ?> class="selected"<? endif ?>>
				<?=$file ?>
			</a>
		</li>
		<? endforeach ?>
	</ul>
	
	<? if ($selectedFile): ?>
	<table>
		<? $fp = fopen($selectedFile, 'r'); ?>
		<? while (($line = fgets($fp)) !== false): ?>
		<? $cols = explode("\t", trim($line)) ?>
		<tr>
			<td class="date"><?=$cols[0] ?></td>
			<td class="url">
				<? if (preg_match('#^https?://#i', $cols[1])): ?>
					<a href="<?=extLink(linkToAMO($cols[1])) ?>" rel="noreferrer" target="_blank"><?=htmlspecialchars($cols[1]) ?></a>
					<a href="<?=extLink($cols[1]) ?>" rel="noreferrer" target="_blank">
						[ORIG]</a>
				<? else: ?>
					<?=htmlspecialchars($cols[1]) ?>
				<? endif ?>
			</td>
			<td title="<?=$cols[2] ?>"><?=$cols[3] ?></td>
			<td><?=$cols[4] ?></td>
		</tr>
		<? endwhile ?>
	</table>
	<? endif ?>
</body>
</html>
