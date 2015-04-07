<? include "templates/header.php" ?>

<h2>Page Not Found</h2>

<? if (strpos($_SERVER['REQUEST_URI'], '/tmp/convert/') !== false): ?>
	<p>The file you were trying to download is no longer available. Probably your session has expired. Converted add-ons are deleted after 20 minutes. Please <a href="/">go to home page</a> and do the conversion again.</p>

<? else: ?>
	<p style="margin-top: 2em"><a href="/">« Go to home page</a></p>
<? endif ?>

<? include "templates/footer.php" ?>
