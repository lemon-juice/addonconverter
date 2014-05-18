<?php
class AddOnConverter {
	
	// config:
	public $maxVersionStr = '2.*';
	
	/**
	 * List of file extensions, in which to replace chrome URL's
	 */
	public $convertChromeURLsInExt = array();
	
	public $xulIds = false;
	public $jsShortcuts = false;
	public $jsKeywords = false;
	// end config.

	protected $sourceFile;
	protected $originalDir;
	protected $convertedDir;
	protected $logMessages = array();
	protected $chromeURLReplacements;

	/**
	 * @var DOMDocument
	 */
	protected $installRdf;
	
	const SEAMONKEY_ID = '{92650c4d-4b8e-4d2a-b7eb-24ecf4f6b63a}';
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
		
		$this->chromeURLReplacements = array(
			'chrome://browser/content/browser.xul' => 'chrome://navigator/content/navigator.xul',
			'chrome://browser/content/pageinfo/pageInfo.xul' => 'chrome://navigator/content/pageinfo/pageInfo.xul',
			'chrome://browser/content/preferences/permissions.xul' => 'chrome://communicator/content/permissions/permissionsManager.xul',
			'chrome://browser/content/bookmarks/bookmarksPanel.xul' => 'chrome://communicator/content/bookmarks/bm-panel.xul',
			'chrome://browser/content/places/places.xul' => 'chrome://communicator/content/bookmarks/bookmarksManager.xul',
			'chrome://browser/content/' => 'chrome://navigator/content/',
		);
	}
	
	/**
	 * @param string $destDir
	 * @return string|NULL URL path to converted file for download or NULL
	 *    if no conversion was done
	 */
	public function convert($destDir) {
		$modified = false;
		
		$newInstallRdf = $this->convertInstallRdf($this->installRdf, $this->maxVersionStr);
		
		if ($newInstallRdf) {
			// write modified file
			file_put_contents($this->convertedDir ."/install.rdf", $newInstallRdf->saveXML());
			unset($newInstallRdf);
			$modified = true;
		}
		
		
		$filesConverted = $this->convertManifest('chrome.manifest');

		if ($filesConverted > 0) {
			$modified = true;
		}
		
		$filesConverted = $this->replaceChromeURLs($this->convertChromeURLsInExt);

		if ($filesConverted > 0) {
			$modified = true;
		}
		
		if ($this->xulIds) {
			$filesConverted = $this->replaceXulIds();
		}

		if ($filesConverted > 0) {
			$modified = true;
		}
		
		if ($this->jsShortcuts) {
			$filesConverted = $this->fixJsShortcuts();
		}

		if ($filesConverted > 0) {
			$modified = true;
		}
		
		if ($this->jsKeywords) {
			$filesConverted = $this->fixJsKeywords();
		}

		if ($filesConverted > 0) {
			$modified = true;
		}
		
		if ($modified) {
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
		$Descriptions = $installRdf->documentElement->getElementsByTagNameNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "Description");
		
		$topDescription = null;

		foreach ($Descriptions as $d) {
			$about = $d->getAttributeNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "about");
			if (!$about) {
				$about = $d->getAttribute("about");
			}
			
			if ($about == "urn:mozilla:install-manifest") {
				$topDescription = $d;
				break;
			}
		}

		if (!$topDescription) {
			return null;
		}
		
		$docChanged = false;
		$SM_exists = false;
		
		foreach ($topDescription->getElementsByTagName("targetApplication") as $ta) {
			$Description = $ta->getElementsByTagName("Description")->item(0);
			
			if (!$Description) {
				continue;
			}
			
			$id = $Description->getElementsByTagName("id")->item(0);

			if (!$id) {
				continue;
			}
			
			if ($id->nodeValue == self::SEAMONKEY_ID) {
				// change maxVersion
				$SM_exists = true;
				
				$maxVersion = $Description->getElementsByTagName("maxVersion")->item(0);
				if (!$maxVersion) {
					// maxVersion missing
					$maxVersion = $this->installRdf->createElementNS("http://www.mozilla.org/2004/em-rdf#", "maxVersion", $maxVersionStr);
					
					$this->log("<a>install.rdf</a>: Added missing maxVersion");
					$docChanged = true;
					
				} elseif ($maxVersion && $maxVersion->nodeValue != $maxVersionStr) {
					$this->log("<a>install.rdf</a>: Changed <em>maxVersion</em> from '$maxVersion->nodeValue' to '$maxVersionStr'");
					
					$maxVersion->nodeValue = $maxVersionStr;
					$docChanged = true;
				}
				
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
			$topDescription->appendChild($tApp);
			
			$this->log("<a>install.rdf</a>: Added SeaMonkey to list of supported applications");
			$docChanged = true;
		}
		
		return $docChanged ? $installRdf : null;
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
						$newLine = $this->createNewManifestLine($trimLine);
						break;
				}
			}
			
			$newManifest .= $line;

			if ($newLine && !$this->lineExistsInManifest($newLine, $manifestContentLines)) {
				$newManifest .= $newLine;
				$this->log("Added new line to <a>$manifestFileName</a>: '$newLine'");
				$isConverted = true;
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
	 * Take existing manifest line and if it contains firefox-specific data
	 * then return new seamonkey-specific line. Otherwise, return empty string.
	 * 
	 * @param string $originalLine
	 * @retutn string
	 */
	private function createNewManifestLine($originalLine) {
		$convertedLine = strtr($originalLine, $this->chromeURLReplacements);
		
		if ($convertedLine != $originalLine) {
			$convertedLine = $this->fixManifestAppVersionLine($convertedLine);
			return $convertedLine ."\n";
		} else {
			return '';
		}
	}
	
	/**
	 * Fix appversion flag in manufest file: convert it to platformversion
	 * @param string $line
	 * @return string
	 */
	private function fixManifestAppVersionLine($line) {
		$segments = preg_split('/(\s+)/', trim($line), -1, PREG_SPLIT_DELIM_CAPTURE);
		$newLine = "";
		
		foreach ($segments as $key => $lineSegm) {
			if (strpos($lineSegm, 'appversion') === 0) {
				$flagSegm = preg_split('/(\s*[<=>]+\s*)/', $lineSegm, 3, PREG_SPLIT_DELIM_CAPTURE);

				if (isset($flagSegm[2])) {
					$flagSegm[2] = $this->translateAppToPlatformVersion($flagSegm[2]);
					
					$flagSegm[0] = 'platformversion';
					$lineSegm = implode('', $flagSegm);
				}
			}
			
			$newLine .= $lineSegm;
		}
		
		return $newLine;
	}
	
	/**
	 * Translate Firefox appversion to Gecko platformversion number.
	 * See https://developer.mozilla.org/en-US/docs/Mozilla/Gecko/Versions
	 * @param string $appVer app version number, may contain * at the end,
	 *    e.g. 3.6.*
	 * @return string
	 */
	private function translateAppToPlatformVersion($appVer) {
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
			
		} elseif ($ffVer <= 3) {
			$gecko = '1.9';
			
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

	protected function log($msg) {
		$this->logMessages[] = $msg;
	}
	
	public function getLogMessages() {
		return $this->logMessages;
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
				
				$res = $zip->extractTo($pathInfo->getPath());
				
				if ($res !== true) {
					throw new Exception("Cannot extract JAR archive");
				}
				
				$zip->close();
				
				if ($deleteJARs) {
					unlink($pathInfo->__toString());
				}
			}
		}
	}
	
	/**
	 * ZIP all directory with files and folders. Compress appropriate folders
	 * to JARs.
	 * @param string $dir
	 * @param string $destFile
	 * @throws Exception
	 */
	protected function zipDir($dir, $destFile) {
		
		$this->zipJars($dir);
		
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
	
	protected function zipJars($dir) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $pathInfo) {
			if ($pathInfo->isFile() && strtolower($pathInfo->getExtension()) == 'jar') {
				$jarFile = $pathInfo->__toString();
				
				// zip all files and folders in this dir except for the jar
				$path = $pathInfo->getPath();
				unlink($jarFile);
				
				$zip = new ZipArchive;
				$zip->open($jarFile, ZipArchive::CREATE);
				
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
					RecursiveIteratorIterator::SELF_FIRST);

				$dirLen = strlen($path);

				foreach ($iterator as $pathInfo) {
					$localname = substr($pathInfo->__toString(), $dirLen + 1);

					if ($pathInfo->isDir()) {
						$zip->addEmptyDir($localname);
					} else {
						$zip->addFile($pathInfo->__toString(), $localname);
					}
				}
				
				$zip->close();
				
				// delete jar'ed files recursively
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
					RecursiveIteratorIterator::CHILD_FIRST);
				
				foreach ($iterator as $filename => $fileInfo) {
					if ($fileInfo->isDir()) {
						rmdir($filename);
					
					} elseif ($filename != $jarFile) {
						unlink($filename);
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
			if ($pathInfo->isFile() && in_array(strtolower($pathInfo->getExtension()), $extensions) && $pathInfo->getFilename() != 'install.rdf') {
				$contents = file_get_contents((string) $pathInfo);
				$newContents = strtr($contents, $this->chromeURLReplacements);
				
				if ($contents !== $newContents) {
					file_put_contents((string) $pathInfo, $newContents);
					
					$localname = substr($pathInfo->__toString(), $dirLen + 1);
					
					$this->log("<a>$localname</a>: replaced chrome: URL's to those used in SeaMonkey");
					$changedCount++;
				}
			}
		}
		
		return $changedCount;
	}
	
	/**
	 * Replace some IDs in XUL overlay files
	 * 
	 * @return int number of changed files
	 */
	protected function replaceXulIds() {
		
		$changedCount = 0;
		$dirLen = strlen($this->convertedDir);
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->convertedDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $pathInfo) {
			if ($pathInfo->isFile() && strtolower($pathInfo->getExtension()) == 'xul') {
				
				$contents = file_get_contents((string) $pathInfo);
				$newContents = strtr($contents, array(
					"'msgComposeContext'" => "'contentAreaContextMenu'",
					'"msgComposeContext"' => '"contentAreaContextMenu"',
				));
				
				if ($contents !== $newContents) {
					file_put_contents((string) $pathInfo, $newContents);
					
					$localname = substr($pathInfo->__toString(), $dirLen + 1);
					
					$this->log("<a>$localname</a>: replaced ID's to those used in SeaMonkey");
					$changedCount++;
				}
			}
		}
		
		return $changedCount;
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
				$newContents = $this->addJsShortcutConstants($contents);
				
				if ($contents !== $newContents) {
					file_put_contents((string) $pathInfo, $newContents);
					
					$localname = substr($pathInfo->__toString(), $dirLen + 1);
					
					$this->log("<a>$localname</a>: added definitions for javascript shortcuts, which are not available in SeaMonkey");
					$changedCount++;
				}
			}
		}
		
		return $changedCount;
	}
	
	/**
	 * @param string $contents
	 * @return string contents with prepended definitions
	 */
	private function addJsShortcutConstants($contents) {
		$shortcuts = array(
			'Cc' => 'Components.classes',
			'Ci' => 'Components.interfaces',
			'Cr' => 'Components.results',
			'Cu' => 'Components.utils',
		);
		
		// detect which shortcuts are used
		$set = implode('|', array_keys($shortcuts));
		
		preg_match_all('/\b(' .$set. ')[\[.]/', $contents, $matches);
		$found = array_unique($matches[1]);
		
		$definitions = "";
		
		foreach ($found as $shortcut) {
			
			if (preg_match('/\bconst\b.*?\b' .$shortcut. '\b.*?=/s', $contents)) {
				// don't add if there is a 'const ...' declaration
				continue;
			}
			
			$definitions .= "if (typeof $shortcut == 'undefined') {\n"
				. "  var $shortcut = " .$shortcuts[$shortcut]. ";\n"
				. "}\n\n";
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
			if ($pathInfo->isFile() && ($ext == 'js' || $ext == 'jsm')) {
				$contents = file_get_contents((string) $pathInfo);
				$newContents = $this->replaceJsKeywords($contents);
				
				if ($contents !== $newContents) {
					file_put_contents((string) $pathInfo, $newContents);
					
					$localname = substr($pathInfo->__toString(), $dirLen + 1);
					
					$this->log("<a>$localname</a>: replaced some javascript keywords");
					$changedCount++;
				}
			}
		}
		
		return $changedCount;
	}
	
	/**
	 * @param string $contents
	 * @return string contents with replaced keywords
	 */
	private function replaceJsKeywords($contents) {
		$replacements = array(
			'@mozilla.org/browser/sessionstore;1' => '@mozilla.org/suite/sessionstore;1',
			'blockedPopupOptions' => 'popupNotificationMenu',
			'bookmarksMenuPopup' => 'menu_BookmarksPopup',
			'menu_ToolsPopup' => 'taskPopup',
			'menu_HelpPopup' => 'helpPopup',
		);
		
		foreach ($replacements as $from => $to) {
			$tr = array(
				"'".$from."'" => "'".$to."'",
				'"'.$from.'"' => '"'.$to.'"',
			);
			
			$contents = strtr($contents, $tr);
		}
		
		return $contents;
	}

}
