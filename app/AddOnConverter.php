<?php
class AddOnConverter {
	
	// config:
	public $maxVersionStr = '2.*';
	
	/**
	 * List of file extensions, in which to replace chrome URL's
	 */
	public $convertManifest = false;
	public $convertChromeUrls = false;
	public $convertChromeURLsInExt = array();
	public $convertPageInfoChrome = false;
	
	public $xulIds = false;
	public $jsShortcuts = false;
	public $jsKeywords = false;
	public $replaceEntities = false;
	// end config.

	protected $sourceFile;
	protected $originalDir;
	protected $convertedDir;
	protected $logMessages = array();
	protected $logWarnings = array();
	protected $chromeURLReplacements = array();
	protected $missingChromeURLs = array();

	/**
	 * @var DOMDocument
	 */
	protected $installRdf;
	
	protected $addonName = array();

	const SEAMONKEY_ID = '{92650c4d-4b8e-4d2a-b7eb-24ecf4f6b63a}';
	const FIREFOX_ID = '{ec8030f7-c20a-464f-9b0e-13a3a9e97384}';
	const THUNDERBIRD_ID = '{3550f703-e582-4d05-9a08-453d09bdfdc6}';
	
	const MISSING_FILES_DIR = 'app/missing_files';

	protected $minVersionStr = '2.0';

	/**
	 * @param string $sourceFile
	 * @throws Exception
	 */
	public function __construct($sourceFile) {
		$this->sourceFile = $sourceFile;
		
		$this->originalDir = dirname($sourceFile) . "/original";
		$this->convertedDir = dirname($sourceFile) . "/converted";
		$this->extractXPI($sourceFile, $this->originalDir, true);
		$this->extractXPI($sourceFile, $this->convertedDir, false);
		
		if (!is_file($this->convertedDir ."/install.rdf")) {
			throw new Exception("install.rdf not found in installer");
		}
		
		$this->installRdf = new DOMDocument('1.0', 'utf-8');
		$this->installRdf->preserveWhiteSpace = false;
		$this->installRdf->formatOutput = true;
		$result = @$this->installRdf->load($this->convertedDir ."/install.rdf");
		$this->installRdf->encoding = 'utf-8';
		
		if (!$result) {
			throw new Exception("Cannot parse install.rdf as XML");
		}
		
		$this->addonName = $this->getAddonNameFromInstallRds($this->installRdf);
		
		$this->chromeURLReplacements = array(
			'chrome://browser/content/browser.xul' => 'chrome://navigator/content/navigator.xul',
			'chrome://browser/content/pageinfo/pageInfo.xul' => 'chrome://navigator/content/pageinfo/pageInfo.xul',
			'chrome://browser/content/preferences/permissions.xul' => 'chrome://communicator/content/permissions/permissionsManager.xul',
			'chrome://browser/content/bookmarks/bookmarksPanel.xul' => 'chrome://communicator/content/bookmarks/bm-panel.xul',
			'chrome://browser/content/places/places.xul' => 'chrome://communicator/content/bookmarks/bookmarksManager.xul',
			'chrome://browser/content/preferences/sanitize.xul' => 'chrome://communicator/content/sanitize.xul',
			'chrome://browser/content/nsContextMenu.js' => 'chrome://communicator/content/nsContextMenu.js',
			'chrome://browser/content/utilityOverlay.js' => 'chrome://communicator/content/utilityOverlay.js',
			'chrome://browser/content/history/history-panel.xul' => 'chrome://communicator/content/history/history-panel.xul',
			'chrome://browser/content/places/menu.xml' => 'chrome://communicator/content/places/menu.xml',
			'chrome://browser/content/search/engineManager.js' => 'chrome://communicator/content/search/engineManager.js',
			'chrome://browser/locale/places/editBookmarkOverlay.dtd' => 'chrome://communicator/locale/bookmarks/editBookmarkOverlay.dtd',
			'chrome://browser/locale/places/places.dtd' => 'chrome://communicator/locale/bookmarks/places.dtd',
			'chrome://browser/content/places/bookmarkProperties.xul' => 'chrome://communicator/content/bookmarks/bm-props.xul',
			'chrome://browser/content/places/bookmarkProperties2.xul' => 'chrome://communicator/content/bookmarks/bm-props.xul',
			'chrome://browser/skin/livemark-folder.png' => 'chrome://communicator/skin/bookmarks/livemark-folder.png',
			'chrome://browser/content/' => 'chrome://navigator/content/',
			'resource:///modules/sessionstore/SessionStore.jsm' => 'resource:///components/nsSessionStore.js',
			//'chrome://browser/locale/preferences/cookies.dtd' => 'chrome://communicator/locale/permissions/cookieViewer.dtd',
		);

		
		foreach (scandir(self::MISSING_FILES_DIR) as $filename) {
			$file = self::MISSING_FILES_DIR . "/$filename";
			
			if ($filename[0] != '.' && is_file($file)) {
				$this->missingChromeURLs[$filename] = 'chrome://' .str_replace('+', '/', $filename);
			}
		}
	}
	
	/**
	 * @return string
	 */
	public function getAddOnName() {
		return $this->addonName;
	}
	
	/**
	 * @param string $destDir
	 * @return string|NULL URL path to converted file for download or NULL
	 *    if no conversion was done
	 */
	public function convert($destDir) {
		$filesConverted = 0;
		
		$newInstallRdf = $this->convertInstallRdf($this->installRdf, $this->maxVersionStr);
		
		if ($newInstallRdf) {
			// write modified file
			file_put_contents($this->convertedDir ."/install.rdf", $newInstallRdf->saveXML());
			unset($newInstallRdf);
			$filesConverted++;
		}
		
		if ($this->convertManifest) {
			$filesConverted += $this->convertManifest('chrome.manifest');
		}
		
		if ($this->convertChromeUrls) {
			$filesConverted += $this->replaceChromeURLs($this->convertChromeURLsInExt);
		}

		if ($this->xulIds) {
			$filesConverted += $this->replaceXulIds();
		}
		
		if ($this->jsShortcuts && !$this->isBootstrapped($this->installRdf)) {
			$filesConverted += $this->fixJsShortcuts();
		}
		
		if ($this->replaceEntities) {
			$er = new EntityReplacer($this);
			$filesConverted += $er->replaceEntities();
		}

		if ($this->jsKeywords) {
			$filesConverted += $this->fixJsKeywords();
		}
		
		$filesConverted += $this->removeMetaInfDir();

		if ($filesConverted > 0) {
			// ZIP files
			$filename = $this->createNewFileName($this->sourceFile);
			$destFile = "$destDir/$filename";
			
			$this->zipDir($this->convertedDir, $destFile);
			
			// extract jars again for diff
			$this->extractJARs($this->convertedDir, true);
			
			return $destFile;
		}
		
		return null;
	}
	
	/**
	 * @param DOMDocument $installRdf
	 * @param string $maxVersionStr
	 * @return DOMDocument|null NULL if document was not changed
	 */
	public function convertInstallRdf(DOMDocument $installRdf, $maxVersionStr) {
		
		$allDescriptions = $this->getDescriptionSFromInstallRdf($installRdf);
		$urnDescription = $allDescriptions['urnDescription'];
		$Descriptions = $allDescriptions['Descriptions'];

		if (!$urnDescription) {
			return null;
		}
		
		$docChanged = false;
		$SM_exists = false;
		
		foreach ($urnDescription->getElementsByTagName("targetApplication") as $ta) {
			$maxVersionNode = $this->findMaxVersionNodeInInstallRdf($ta, self::SEAMONKEY_ID, $Descriptions);

			if ($maxVersionNode) {
				// change maxVersion
				$SM_exists = true;
				
				if ($maxVersionNode->nodeValue != $maxVersionStr) {
					$this->log("install.rdf", "Changed <em>maxVersion</em> from '$maxVersionNode->nodeValue' to '$maxVersionStr'");

					$maxVersionNode->nodeValue = $maxVersionStr;
					$docChanged = true;
				}
				
				// warning about SeaMonkey already supported
				$this->logWarning("This add-on appears to already support SeaMonkey. It is recommended you do not use the converted version but install the original add-on. If the author has included SeaMonkey support then it is likely this converter will do more harm than good &mdash; unless you only increase maxVersion &mdash; however, your experience may vary. Remember that on the <a href='https://addons.mozilla.org/en-US/seamonkey/'>AMO site</a> you can still install (sometimes) add-ons marked as <em>Not available for SeaMonkey x.xx</em> by clicking on the greyed-out button and pressing <em>Install Anyway</em>. If the install button is greyed-out it usually means the add-on has not been tested with the current version of SeaMonkey but is likely to work regardless.");
				
				break;
			}
		}
		
		if (!$SM_exists) {
			// add application
			$tApp = $this->installRdf->createElementNS("http://www.mozilla.org/2004/em-rdf#", "targetApplication");
			
			$Description = $this->installRdf->createElementNS("http://www.mozilla.org/2004/em-rdf#", "Description");
			$id = $this->installRdf->createElementNS("http://www.mozilla.org/2004/em-rdf#", "id", self::SEAMONKEY_ID);
			$minVersion = $this->installRdf->createElementNS("http://www.mozilla.org/2004/em-rdf#", "minVersion", $this->minVersionStr);
			$maxVersion = $this->installRdf->createElementNS("http://www.mozilla.org/2004/em-rdf#", "maxVersion", $maxVersionStr);
			
			$Description->appendChild($id);
			$Description->appendChild($minVersion);
			$Description->appendChild($maxVersion);
			
			$tApp->appendChild($Description);
			$urnDescription->appendChild($tApp);
			
			$this->log("install.rdf", "Added SeaMonkey to list of supported applications");
			$docChanged = true;
		}
		
		return $docChanged ? $installRdf : null;
	}
	
	/**
	 * Get the main description from install.rdf:
	 * <Description about="urn:mozilla:install-manifest">
	 * @param DOMDocument $installRdf
	 * @return array urlDescription: DOMNode|null The urn Description node.
	 *   Descriptions: DOMNodeList All Description nodes.
	 */
	protected function getDescriptionSFromInstallRdf(DOMDocument $installRdf) {
		$Descriptions = $installRdf->documentElement->getElementsByTagNameNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "Description");
		
		$urnDescription = null;

		foreach ($Descriptions as $d) {
			$about = $d->getAttributeNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "about");
			if (!$about) {
				$about = $d->getAttribute("about");
			}
			
			if ($about == "urn:mozilla:install-manifest") {
				$urnDescription = $d;
				break;
			}
		}
		
		return array(
			'urnDescription' => $urnDescription,
			'Descriptions' => $Descriptions,
		);
	}
	
	/**
	 * @param DOMDocument $installRdf
	 * @return array [name,version]
	 */
	protected function getAddonNameFromInstallRds(DOMDocument $installRdf) {
		$out = array(
			'name' => null,
			'version' => null,
		);
		
		$descriptions = $this->getDescriptionSFromInstallRdf($installRdf);
		
		if (empty($descriptions['urnDescription'])) {
			return $out;
		}
		
		// try name attribute on <Description>
		$nameAttr = $descriptions['urnDescription']->attributes->getNamedItem('name');
		
		if ($nameAttr) {
			$out['name'] = $nameAttr->nodeValue;
			
		} else {
			// look in child elements
			foreach ($descriptions['urnDescription']->getElementsByTagName("name") as $name) {
				$out['name'] = $name->nodeValue;
				break;
			}
		}
		
		// find version
		$versionAttr = $descriptions['urnDescription']->attributes->getNamedItem('version');
		
		if ($versionAttr) {
			$out['version'] = $versionAttr->nodeValue;
			
		} else {
			// look in child elements
			foreach ($descriptions['urnDescription']->getElementsByTagName("version") as $version) {
				$out['version'] = $version->nodeValue;
				break;
			}
		}
		
		return $out;
	}
	
	/**
	 * Check if this is a bootstrapped extension.
	 * 
	 * @param DOMDocument $installRdf
	 * @return bool
	 */
	protected function isBootstrapped(DOMDocument $installRdf) {
		$allDescriptions = $this->getDescriptionSFromInstallRdf($installRdf);
		$urnDescription = $allDescriptions['urnDescription'];

		if (!$urnDescription) {
			return false;
		}
		
		$nodes = $urnDescription->getElementsByTagName('bootstrap');
		
		if ($nodes->length > 0) {
			$nodes = iterator_to_array($nodes);
			$val = $nodes[0]->nodeValue;
			
			return strtolower($val) == 'true';
		}
		
		return false;
	}

	
	/**
	 * Find application maxVersion node (attribute or element) referenced by the given
	 * targetApplication element.
	 * 
	 * @param DOMElement $ta
	 * @param string $appId app id to look for
	 * @param DOMNodeList $Descriptions All <Description> elements in install.rdf
	 * @return DOMNode|null
	 */
	private function findMaxVersionNodeInInstallRdf(DOMElement $ta, $appId, DOMNodeList $Descriptions) {
		$resource = $ta->getAttribute("RDF:resource");
		
		$targetDescriptions = array();
		
		if ($resource) {
			// find id in Description element referenced by resource
			$targetDescription = null;
			
			foreach ($Descriptions as $Description) {
				if ($Description->getAttribute("RDF:about") == $resource) {
					$targetDescriptions[0] = $Description;
					break;
				}
			}
		
		} else {
			// when no resource attr is present then target Description is
			// within given targetApplication
			$targetDescriptions = $ta->getElementsByTagName('Description');
		}

		if (!$targetDescriptions ||
			(!is_array($targetDescriptions) && $targetDescriptions->length == 0)) {
			return null;
		}
		

		// target Descriptions found
		foreach ($targetDescriptions as $targetDescription) {
			// try id attribute
			$idNode = $targetDescription->getAttributeNode('em:id');

			if (!$idNode) {
				// try to find id element
				$idNode = $targetDescription->getElementsByTagName("id")->item(0);
			}

			if ($idNode && $idNode->nodeValue == $appId) {
				// app id is correct - find node for maxVersion
				$maxVersionNode = $targetDescription->getAttributeNode('em:maxVersion');

				if (!$maxVersionNode) {
					// try to find id element
					$maxVersionNode = $targetDescription->getElementsByTagName("maxVersion")->item(0);
				}

				return $maxVersionNode ? $maxVersionNode : null;
			}
		}

		return null;
	}

	/**
	 * Convert chrome.manifest and any included manifest files
	 * 
	 * @param string $manifestFileName filename of manifest file
	 * @return int number of converted files
	 */
	protected function convertManifest($manifestFileName) {
		$manifestFile = $this->convertedDir ."/$manifestFileName";
		
		if (!is_file($manifestFile)) {
			return 0;
		}
		
		$convertedFilesCount = 0;
		$isConverted = false;
		$newManifest = "";
		
		$manifestContentLines = file($manifestFile);
		
		$fp = fopen($manifestFile, "rb");
		
		while (($line = fgets($fp, 4096)) !== false) {
			$trimLine = trim($line);
			$newLine = "";
			
			if ($trimLine && $trimLine[0] != '#') {
				$segm = preg_split('/\s+/', $trimLine);

				switch ($segm[0]) {
					case 'manifest':
						// included another manifest
						$file = ltrim($segm[1], './\\');
						$convertedFilesCount += $this->convertManifest($file);
						break;;

					case 'overlay':
					case 'override':
					case 'style':
						$newLine = $this->createNewManifestLine($trimLine);
						break;
					
					case 'binary-component':
					case 'interfaces':
					case 'component':
						// for now we guess this is firefox extension (ideally, we should
						// have detected whether it's Fx or TB)
						$newLine = $this->fixManifestAppVersionLine($line, 'firefox') . "\n";
					
					default:
						// replace application ids
						$line = strtr($line, array(
							self::FIREFOX_ID => self::SEAMONKEY_ID,
							self::THUNDERBIRD_ID => self::SEAMONKEY_ID,
						));
				}
			}
			
			if (!preg_match('/\n$/', $line)) {
				$line .= "\n";
			}
			
			$newManifest .= $line;
			
			$addLine = ($newLine && !$this->lineExistsInManifest($newLine, $manifestContentLines));

			if ($addLine && strpos($newLine, "chrome://navigator/content/navigator.xul")) {
				// if chrome://navigator/content/navigatorOverlay.xul is present then
				// chrome://navigator/content/navigator.xul should not be added
				// - see WOT extension
				$lookFor = str_replace("chrome://navigator/content/navigator.xul", "chrome://navigator/content/navigatorOverlay.xul", $newLine);
				
				if ($this->lineExistsInManifest($lookFor, $manifestContentLines)) {
					$addLine = false;
				}
			}
				
			if ($addLine) {
				$newManifest .= $newLine;
				$this->log($manifestFileName, "Added new line: <i>$newLine</i>");
				$isConverted = true;
				
				if (strpos($newLine, 'pageInfo.xul')) {
					$this->logWarning("This extension attempts to make modifications to the <i>Page Info</i> dialog window. This window is a bit different from that in Firefox so it is likely these modifications may be ported incorrectly. Please check if this window looks and works correctly after installing this extension. In case of problems, you may try converting with <i>allow to port Page Info features</i> disabled.");
				}
			}
		}
		
		fclose($fp);
		
		if ($isConverted) {
			file_put_contents($manifestFile, $newManifest);
			$convertedFilesCount++;
		}
		
		return $convertedFilesCount;
	}
	
	/**
	 * Take existing manifest line and if it contains firefox- or thunderbird-specific data
	 * then return new seamonkey-specific line. Otherwise, return empty string.
	 * 
	 * @param string $originalLine
	 * @return string
	 */
	private function createNewManifestLine($originalLine) {
		$convertedLine = strtr($originalLine, $this->chromeURLReplacements);
		
		if (!$this->convertPageInfoChrome) {
			// restore original pageInfo chrome url
			$convertedLine = strtr($convertedLine, array(
				'chrome://navigator/content/pageinfo/pageInfo.xul' => 'chrome://browser/content/pageinfo/pageInfo.xul',
			));
		}
		
		preg_match('/application=(\{[^}]+\})/', $convertedLine, $matches);
		
		$appId = isset($matches[1]) ? $matches[1] : null;
		
		if ($appId == self::FIREFOX_ID) {
			$application = 'firefox';
			
		} elseif ($appId == self::THUNDERBIRD_ID) {
			$application = 'thunderbird';
			
		} elseif ($appId == self::SEAMONKEY_ID) {
			$application = 'seamonkey';
			
		} elseif (!$appId) {
			// auto-detect
			if (preg_match('#^\s*(overlay|override|style)\s+chrome://messenger/#', $convertedLine)) {
				$application = 'thunderbird';
			} else {
				$application = 'firefox';
			}
		
		} else {
			// unknown
			$application = null;
		}
		
		if ($application && $application != 'seamonkey') {
			$convertedLine = $this->fixManifestAppVersionLine($convertedLine, $application);
		}
		
		
		if ($convertedLine != $originalLine) {
			return $convertedLine ."\n";
		} else {
			return '';
		}
	}
	
	/**
	 * Fix appversion flag in manifest file: convert it to platformversion
	 * @param string $line
	 * @param string $application
	 * @return string
	 */
	private function fixManifestAppVersionLine($line, $application) {
		$segments = preg_split('/(\s+)/', trim($line), -1, PREG_SPLIT_DELIM_CAPTURE);
		$newLine = "";
		
		foreach ($segments as $key => $lineSegm) {
			if (strpos($lineSegm, 'appversion') === 0) {
				$flagSegm = preg_split('/(\s*[<=>]+\s*)/', $lineSegm, 3, PREG_SPLIT_DELIM_CAPTURE);

				if (isset($flagSegm[2])) {
					$flagSegm[2] = $this->translateAppToPlatformVersion($flagSegm[2], $application);
					
					$flagSegm[0] = 'platformversion';
					$lineSegm = implode('', $flagSegm);
				}
			}
			
			$newLine .= $lineSegm;
		}
		
		if ($application == 'firefox') {
			$newLine = str_replace(self::FIREFOX_ID, self::SEAMONKEY_ID, $newLine);
			
		} else if ($application == 'thunderbird') {
			$newLine = str_replace(self::THUNDERBIRD_ID, self::SEAMONKEY_ID, $newLine);
		}
		
		return $newLine;
	}
	
	/**
	 * Translate Fx or Tb appversion to Gecko platformversion number.
	 * See https://developer.mozilla.org/en-US/docs/Mozilla/Gecko/Versions
	 * @param string $appVer app version number, may contain * at the end,
	 *    e.g. 3.6.*
	 * @param string $application
	 * @return string gecko version
	 */
	private function translateAppToPlatformVersion($appVer, $application) {
		preg_match('/([\d.]*\d+)(.*)$/', $appVer, $matches);
		
		if (!isset($matches[2])) {
			return $appVer;
		}
		
		$ffVer = $matches[1];
		$suffix = $matches[2];
		
		$gecko = $ffVer;
		
		if ($ffVer <= 1) {
			$gecko = '1.7';
			
		} elseif ($ffVer <= 1.5) {
			$gecko = '1.8';
			
		} elseif ($ffVer <= 2) {
			$gecko = '1.8.1';
			
		} elseif ($application == 'firefox' && $ffVer <= 3) {
			$gecko = '1.9';
			
		} elseif ($application == 'thunderbird' && $ffVer <= 3) {
			$gecko = '1.9.1';
			
		} elseif ($application == 'thunderbird' && $ffVer <= 3.1) {
			$gecko = '1.9.2';
			
		} elseif ($application == 'thunderbird' && $ffVer <= 3.3) {
			$gecko = '2';
			
		} elseif ($ffVer <= 3.5) {
			$gecko = '1.9.1';
			
		} elseif ($ffVer <= 3.6) {
			$gecko = '1.9.2';
			
		} elseif ($ffVer <= 4) {
			$gecko = '2';
			
		} else {
			$gecko = $ffVer;
		}
		
		return $gecko.$suffix;
	}

	/**
	 * Check if given line exists in manifest file.
	 * 
	 * @param string $line
	 * @param array $manifestLines Contents of manifest file in separate lines
	 * @return bool
	 */
	private function lineExistsInManifest($line, array $manifestLines) {
		$lineSegm = preg_split('/\s+/', trim($line));
		$countLineSegm = count($lineSegm);
		
		foreach ($manifestLines as $manLine) {
			$manLineSegm = preg_split('/\s+/', trim($manLine));
			
			if (count($manLineSegm) != $countLineSegm) {
				continue;
			}
			
			$isSame = true;
			
			for ($i = 0; $i < $countLineSegm; $i++) {
				if ($manLineSegm[$i] !== $lineSegm[$i]) {
					$isSame = false;
					break;
				}
			}
			
			if ($isSame) {
				return true;
			}
		}
		
		return false;
	}

	public function log($file, $msg) {
		$this->logMessages[$file][] = $msg;
	}
	
	public function getLogMessages() {
		return $this->logMessages;
	}
	
	public function logWarning($msg) {
		$this->logWarnings[] = $msg;
	}
	
	public function getWarnings() {
		return $this->logWarnings;
	}
	
	public function getConvertedDir() {
		return $this->convertedDir;
	}

	protected function createNewFileName($sourceFile) {
		$segm = pathinfo($sourceFile);
		
		return $segm['filename'] .'.' //. '-sm.'
			. $segm['extension'];
	}
	
	/**
	 * Extract XPI archive and extract all JAR files inside
	 * @param string $archiveFile
	 * @param string $extractDir
	 * @param bool $deleteJARs Whether to delete JAR files after extraction
	 * @throws Exception
	 */
	protected function extractXPI($archiveFile, $extractDir, $deleteJARs) {
		$zip = new ZipArchive;
		$result = $zip->open($archiveFile);

		if ($result !== true) {
			throw new Exception("Cannot read the XPI file");
		}

		if (!is_dir($extractDir)) {
			mkdir($extractDir);
		}

		if (!$zip->extractTo($extractDir)) {
			throw new Exception("Cannot extract archive");
		}
		$zip->close();
		
		$this->extractJARs($extractDir, $deleteJARs);
	}
	
	/**
	 * @param string $dir
	 * @param bool $deleteJARs Whether to delete JAR files after extraction
	 * @throws Exception
	 */
	protected function extractJARs($dir, $deleteJARs) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST);
		
		foreach ($iterator as $pathInfo) {
			if ($pathInfo->isFile() && strtolower($pathInfo->getExtension()) == 'jar') {
				$zip = new ZipArchive;
				$res = $zip->open($pathInfo->__toString());
				
				if ($res !== true) {
					throw new Exception("Cannot open JAR archive");
				}
				
				$path = $pathInfo->getPath();
				$tmpPath = "$path/~jartmp";
				mkdir($tmpPath);
				
				$res = $zip->extractTo($tmpPath);
				
				if ($res !== true) {
					throw new Exception("Cannot extract JAR archive");
				}
				
				if (!$deleteJARs) {
					
					$jarListFile = "$path/" . pathinfo($pathInfo->getFilename(), PATHINFO_FILENAME) . ".jarlist";
					$this->createJarListFile($jarListFile, $tmpPath);
				}
				
				$this->moveAllFiles($tmpPath, $path);
				
				$zip->close();
				
				
				// delete jar file
				unlink($pathInfo->__toString());
			}
		}
	}
	
	/**
	 * Create a file containing all files and folder in given dir
	 * @param string $destFile
	 * @param string $dir
	 */
	private function createJarListFile($destFile, $dir) {
		$jarList = "";
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);
		
		$dirLen = strlen($dir);
		
		foreach ($iterator as $pathInfo) {
			$localname = substr($pathInfo->__toString(), $dirLen + 1);
			
			$jarList .= $localname . "\n";
		}
		
		file_put_contents($destFile, $jarList);
	}
	
	/**
	 * Move all files from source dir to dest, deleting all source files
	 * and folders
	 * @param string $sourceDir
	 * @param string $destDir
	 */
	private function moveAllFiles($sourceDir, $destDir) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);
		
		foreach ($iterator as $name => $pathInfo) {
			$startsAt = substr(dirname($name), strlen($sourceDir));
			$currentDestDir = $destDir.$startsAt;
			
			if (!is_dir($currentDestDir)) {
				mkdir($currentDestDir);
			}
			
			if ($pathInfo->isFile()) {
				copy((string) $name, "$currentDestDir/" . basename($name));
			}
		}
		
		// delete source
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::CHILD_FIRST);

		foreach ($iterator as $file => $fileInfo) {
			if (strpos($file, "tmp/convert/") !== 0) {
				exit("Unsafe file delete attempt!");
			}

			if ($fileInfo->isDir()) {
				rmdir($file);

			} else {
				unlink($file);
			}
		}

		rmdir($sourceDir);
	}

	/**
	 * ZIP all directory with files and folders. Compress appropriate folders
	 * to JARs.
	 * @param string $dir
	 * @param string $destFile
	 * @throws Exception
	 */
	protected function zipDir($dir, $destFile) {
		
		$this->zipFilesIntoJars($dir);
		
		$zip = new ZipArchive;
		$res = $zip->open($destFile, ZipArchive::CREATE);
		
		if (!$res) {
			throw new Exception("Cannot open ZipArchive for writing");
		}
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);

		$dirLen = strlen($dir);
		
		foreach ($iterator as $pathInfo) {
			$localname = substr($pathInfo->__toString(), $dirLen + 1);
			
			if ($pathInfo->isDir()) {
				$zip->addEmptyDir($localname);
			} else {
				$zip->addFile($pathInfo->__toString(), $localname);
			}
		}
		
		$zip->close();
	}
	
	protected function zipFilesIntoJars($dir) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $pathInfo) {
			if ($pathInfo->isFile() && $pathInfo->getExtension() == 'jarlist') {
				// jarlist temp file contains all files to be put into jar
				$jarListFile = $pathInfo->__toString();
				$jarList = file($jarListFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				
				$path = $pathInfo->getPath();
				unlink($jarListFile);
				
				// zip all listed files and folders
				$jarFile = "$path/" . pathinfo($jarListFile, PATHINFO_FILENAME) . ".jar";
				
				$zip = new ZipArchive;
				$zip->open($jarFile, ZipArchive::CREATE);
				
				foreach ($jarList as $localname) {
					$localname = trim($localname);
					
					if ($localname === '') {
						continue;
					}
					
					$fileToJar = "$path/$localname";

					if (is_dir($fileToJar)) {
						$zip->addEmptyDir($localname);
						
						$extraDir = "$fileToJar/addonconverter";
						
						if (is_dir($extraDir)) {
							// zip extra directory added to the archive by this converter -
							// it is not included in .jarlist.
							$zip->addEmptyDir("$localname/addonconverter");
							$jarList[] = "$localname/addonconverter";
							
							foreach (scandir($extraDir) as $extraFilename) {
								$extraFile = "$extraDir/$extraFilename";
								
								if ($extraFilename[0] != '.' && is_file($extraFile)) {
									$zip->addFile("$extraDir/$extraFilename", "$localname/addonconverter/$extraFilename");
									$jarList[] = "$localname/addonconverter/$extraFilename";
								}
							}
							
						}
						
					} else {
						$zip->addFile($fileToJar, $localname);
					}
				}
				
				$zip->close();
				
				// delete jar'ed files recursively
				$iterator2 = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
					RecursiveIteratorIterator::CHILD_FIRST);
				
				$dirLen = strlen($path);
				
				foreach ($iterator2 as $filename => $fileInfo) {
					$localname = substr($fileInfo->__toString(), $dirLen + 1);
					
					if (in_array($localname, $jarList)) {
						if ($fileInfo->isDir()) {
							rmdir($filename);

						} elseif ($filename != $jarListFile) {
							unlink($filename);
						}
					}
				}
			}
		}
	}
	
	/**
	 * Replace chrome:// URLs in all file with given extensions.
	 * 
	 * @param array $extensions
	 * @return int number of changed files
	 */
	protected function replaceChromeURLs(array $extensions) {
		if (!$extensions) {
			return 0;
		}
		
		$changedCount = 0;
		$dirLen = strlen($this->convertedDir);
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->convertedDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $pathInfo) {
			
			$ext = strtolower($pathInfo->getExtension());
			
			if ($pathInfo->isFile() && in_array($ext, $extensions) && $pathInfo->getFilename() != 'install.rdf') {
				
				$fileChanged = false;
				$contents = file_get_contents((string) $pathInfo);
				$newContents = $contents;
				
				if ($ext == 'xul') {
					$newContents = $this->replaceBrowserDTD($newContents);
				}
				
				$newContents = strtr($newContents, $this->chromeURLReplacements);
				$localname = substr($pathInfo->__toString(), $dirLen + 1);
				
				if ($contents !== $newContents) {
					$fileChanged = true;
					
					$this->log($localname, "Replaced <i>chrome://</i> URL's to those used in SeaMonkey");
				}
				
				// replace chrome URLs that have no equivalent in SM by including the
				// missing file in the modded extension and pointing the chrome URL
				// to it
				foreach ($this->missingChromeURLs as $filename => $url) {
					
					$newUrlData = $this->createChromeURLForMissingFile($url);
					
					if (!$newUrlData) {
						continue;
					}
					
					
					$newContents = str_replace($url, $newUrlData['chromeURL'], $newContents, $count);
					
					if ($count > 0) {
						$fileChanged = true;
						$this->includeMissingFile($filename, $newUrlData['contentDir']);
						
						$this->log($localname, "Included missing file in SeaMonkey for <i>$url</i>");
					}
				}
				
				if ($fileChanged) {
					file_put_contents((string) $pathInfo, $newContents);
					$changedCount++;
				}
			}
		}
		
		return $changedCount;
	}
	
	/**
	 * Replace browser.dtd with a few other dtd definitions in ENTITY.
	 * 
	 * @param string $content XML
	 * @return string changed XML
	 */
	private function replaceBrowserDTD($content) {
		$content = preg_replace_callback('#<!ENTITY\s+(.+)chrome://browser/locale/browser\.dtd([^>]*)>#', function($matches) {
			return "<!ENTITY $matches[1]chrome://global/locale/editMenuOverlay.dtd$matches[2]>\n"
				. "<!ENTITY $matches[1]chrome://navigator/locale/tabbrowser.dtd$matches[2]>\n"
				. "<!ENTITY $matches[1]chrome://communicator/locale/viewZoomOverlay.dtd$matches[2]>\n"
				. "<!ENTITY $matches[1]chrome://navigator/locale/navigatorOverlay.dtd$matches[2]>\n"
				. "<!ENTITY $matches[1]chrome://communicator/locale/contentAreaCommands.dtd$matches[2]>"
			;
		}, $content);
		
		return $content;
	}
	
	/**
	 * Create chrome:// URL for the given file to be included in the modded add-on
	 * 
	 * @param string $oldChromeURL
	 * @return array|NULL chromeURL and contentDir
	 */
	private function createChromeURLForMissingFile($oldChromeURL) {
		// get content dir from manufest
		$fp = @fopen($this->convertedDir ."/chrome.manifest", "rb");
		
		if ($fp === false) {
			return null;
		}
		
		$chromeURL = null;
		
		while (($line = fgets($fp, 4096)) !== false) {
			$trimLine = trim($line);
			
			if ($trimLine && $trimLine[0] != '#') {
				$segm = preg_split('/\s+/', $trimLine);

				if ($segm[0] == 'content') {
					// example:
					// content cookiemonster jar:chrome/cookiemonster.jar!/content/
					
					// remove jar:
					$contentDir = preg_replace('#^jar:#', '', $segm[2]);
					
					// remove /filename.jar!/
					$contentDir = trim(preg_replace('#/[^/]+?\.jar!/#', '/', $contentDir), '/');
					
					$filename = substr(strrchr($oldChromeURL, '/'), 1);
					$chromeURL = "chrome://$segm[1]/content/addonconverter/$filename";
					
					return array(
						'chromeURL' => $chromeURL,
						'contentDir' => $contentDir,
					);
				}
			}
		}
		
		return null;
	}
	
	/**
	 * Include the missing file in the modded add-on
	 * 
	 * @param string $filename File in the missing files directory
	 * @param string $contentDir Destination directory within extracted archive
	 */
	private function includeMissingFile($filename, $contentDir) {
		$destDir = $this->convertedDir ."/$contentDir/addonconverter";
		
		if (!is_dir($destDir)) {
			mkdir($destDir);
		}
		
		$destFilename = substr(strrchr($filename, '+'), 1);
		$destFile = "$destDir/$destFilename";
		
		if (!is_file($destFile)) {
			copy(self::MISSING_FILES_DIR . "/$filename", $destFile);

			$dirLen = strlen($this->convertedDir);
			$localname = substr($destFile, $dirLen + 1);

			$this->log($localname, "New file");
		}
	}

	/**
	 * Replace some IDs in XUL overlay files
	 * 
	 * @return int number of changed files
	 */
	protected function replaceXulIds() {
		
		$ids = array(
			'menu_ToolsPopup' => 'taskPopup',
			'menu_viewPopup' => 'menu_View_Popup',
			'menu_HelpPopup' => 'helpPopup',
			'msgComposeContext' => 'contentAreaContextMenu',
		);
		
		$replacements = array();
		
		foreach ($ids as $from => $to) {
			$replacements["'".$from."'"] = "'".$to."'";
			$replacements['"'.$from.'"'] = '"'.$to.'"';
		}

		
		$changedCount = 0;
		$dirLen = strlen($this->convertedDir);
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->convertedDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $pathInfo) {
			$ext = strtolower($pathInfo->getExtension());
			if ($pathInfo->isFile() && ($ext == 'xul' || $ext == 'xml')) {
				
				$contents = file_get_contents((string) $pathInfo);
				$newContents = strtr($contents, $replacements);
				
				// https://addons.mozilla.org/en-US/firefox/addon/progre/
				// Fx: http://mxr.mozilla.org/comm-central/source/mozilla/browser/base/content/urlbarBindings.xml#46
				// SM: http://mxr.mozilla.org/comm-central/source/mozilla/xpfe/components/autocomplete/resources/content/autocomplete.xml#40
				$newContents = preg_replace(
					'~<xul:popupset\s+anonid=["\']popupset["\']\s+class=["\']autocomplete-result-popupset["\']\s*/>~',
					'<xul:popupset><xul:panel type="autocomplete" anonid="popup" ignorekeys="true" noautofocus="true" level="top" xbl:inherits="for=id,nomatch"/></xul:popupset>',
					$newContents, -1, $count
				);
				
				if ($count > 0) {
					$newContents = preg_replace(
						'~<content\s+sizetopopup=["\']pref["\']\s*>~',
						'<content><children includes="menupopup"/>',
						$newContents
					);
				}
				
				if ($ext == 'xul') {
					$newContents = $this->replaceOtherXulContent($newContents);
				}
				
				if ($contents !== $newContents) {
					file_put_contents((string) $pathInfo, $newContents);
					
					$localname = substr($pathInfo->__toString(), $dirLen + 1);
					
					$this->log($localname, "Replaced ID's to those used in SeaMonkey");
					$changedCount++;
				}
			}
		}
		
		return $changedCount;
	}
	
	/**
	 * Do special replacements in XUL files only.
	 * @param string $content
	 * @return string
	 */
	protected function replaceOtherXulContent($content) {
		// https://addons.mozilla.org/en-US/firefox/addon/navigate-up/
		if (preg_match('/["\']navigateup-button["\']/', $content)) {
			// remove <image id="go-button"/>
			$content = preg_replace('~<image\s+id[ \t]*=[ \t]*["\']go-button["\']\s*/>~', '', $content);
			// remove padding from up button
			$content = preg_replace('~<image(.*?)(id[ \t]*=[ \t]*["\']navigateup-button["\'])~', '<image$1$2 style="padding: 0"', $content);
		}

		return $content;
	}

	/**
	 * Fix Firefox shortcuts in js files
	 * @return int number of changed files
	 */
	protected function fixJsShortcuts() {
		$changedCount = 0;
		$dirLen = strlen($this->convertedDir);
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->convertedDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $pathInfo) {
			$ext = strtolower($pathInfo->getExtension());
			if ($pathInfo->isFile() && ($ext == 'js' || $ext == 'jsm')) {
				$contents = file_get_contents((string) $pathInfo);
				$newContents = $this->replaceComponentsShortcuts($contents);
				
				if ($contents !== $newContents) {
					file_put_contents((string) $pathInfo, $newContents);
					
					$localname = substr($pathInfo->__toString(), $dirLen + 1);
					
					$this->log($localname, "Replaced shortcuts for Components");
					$changedCount++;
				}
			}
		}
		
		return $changedCount;
	}
	
//	/**
//	 * @param string $contents
//	 * @return string contents with prepended definitions
//	 */
//	private function replaceComponentsShortcuts($contents) {
//		$shortcuts = array(
//			'Cc' => 'Components.classes',
//			'Ci' => 'Components.interfaces',
//			'Cr' => 'Components.results',
//			'Cu' => 'Components.utils',
//		);
//		
//		// detect which shortcuts are used
//		$set = implode('|', array_keys($shortcuts));
//		
//		preg_match_all('/\b(' .$set. ')[\[.]/', $contents, $matches);
//		$found = array_unique($matches[1]);
//		
//		$definitions = "";
//		
//		foreach ($found as $shortcut) {
//			
//			if (preg_match('/\bconst\b.*?\b' .$shortcut. '\b.*?=/s', $contents)) {
//				// don't add if there is a 'const ...' declaration
//				continue;
//			}
//			
//			$definitions .= "if (typeof $shortcut == 'undefined') {\n"
//				. "  var $shortcut = " .$shortcuts[$shortcut]. ";\n"
//				. "}\n\n";
//		}
//		
//		return $definitions . $contents;
//	}
	
	/**
	 * @param string $contents
	 * @return string contents with replaced shortcuts
	 */
	private function replaceComponentsShortcuts($contents) {
		$shortcuts = array(
			'Cc' => 'Components.classes',
			'Ci' => 'Components.interfaces',
			'Cr' => 'Components.results',
			'Cu' => 'Components.utils',
		);
		
		$shortcuts2 = array(
			'Cc' => 'classes',
			'Ci' => 'interfaces',
			'Cr' => 'results',
			'Cu' => 'utils',
		);
		
		// detect which shortcuts are used
		$set = implode('|', array_keys($shortcuts));
		
		preg_match_all('/\b(' .$set. ')[\[.]/', $contents, $matches);
		$found = array_unique($matches[1]);
		
		$definitions = "";
		
		foreach ($found as $shortcut) {
			
			// const Cc = Components.classes;
			// var { classes: Cc, interfaces: Ci, utils: Cu } = Components;
			if (preg_match('/\b(const|var)\s*.*?\b' .$shortcut. '\s*=\s*' .$shortcuts[$shortcut]. '\b/s', $contents)
				|| preg_match('/\b(const|var)\s*\{[^}]*?\b' . $shortcuts2[$shortcut]. '\s*:\s*' . $shortcut . '\b/', $contents)) {
				// don't add if there is a const or var declaration
				continue;
			}
			
			$contents = preg_replace(
				'#\b' .$shortcut . '([.[])#',
				$shortcuts[$shortcut] .'$1',
				$contents);
		}
		
		return $definitions . $contents;
	}
	
	/**
	 * Fix Firefox keywords in js files
	 * @return int number of changed files
	 */
	protected function fixJsKeywords() {
		$changedCount = 0;
		$dirLen = strlen($this->convertedDir);
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->convertedDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $pathInfo) {
			$ext = strtolower($pathInfo->getExtension());
			$isXML = ($ext == 'xul' || $ext == 'xml');
			
			if ($pathInfo->isFile() && ($ext == 'js' || $ext == 'jsm' || $isXML)) {
				
				$contents = file_get_contents((string) $pathInfo);
				$newContents = $this->replaceJsKeywords($contents, $isXML);
				
				if ($contents !== $newContents) {
					file_put_contents((string) $pathInfo, $newContents);
					
					$localname = substr($pathInfo->__toString(), $dirLen + 1);
					
					$this->log($localname, "Made replacements in javascript");
					$changedCount++;
				}
			}
		}
		
		return $changedCount;
	}
	
	/**
	 * @param string $contents
	 * @param bool $isXML
	 * @return string contents with replaced keywords
	 */
	private function replaceJsKeywords($contents, $isXML) {
		if (0 && $isXML) {
			// for the moment we disable this because js can occur within
			// XML attributes and we don't want to miss that
			
			// in XML only do replacements in <script> sections
			$segments = preg_split('#(<script[^>]*>.+?</script>)#is', $contents, -1, PREG_SPLIT_DELIM_CAPTURE);
			
			$newContents = "";
			
			foreach ($segments as $key => $segm) {
				if ($key & 1) {
					// js is only in odd keys
					$segm = $this->replaceJsKeywordsInJsString($segm);
				}
				
				$newContents .= $segm;
			}
			
			return $newContents;
			
		} else {
			return $this->replaceJsKeywordsInJsString($contents);
		}
	}
	
	/**
	 * @param string $contents
	 * @return string contents with replaced keywords
	 */
	private function replaceJsKeywordsInJsString($contents) {
		$replacements = array(
			'@mozilla.org/browser/sessionstore;1' => '@mozilla.org/suite/sessionstore;1',
			'@mozilla.org/steel/application;1' => '@mozilla.org/smile/application;1',
			'@mozilla.org/fuel/application;1' => '@mozilla.org/smile/application;1',
			'fuelIApplication' => 'smileIApplication',
			'blockedPopupOptions' => 'popupNotificationMenu',
			'menu_ToolsPopup' => 'taskPopup',
			'menu_HelpPopup' => 'helpPopup',
			'addon-bar' => 'status-bar',
			'browser.search.context.loadInBackground' => 'browser.tabs.loadInBackground',
		);
		
		foreach ($replacements as $from => $to) {
			$tr = array(
				"'".$from."'" => "'".$to."'",
				'"'.$from.'"' => '"'.$to.'"',
			);
			
			$contents = strtr($contents, $tr);
		}
		
		// getBrowserSelection() is FF specific
		// eg.: https://addons.mozilla.org/en-US/firefox/addon/context-search/
		$contents = preg_replace(
			'/([^\w"\'.]{1}|window\.)getBrowserSelection[\t ]*\(([^)]*)\)/',
			'$1gContextMenu.searchSelected($2)',
			$contents);
		
		// replace Application.version to fetch gecko version
		$contents = preg_replace(
			'/\bApplication\.version\b/',
			'Components.classes["@mozilla.org/xre/app-info;1"].getService(Components.interfaces.nsIXULAppInfo).platformVersion',
			$contents);
		
		// replace other stuff
		$contents = preg_replace(
			'/document\.getElementById\(["\']tabContextMenu["\']\)/',
			'document.getAnonymousElementByAttribute(document.getElementById("content"), "anonid", "tabContextMenu")',
			$contents);
		
		$contents = preg_replace(
			'/\bgBrowser\.visibleTabs\b/',
			'gBrowser.tabs',
			$contents);
		
		// example: https://addons.mozilla.org/en-US/firefox/addon/undo-closed-tabs-button/
		$contents = preg_replace(
			'/\bgPrefService\b/',
			'Services.prefs',
			$contents);
		
		// example: https://addons.mozilla.org/en-US/firefox/addon/undo-closed-tabs-button/
		$contents = preg_replace(
			'/(?<![\w\.])undoCloseTab\(\)/',  // (?<!) = negative lookbehind
			'gBrowser.undoCloseTab(0)',
			$contents);
		
		$contents = preg_replace(
			'/(?<![\w\.])undoCloseTab\(/',
			'gBrowser.undoCloseTab(',
			$contents);
		
		// example: https://addons.mozilla.org/en-US/firefox/addon/removetabs/
		$contents = preg_replace(
			'/(?<![\w\.])gHomeButton.getHomePage\(\)/',
			'Components.classes["@mozilla.org/preferences-service;1"].getService(Components.interfaces.nsIPrefService).getComplexValue("browser.startup.homepage", Components.interfaces.nsISupportsString).data',
			$contents);
		
		// example: https://addons.mozilla.org/en-US/firefox/addon/googlesearch-by-image/
		// or: https://addons.mozilla.org/en-US/firefox/addon/quickcontextsearch/
		// replace: openLinkIn(url, where, params)
		// to: openUILinkIn(url, where, params.allowThirdPartyFixup, params.postData)
		$contents = preg_replace_callback(
			'/([^\w"\'.]?|window\.)openLinkIn\(([^,]+),([^,)]+)([^)]*)\)/',
			function($matches) {
				$ret = "$matches[1]openUILinkIn($matches[2],$matches[3]";

				if (isset($matches[4])) {
					$ret .= "$matches[4].allowThirdPartyFixup";
					$ret .= "$matches[4].postData";
				}

				$ret .= ")";
				return $ret;
			},
			$contents);
		
		// example: https://addons.mozilla.org/en-US/firefox/addon/greasemonkey/
		// replace id for 'sidebar' with 'sidebar-box'
		$contents = preg_replace(
			'/\bgetElementById\s*\((["\'])sidebar(["\'])\)/',
			'getElementById($1sidebar-box$2)',
			$contents);
		
		$contents = preg_replace(
			'#\bBrowserOpenAddonsMgr\(["\']addons://list/([\w-]*)["\']\)#',
			'window.toEM(\'addons://list/$1\')',
			$contents);
		
		// example: https://addons.mozilla.org/en-US/firefox/addon/yet-another-context-search/
		// contextMenuSearch -> searchSelected
		$contents = preg_replace(
			'/\.(getFormattedString|getString)(\s*\()(["\'])contextMenuSearch(\.accesskey)?(["\'])/',
			'.$1$2$3searchSelected$4$5',
			$contents, -1, $count);
		
		if ($count > 0) {
			// replace id for 'bundle_browser' with '$1contentAreaCommandsBundle$2'
			$contents = preg_replace(
				'/\bgetElementById\s*\((["\'])bundle_browser(["\'])\)/',
				'getElementById($1contentAreaCommandsBundle$2)',
				$contents);
		}
		
		// comment out Components.utils.import("resource:///modules/devtools/scratchpad-manager.jsm");
		// because it doesn't exist in SM (used by Greasemonkey)
		$contents = preg_replace(
			'#([\w.]+.import\(["\']resource:///modules/devtools/scratchpad-manager.jsm["\']\))#',
			'// $1',
			$contents);
		
		// example: https://addons.mozilla.org/en-US/firefox/addon/secure-login/
		// translate Fx version to SM version, exapmle:
		//    .compare(this.getAppInfo().version, '2.*')
		// -> .compare(this.getAppInfo().platformVersion, '1.1')
		
		// example2: https://addons.mozilla.org/en-US/firefox/addon/image-picker/
		// var isUpperV31 = versionChecker.compare(appInfo.version, "31") > 0;
		
		$contents = preg_replace_callback(
			'/(\.compare\s*\(.+?\.)version(\s*,\s*["\'])(\d[\d.*]*)(["\'])/', 
			function ($matches) {
				return
					$matches[1]
					. 'platformVersion'
					. $matches[2]
					. $this->translateAppToPlatformVersion($matches[3], 'firefox')
					. $matches[4];
			}, $contents);
		
		
		// gInitialPages.push() -> gInitialPages.add()
		$contents = preg_replace(
			'#\b((?:window\.|\s)?gInitialPages\.)push\b#',
			'$1add',
			$contents);
		
		// getBoolPref( -> GetBoolPref(
		// window.getBoolPref( -> window.GetBoolPref(
		// example: https://addons.mozilla.org/en-US/firefox/addon/liveclick/
		$contents = preg_replace_callback(
			'#((function\s*)?[ \t=!&(\[\{:,?|]|\bwindow[ \t]*\.[ \t]*)getBoolPref([ \t]*\()#',
			function($matches) {
				if ($matches[2] !== "") {
					// don't replace if this is function definition
					return $matches[0];
				}
				
				return $matches[1]
					. 'GetBoolPref'
					. $matches[3];
			},
			$contents);

		// gBrowser.getTabForBrowser(browser) ->
		// _getTabForContentWindow(browser.contentWindow)
		// example: https://addons.mozilla.org/en-US/firefox/addon/noise-control/
		$contents = preg_replace(
			'#gBrowser\.getTabForBrowser\s*\(([^)]+)\)#',
			'gBrowser._getTabForContentWindow($1.contentWindow)',
			$contents);

		
		// getAnonymousElementByAttribute(xulTab, "class", "tab-content") ->
		// getAnonymousElementByAttribute(xulTab, "class", "tab-middle box-inherit")
		// example: https://addons.mozilla.org/en-US/firefox/addon/noise-control/
		$contents = preg_replace(
			'#(getAnonymousElementByAttribute\s*\([^)]+?["\'])tab-content(["\'])#',
			'$1tab-middle box-inherit$2',
			$contents);

		
		// potentially in https://addons.mozilla.org/en-US/firefox/addon/toomanytabs-saves-your-memory/
		// but broken addon, anyway
//		$contents = preg_replace(
//			'/\bgContextMenu(\.|\[)/',
//			'new nsContextMenu(document.getElementById("contentAreaContextMenu"))$1',
//			$contents);
		
		return $contents;
	}
	
	/**
	 * @return int 1 if dir removed, 0 if META-INF was not found
	 */
	protected function removeMetaInfDir() {
		$metaDir = "$this->convertedDir/META-INF";
		
		if (is_dir($metaDir)) {
			foreach (scandir($metaDir) as $filename) {
				$file = "$metaDir/$filename";
				
				if (is_file($file)) {
					unlink($file);
				}
			}
			
			@rmdir($metaDir);
			$this->log("/META-INF", "Removed META-INF folder to remove certificate check on installation");
			return 1;
		}
		
		return 0;
	}

}
