<?php
class AMOGrabber {
	
	protected $maxFileSize;
	protected $cacheLifetime;
	protected $cacheDir;
	
	const USER_AGENT = "Add-on Converter for SeaMonkey";


	/**
	 * @param int $maxFileSize
	 */
	public function __construct($maxFileSize) {
		$this->maxFileSize = $maxFileSize;
		
		// installers from AMO are cached for a while so that subsequent
		// conversions don't require downloading again
		$this->cacheLifetime = 30 * 60;
		$this->cacheDir = "tmp/remote-cache";
		
		if (!is_dir($this->cacheDir)) {
			mkdir($this->cacheDir);
		}
		
		$this->clearCache();
	}
	
	/**
	 * @param string $url Full URL to extension on addons.mozilla.org
	 * @param string $destDir Path to existing dir where to save fetched file
	 * @return string Path to saved file
	 * @throws Exception
	 */
	public function fetch($url, $destDir) {
		if (!preg_match('#^https?://.+#', $url)) {
			throw new Exception("This URL is incorrect. Make sure to provide the whole URL including http:// or https:// part.");
		}
		
		$isAMOUrl = preg_match('#^https://addons.mozilla.org/#i', $url);
		
		if ($isAMOUrl) {
			// attempt to load file from cache
			$pathToFile = $this->fetchXPIFromCache($url, $destDir);
			
			if ($pathToFile !== null) {
				return $pathToFile;
			}
		}
		
		try {
			$file = $this->fetchRemoteFile($url);
			
		} catch (IOException $ex) {
			throw new Exception("Couldn't fetch file from remote server");
		}
		
		$source = $file['content'];
		
		// check max filesize
		if (strlen($source) > $this->maxFileSize) {
			$maxMB = round($this->maxFileSize / 1024 / 1024, 1);
			throw new Exception("Input file too large. Maximum $maxMB MB is allowed");
		}
		
		$isZip = substr($source, 0, 2) == 'PK';
		
		
		if ($isAMOUrl && !$isZip) {
			// html page at AMO
			$pathToFile = $this->fetchXPIFromAMO($source, $url, $destDir);
			$this->saveFileInCache($pathToFile, $url, basename($pathToFile));
			
			return $pathToFile;
			
		} else {
			// assume this is the target XPI
			if ($isZip) {
				$filename = $this->urlToFilename($url);
				$destFile = "$destDir/$filename";

				file_put_contents($destFile, $source);
				return $destFile;
				
			} else {
				throw new Exception("Incorrect file format");
			}
		}
	}

	/**
	 * Fetch XPI from page on AMO.
	 * 
	 * @param source $source HTML source of add-on page on AMO
	 * @param source $url URL of $source
	 * @param source $destDir where to save fetched file
	 * @return string Path to saved file
	 * @throws Exception
	 */
	protected function fetchXPIFromAMO($source, $url, $destDir) {
		
		$downloadUrl = $this->getDownloadUrlFromAMOPage($source, $url);
		
		// fetch remote file
		try {
			$file = $this->fetchRemoteFile($downloadUrl);
		} catch (IOException $ex) {
			throw new Exception("Error fetching remote XPI: " . $ex->getMessage());
		}
		
		if (substr($file['content'], 0, 2) != 'PK') { // not ZIP archive
			
			// the button can lead to proper download page (in extensions
			// asking for contributions) - try parsing it and fetching XPI again
			try {
				$downloadUrl = $this->getDownloadUrlFromAMOPage($file['content'], $url);
				
				// fetch remote file
				try {
					$file = $this->fetchRemoteFile($downloadUrl);
				} catch (IOException $ex) {
					throw new Exception("Error fetching remote XPI: " . $ex->getMessage());
				}

				if (substr($file['content'], 0, 2) != 'PK') {
					throw new Exception;
				}
				
			} catch (Exception $ex) {
				throw new Exception("The link under download button on the AMO page doesn't lead to XPI file. You may need to provide a link to the target download page or directly to the XPI file.");
			}
		}
		
		if (strlen($file['content']) > $this->maxFileSize) {
			$maxMB = round($this->maxFileSize / 1024 / 1024, 1);
			throw new Exception("Remote XPI file too large. Maximum $maxMB MB is allowed");
		}
		
		$filename = $this->urlToFilename($file['effectiveUrl']);
		
		$destFile = "$destDir/$filename";
		
		if (!@file_put_contents($destFile, $file['content'])) {
			throw new Exception("Cannot save to local file");
		}

		return $destFile;
	}

	protected function relativeToAbsoluteURL($relUrl, $baseUrl) {
		
		if (preg_match('#^https?://#i', $relUrl)) {
			// given url is already absolute - nothing to do
			return $relUrl;
		}
		
		$baseInfo = parse_url($baseUrl);
		$base = $baseInfo['scheme'] . "://"
			. $baseInfo['host'];
		
		if ($relUrl[0] == '/') {
			return $base . $relUrl;
		
		} else {
			return $base . $baseInfo['path'] . $baseUrl;
		}
	}
	
	protected function saveFileInCache($pathToFile, $url, $filename) {
		$cacheFile = "$this->cacheDir/" . sha1($url). ".$filename";
		
		copy($pathToFile, $cacheFile);
	}

	/**
	 * @param string $url
	 * @param string $destDir
	 * @return string|null Path to saved file or NULL if file from cache
	 *    couldn't be fetched
	 */
	protected function fetchXPIFromCache($url, $destDir) {
		$files = glob("$this->cacheDir/" .sha1($url). ".*");
		
		if (!$files) {
			return null;
		}
		
		$cacheFile = $files[0];
		
		if (@filemtime($cacheFile) < time() - $this->cacheLifetime) {
			// no cache available
			return null;
		}
		
		$filename = basename($files[0]);
		$filename = substr($filename, strpos($filename, '.') + 1);
		
		$destFile = "$destDir/$filename";
		
		copy($cacheFile, $destFile);
		return $destFile;
	}
	
	protected function clearCache() {
		$expiry = time() - $this->cacheLifetime;
		
		foreach (scandir($this->cacheDir) as $filename) {
			$file = "$this->cacheDir/$filename";
			
			if ($filename[0] != '.' && is_file($file) && filemtime($file) < $expiry) {
				unlink($file);
			}
		}
	}
	
	protected function urlToFilename($url) {
		return pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
	}
	
	/**
	 * @param string $url
	 * @return array [content,effectiveUrl]
	 * @throws IOException
	 */
	protected function fetchRemoteFile($url) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);

		$content = curl_exec($ch);
		$error = curl_error($ch);
		$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		curl_close($ch);
		
		if ($content === false) {
			throw new IOException($error);
		}
		
		return array(
			'content' => $content,
			'effectiveUrl' => $effectiveUrl,
		);
	}
	
	/**
	 * Get URL from the download button on AMO
	 * 
	 * @param string $source HTML source of page
	 * @param string $url URL of page
	 * @throws Exception
	 */
	protected function getDownloadUrlFromAMOPage($source, $url) {
		$doc = new DOMDocument;
		$result = @$doc->loadHTML($source);
		
		if (!$result) {
			throw new Exception("Error parsing remote AMO page");
		}
		
		$ps = $doc->getElementsByTagName('p');
		$installElem = null;
		
		foreach ($ps as $p) {
			if ($p->getAttribute('class') == 'install-button') {
				$installElem = $p;
				break;
			}
		}
		
		if (!$installElem) {
			throw new Exception("Couldn't find download link container on remote AMO page");
		}
		
		$link = $installElem->getElementsByTagName('a')->item(0);
		
		if (!$link) {
			throw new Exception("Couldn't find download link on remote AMO page");
		}
		
		$downloadUrl = $this->relativeToAbsoluteURL($link->getAttribute('href'), $url);
		return $downloadUrl;
	}
}