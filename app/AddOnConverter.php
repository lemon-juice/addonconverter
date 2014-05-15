<?php
class AddOnConverter {
	
	// config:
	public $maxVersionStr = '2.*';
	
	/**
	 * List of file extensions, in which to replace chrome URL's
	 */
	public $convertChromeURLsInExt = array();
	
	public $jsShortcuts = false;
	// end config.

	protected $sourceFile;
	protected $extractDir;
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
		
		$this->extractDir = dirname($sourceFile) . "/extracted";
		$this->extractXPI($sourceFile, $this->extractDir);
		
		if (!is_file($this->extractDir ."/install.rdf")) {
			throw new Exception("install.rdf not found in installer");
		}
		
		$this->installRdf = new DOMDocument();
		$this->installRdf->preserveWhiteSpace = false;
		$this->installRdf->formatOutput = true;
		$result = @$this->installRdf->load($this->extractDir ."/install.rdf");
		
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
			file_put_contents($this->extractDir ."/install.rdf", $newInstallRdf->saveXML());
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
		
		if ($this->jsShortcuts) {
			$filesConverted = $this->fixJsShortcuts();
		}

		if ($filesConverted > 0) {
			$modified = true;
		}
		
		if ($modified) {
			// ZIP files
			$filename = $this->createNewFileName($this->sourceFile);
			$destFile = "$destDir/$filename";
			
			$this->zipDir($this->extractDir, $destFile);
			
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
					
					$this->log("install.rdf: Added missing maxVersion");
					$docChanged = true;
					
				} elseif ($maxVersion && $maxVersion->nodeValue != $maxVersionStr) {
					$this->log("install.rdf: Changed <em>maxVersion</em> from '$maxVersion->nodeValue' to '$maxVersionStr'");
					
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
			
			$this->log("install.rdf: Added SeaMonkey to list of supported applications");
			$docChanged = true;
		}
		
		return $docChanged ? $installRdf : null;
	}
	
	/**
	 * Convert chrome.manifest and any included manifest files
	 * 
	 * @return int number of converted files
	 */
	protected function convertManifest($manifestFileName) {
		$manifestFile = $this->extractDir ."/$manifestFileName";
		
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
				$this->log("Added new line to $manifestFileName: '$newLine'");
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
			return $convertedLine ."\n";
		} else {
			return '';
		}
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
	 * @param string $archiveFile
	 * @param string $extractDir
	 * @throws Exception
	 */
	protected function extractXPI($archiveFile, $extractDir) {
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
		
		$this->extractJARs($extractDir);
	}
	
	/**
	 * Extract XPI archive and extract all JAR files inside
	 * @param type $dir
	 */
	protected function extractJARs($dir) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $pathInfo) {
			if ($pathInfo->isFile() && strtolower($pathInfo->getExtension()) == 'jar') {
				$zip = new ZipArchive;
				$zip->open($pathInfo->__toString());
				$zip->extractTo($pathInfo->getPath());
				$zip->close();
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
			throw new Exception("Cannot open ZipArchive");
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
		$dirLen = strlen($this->extractDir);
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->extractDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $pathInfo) {
			if ($pathInfo->isFile() && in_array(strtolower($pathInfo->getExtension()), $extensions) && $pathInfo->getFilename() != 'install.rdf') {
				$contents = file_get_contents((string) $pathInfo);
				$newContents = strtr($contents, $this->chromeURLReplacements);
				
				if ($contents !== $newContents) {
					file_put_contents((string) $pathInfo, $newContents);
					
					$localname = substr($pathInfo->__toString(), $dirLen + 1);
					
					$this->log("$localname: replaced chrome: URL's to those used in SeaMonkey");
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
		$dirLen = strlen($this->extractDir);
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->extractDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $pathInfo) {
			if ($pathInfo->isFile() && strtolower($pathInfo->getExtension() == 'js')) {
				$contents = file_get_contents((string) $pathInfo);
				$newContents = $this->addJsShortcutConstants($contents);
				
				if ($contents !== $newContents) {
					file_put_contents((string) $pathInfo, $newContents);
					
					$localname = substr($pathInfo->__toString(), $dirLen + 1);
					
					$this->log("$localname: added definitions for javascript shortcuts, which are not available in SeaMonkey");
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
	protected function addJsShortcutConstants($contents) {
		$shortcuts = array(
			'Cc' => 'Components.classes',
			'Ci' => 'Components.interfaces',
			'Cr' => 'Components.results',
			'Cu' => 'Components.utils',
		);
		
		// detect which shortcuts are used
		$set = implode('|', array_keys($shortcuts));
		
		preg_match_all('/\b(?:' .$set. ')\b/', $contents, $matches);
		$found = array_unique($matches[0]);
		
		$definitions = "";
		
		foreach ($found as $shortcut) {
			
			if (preg_match('/\bconst[ \t]+' .$shortcut. '\b/', $contents)) {
				// don't add if there is a 'const ...' declaration
				continue;
			}
			
			$definitions .= "if (typeof $shortcut == 'undefined') {\n"
				. "  var $shortcut = " .$shortcuts[$shortcut]. ";\n"
				. "}\n\n";
		}
		
		return $definitions . $contents;
	}
}
