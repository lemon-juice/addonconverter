<?php
require_once "app/functions.php";

emptyXPICache();
?>
<? include "templates/header.php" ?>


<form action="convert.php" method="post" enctype="multipart/form-data" id="converterForm">
	<? if (!empty($error)): ?>
		<div class="error"><?=$error ?></div>
	<? endif ?>

	<h2>About this converter</h2>
	<div class="info">
		<p>The purpose of this tool is to make Firefox and Thunderbird extensions compatible with <a href="http://www.seamonkey-project.org/">SeaMonkey</a>. It will run a couple of automatic conversions based on <a href="https://developer.mozilla.org/en-US/Add-ons/SeaMonkey_2">most commonly known differences</a> between Firefox and SeaMonkey. There is no guarantee that every extension will work in SeaMonkey &mdash; it will usually install but how and if it will work depends on the code. The simpler the extension the more likelihood of succeess. To learn more about this tool read the discussion on <a href="http://forums.mozillazine.org/viewtopic.php?f=40&amp;t=2834855">MozillaZine Forum</a>.</p>
		<p>For trying out an extension in most cases it is best to leave the default options unchanged. Sometimes, this tool can do too much so in case of problems you may try to play with the options &mdash; remember this tool is mostly dumb and except for updating install.rdf it does not parse nor interpret the source code &mdash; it only does some basic string replacements. In most cases this will work, but sometimes it can produce broken code and unusable add-on. However, after the conversion you will be able to see the diff of changed files &mdash; handy for the more experienced users.</p>
		<p class="warning"><strong>Warning!</strong> While there are some non-SeaMonkey extensions that can be automatically made compatible with SeaMonkey there is no guarantee what will actually happen. If you are unsure, it is strongly suggested you test the modded extension in a separate profile first as it can behave unexpectedly. Such modifications are neither supported by Mozilla nor by add-on authors so remember you are doing this at your own risk!</p>
	

		<h2>Feedback and Support</h2>
		<p>There's no formal support for this tool but you are welcome to share your experience, discuss and ask questions in <a href="http://forums.mozillazine.org/viewtopic.php?f=40&amp;t=2834855">this MozillaZine thread</a>. There you will also find information on which extensions work after automatic conversion and which don't.</p>
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
			<label><input type="checkbox" name="convertManifest" checked="" /> add SeaMonkey-specific overlays to manifest files</label>
			<div class="checkboxes">
				<label><input type="checkbox" name="convertPageInfoChrome" checked="" /> allow to port Page Info features</label>
				
				<span class="help">
					<span>?</span>
					<span>This option will port extension features into the View -&gt; Page Info window &mdash; if there are any. The Page Info window is a bit different from that in Firefox, therefore porting stuff in that window may not be fully successful. If the Page Info window is broken after installing the extension then try another conversion with this option disabled.<br/><br/>
						Technical explanation: this feature will prevent <em>chrome://navigator/content/pageinfo/pageInfo.xul</em> from being added to chrome.manifest.
					</span>
				</span>
			</div>
		</div>
		
		<div>
			<label><input type="checkbox" name="convertChromeUrls" checked="" /> convert <em>chrome://</em> URL's in following file types:</label>
		</div>
		<div id="convertChromeUrlsExtensions" class="checkboxes">
			<label><input type="checkbox" name="convertChromeExtensions[]" value="xul" checked="" /> xul</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="rdf" checked="" /> rdf</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="js" checked="" /> js</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="jsm" checked="" /> jsm</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="xml" checked="" /> xml</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="html" checked="" /> html</label>
			<label><input type="checkbox" name="convertChromeExtensions[]" value="xhtml" checked="" /> xhtml</label>
		</div>
		
		<div>
			<label><input type="checkbox" name="xulIds" checked="" /> replace some Thunderbird- and Firefox-specific IDs in xul and xml files</label>
			<span class="help">
				<span>?</span>
				<span>Replaces:<br/>
					<em>menu_ToolsPopup</em> to <em>taskPopup</em><br/>
					<em>menu_HelpPopup</em> to <em>helpPopup</em><br/>
					<em>msgComposeContext</em> to <em>contentAreaContextMenu</em>
				</span>
			</span>
		</div>
		
		<div>
			<label><input type="checkbox" name="jsKeywords" checked="" /> replace some Firefox-specific keywords in js files</label>
			<span class="help">
				<span>?</span>
				<span>Replaces strings:<br/>
					<em>@mozilla.org/browser/sessionstore;1</em> to <em>@mozilla.org/suite/sessionstore;1</em><br/>
					<em>@mozilla.org/steel/application;1</em> to <em>@mozilla.org/smile/application;1</em><br/>
					<em>@mozilla.org/fuel/application;1</em> to <em>@mozilla.org/smile/application;1</em><br/>
					<em>blockedPopupOptions</em> to <em>popupNotificationMenu</em><br/>
					<em>menu_ToolsPopup</em> to <em>taskPopup</em><br/>
					<em>menu_HelpPopup</em> to <em>helpPopup</em><br/>
					<em>getBrowserSelection(...)</em> to <em>ContextMenu.searchSelected(...)</em><br/>
				</span>
			</span>
		</div>
		
		<div>
			<label><input type="checkbox" name="replaceEntities" checked="" /> replace some Firefox-specific entities with plain text</label>
		</div>
		
		<div>
			<label><input type="checkbox" name="jsShortcuts" checked="" /> add definitions for Firefox-specific js shortcuts (Cc, Ci, Cr, Cu)</label>
			<span class="help">
				<span>?</span>
				<span>Adds <em>var</em> definitions for Cc, Ci, Cr and Cu corresponding to properties <em>classes</em>, <em>interfaces</em>, <em>results</em> and <em>utils</em> in the <em>Components</em> object respectively. It does its best not to add those definitions if they are defined as constants in the javascript file. Also, these shortcuts are not added to bootstrapped (restartless) extensions.</span>
			</span>
		</div>
	</div>
	
	<div>
		<input type="submit" value="Convert!" />
	</div>
</form>


<div class="footer-text">
	<div class="update">Last update of converter engine: 2014-10-30</div>
	<div class="disclaimer">Disclaimer: This service is provided as-is, free of charge, and without any warranty whatsoever. The author and provider of this service shall not be responsible for any damages caused directly or indirectly by using this web application.</div>
</div>

<? include "templates/footer.php" ?>
