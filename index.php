<?php

?>
<? include "templates/header.php" ?>

<h1>SeaMonkey Add-on Converter</h1>

<? if (!empty($error)): ?>
	<div class="error"><?=$error ?></div>
<? endif ?>

<form action="convert.php" method="post" enctype="multipart/form-data">
	<div>
		Select add-on installer file (with <em>.xpi</em> filename extension):
		<input type="file" name="xpi" />
	</div>
	<div>
		maxVersion: <input type="text" name="maxVersion" value="2.*" size="7" maxlength="7" />
	<div>
		<input type="submit" value="Convert!" />
	</div>
</form>


<? include "templates/footer.php" ?>
