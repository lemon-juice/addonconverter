<?php
$url = @$_GET['url'];
?>
<html>
<head>
<title>Redirecting...</title>
<script type="text/javascript">
function goTo() {
	var link = document.getElementsByTagName('a')[0];
	link.style.color = '#ccc';
	
	if (!window.MouseEvent) {
		location.href = "<?=$url ?>";
		return;
	}
	
	var event = new MouseEvent('click', {
		'view': window,
		'bubbles': true,
		'cancelable': true
	});
	link.dispatchEvent(event);
}
</script>
</head>

<body onload="goTo()">
	<a href="<?=htmlspecialchars($url) ?>" rel="noreferrer">redirecting...</a>
</body>
</html>
