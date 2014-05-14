<?php

?>
<? include "templates/header.php" ?>

<h1>SeaMonkey Add-on Converter</h1>

<? if (!empty($error)): ?>
	<div class="error"><?=$error ?></div>
<? endif ?>

<form action="convert.php" method="post" enctype="multipart/form-data">
	Upload add-on installer (with <em>.xpi</em> filename extension):
	<input type="file" name="xpi" />
	<input type="submit" value="Convert!" />	
</form>


<? include "templates/footer.php" ?>
