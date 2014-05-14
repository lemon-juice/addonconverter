<?php
class AddOnConverter {
	
	protected $sourceFile;
	protected $extractDir;


	/**
	 * @param string $sourceFile
	 * @throws Exception
	 */
	public function __construct($sourceFile) {
		$zip = new ZipArchive;
		$result = $zip->open($sourceFile);

		if ($result !== true) {
			throw new Exception("Cannot read the XPI file");
		}

		$this->extractDir = dirname($sourceFile) . "/extracted";
		mkdir($this->extractDir);

		if (!$zip->extractTo($this->extractDir)) {
			throw new Exception("Cannot extract archive");
		}
		$zip->close();
	}
	
	public function convert($destDir) {
		
	}
}
