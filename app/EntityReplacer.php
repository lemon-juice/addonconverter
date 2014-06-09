<?php
class EntityReplacer {
	
	protected $AddOnConverter;
	protected $entityReplacements;
	protected $fullEntityReplacements = array();


	public function __construct(AddOnConverter $AddOnConverter) {
		$this->AddOnConverter = $AddOnConverter;
		
		$this->entityReplacements = array(
			'menuRestoreAllTabs.label' => 'Restore All Tabs',
		);
		
		foreach ($this->entityReplacements as $key => $replacement) {
			$this->fullEntityReplacements["&$key;"] = $replacement;
		}
	}
	
	/**
	 * @return int Number of changed files
	 */
	public function replaceEntities() {
		$changedCount = 0;
		$convertedDir = $this->AddOnConverter->getConvertedDir();
		$dirLen = strlen($convertedDir);
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($convertedDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $pathInfo) {
			$ext = strtolower($pathInfo->getExtension());
			
			if ($pathInfo->isFile() && preg_match('/^(xul|xml|rdf|js|jsm)$/', $ext)) {
				$contents = file_get_contents((string) $pathInfo);
				$newContents = $this->replaceEntitiesInFile($contents);
				
				if ($contents !== $newContents) {
					file_put_contents((string) $pathInfo, $newContents);
					
					$localname = substr($pathInfo->__toString(), $dirLen + 1);
					
					$this->AddOnConverter->log($localname, "Replaced entities, which are not available in SeaMonkey, with plain text");
					$changedCount++;
				}
			}
		}
		
		return $changedCount;
	}
	
	/**
	 * @param string $contents File contents
	 * @return string New contents
	 */
	private function replaceEntitiesInFile($contents) {
		$contents = strtr($contents, $this->fullEntityReplacements);
		
		// replace js calls like:
		// strings.getString("menuRestoreAllTabs.label"))
		foreach ($this->entityReplacements as $entity => $str) {
			$entity = preg_quote($entity);
			
			$contents = preg_replace('/([\s,+])[\w."\']*?\.getString\(["\']' .$entity. '["\']\)/', "$1\"$str\"", $contents);
		}
		
		return $contents;
	}

}
