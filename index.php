<?php
require_once "app/functions.php";

emptyXPICache();
?>
<? include "templates/header.php" ?>


<form action="convert.php" method="post" enctype="multipart/form-data">
	<? if (!empty($error)): ?>
		<div class="error"><?=$error ?></div>
	<? endif ?>
	
	<div class="group">
		Upload add-on installer file (with <em>.xpi</em> filename extension):
		<div class="field"><input type="file" name="xpi" /></div>
	</div>
	
	<div class="group">
		or paste full URL of add-on page at https://addons.mozilla.org/ or direct link to xpi file:
		<div class="field"><input type="text" name="url" size="95" maxlength="250" /></div>
	</div>
	
	<div class="group options">
		<h2>Options:</h2>
		
		<div>
			<label><input type="checkbox" checked="" disabled="" /> add SeaMonkey to install.rdf</label>
		</div>
		<div>
			set maxVersion to: <input type="text" name="maxVersion" value="2.*" size="7" maxlength="7" />
		</div>
		
		<div>
			<label><input type="checkbox" checked="" disabled="" /> add SeaMonkey-specific overlays to manifest files</label>
		</div>
		
		<div>
			convert <em>chrome://</em> URL's in folloing file types:
		</div>
		<div class="checkboxes">
			<label><input type="checkbox" name="convertChromeExtensions[]" value="xul" checked="" /> xul</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="rdf" checked="" /> rdf</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="js" checked="" /> js</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="jsm" checked="" /> jsm</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="xml" checked="" /> xml</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="html" checked="" /> html</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="xhtml" checked="" /> xhtml</label>
		</div>
		
		<div>
			<label><input type="checkbox" name="jsKeywords" checked="" /> replace some Firefox-specific keywords in js files</label>
		</div>
		
		<div>
			<label><input type="checkbox" name="jsShortcuts" checked="" /> add definitions for Firefox-specific js shortcuts (Cc, Ci, Cr, Cu)</label>
		</div>
	</div>
	
	<div>
		<input type="submit" value="Convert!" />
	</div>
</form>


<? include "templates/footer.php" ?>
