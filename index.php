<?php

?>
<? include "templates/header.php" ?>

<h1>SeaMonkey Add-on Converter</h1>

<? if (!empty($error)): ?>
	<div class="error"><?=$error ?></div>
<? endif ?>

<form action="convert.php" method="post" enctype="multipart/form-data">
	<div>
		Upload add-on installer file (with <em>.xpi</em> filename extension):
		<input type="file" name="xpi" />
	</div>
	<div>
		or paste full URL of add-on page at https://addons.mozilla.org/ or direct link to xpi file:
		<input type="text" name="url" size="70" maxlength="250" />
	</div>
	<div>
		maxVersion: <input type="text" name="maxVersion" value="2.*" size="7" maxlength="7" />
	</div>
	<div>
		<input type="submit" value="Convert!" />
	</div>
</form>


<? include "templates/footer.php" ?>
