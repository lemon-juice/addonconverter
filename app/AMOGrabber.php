<?php
class AMOGrabber {
	
	
	/**
	 * @param string $url Full URL to extension on addons.mozilla.org
	 * @param string $destDir Path to existing dir where to save fetched file
	 * @return string Path and filename to saved file
	 * @throws Exception
	 */
	public function fetch($url, $destDir) {
		//if (!preg_match('#^https://addons.mozilla.org/#', $url)) {
		if (!preg_match('#^https?://.+#', $url)) {
			throw new Exception("This URL is incorrect. Make sure to provide the whole URL including http:// or https:// part.");
		}
		
		$source = @file_get_contents($url);
		
		if ($source === false) {
			throw new Exception("Couldn't fetch file from remote server");
		}
		
		// check max filesize
		$maxMB = 16;
		
		if (strlen($source) > 1024 * 1024 * $maxMB) {
			throw new Exception("Input file too large. Maximum $maxMB MB is allowed");
		}
		
		
		if (preg_match('#^https://addons.mozilla.org/#i', $url)) {
			return $this->fetchXPIFromAMO($source, $url, $destDir);
			
		} else {
			// assume this is the target XPI
			$isZip = substr($source, 0, 2) == 'PK';

			if ($isZip) {
				$filename = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
				$destFile = "$destDir/$filename";

				file_put_contents($destFile, $source);
				return $destFile;
				
			} else {
				throw new Exception("Incorrect file format");
			}
		}
	}

	/**
	 * @param source $source
	 * @param source $url
	 * @param source $destDir where to save fetched file
	 * @return string Path and filename to saved file
	 * @throws Exception
	 */
	protected function fetchXPIFromAMO($source, $url, $destDir) {
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
		
		// fetch remote file
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $downloadUrl);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		$xpi = curl_exec($ch);
		$error = curl_error($ch);
		$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		curl_close($ch);
		
		if ($xpi === false) {
			throw new Exception("Error fetching remote XPI: $error");
		}
		
		$filename = pathinfo(parse_url($effectiveUrl, PHP_URL_PATH), PATHINFO_BASENAME);
		
		$destFile = "$destDir/$filename";
		
		if (!@file_put_contents($destFile, $xpi)) {
			throw new Exception("Cannot save to local file");
		}

		return $destFile;
	}

	protected function relativeToAbsoluteURL($relUrl, $baseUrl) {
		$baseInfo = parse_url($baseUrl);
		$base = $baseInfo['scheme'] . "://"
			. $baseInfo['host'];
		
		if ($relUrl[0] == '/') {
			return $base . $relUrl;
		
		} else {
			return $base . $baseInfo['path'] . $baseUrl;
		}
	}
}