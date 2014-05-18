<?php
require_once "app/functions.php";

emptyXPICache();
?>
<? include "templates/header.php" ?>


<form action="convert.php" method="post" enctype="multipart/form-data">
	<? if (!empty($error)): ?>
		<div class="error"><?=$error ?></div>
	<? endif ?>

	<h2>About this converter</h2>
	<div class="info">
		<p>The purpose of this tool is to make Firefox and Thunderbird extensions compatible with SeaMonkey. It will run a couple of automatic conversions based on <a href="https://developer.mozilla.org/en-US/Add-ons/SeaMonkey_2">most commonly known differences</a> between Firefox and SeaMonkey. There is no guarantee that every extension will work in SeaMonkey &mdash; it will usually install but how and if it will work depends on the code. The simpler the extension the more likelihood of succeess.</p>
		<p>For trying out an extension in most cases it is best to leave the default options unchanged. Sometimes, this tool can do too much so in case of problems you may try to play with the options &mdash; remember this tool is mostly dumb and except for updating install.rdf it does not parse nor interpret the source code &mdash; it only does some basic string replacements. In most cases this will work, but sometimes it can produce broken code and unusable add-on. However, after the conversion you will be able to see the diff of changed files &mdash; handy for the more experienced users.</p>
		<p class="warning"><strong>Warning!</strong> While there are some non-SeaMonkey extensions that can be automatically made compatible with SeaMonkey there is no guarantee what will actually happen. If you are unsure, it is strongly suggested you test the modded extension in a separate profile first as it can behave unexpectedly. Such modifications are niether supported by Mozilla nor by add-on authors so remember you are doing this at your own risk!</p>
	</div>
	
	<h2>Converter</h2>

	<div class="group">
		Upload add-on installer file (with <em>.xpi</em> filename extension):
		<div class="field"><input type="file" name="xpi" /></div>
	</div>
	
	<div class="group">
		or paste direct link to xpi file or full URL of add-on page at https://addons.mozilla.org/:
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
			convert <em>chrome://</em> URL's in following file types:
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
			<label><input type="checkbox" name="xulIds" checked="" /> replace some Thunderbird-specific IDs in xul overlay files</label>
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
