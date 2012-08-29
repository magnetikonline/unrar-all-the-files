<?php
// unrarallthefiles.php



class UnrarAllTheFiles {

	const LE = "\n";
	const RAR_FILEEXT = '/\.(rar|r\d{2,3})$/';
	const RAR_PART = '/\.part\d{1,3}$/i';



	public function execute(array $argv) {

		// fetch options and validate given dirs - exit on error
		if (($optionList = $this->getOptions($argv)) === false) return;
		if (!$this->validateDirs($optionList)) return;

		// work over source dir recursively
		$this->workSourceDir($optionList);

		// all done
		$this->writeLine('All done!');
	}

	private function buildTargetExtractDir($singleRarFileSet,$sourceDir,$targetDir,$fileDirPart) {

		// cut source dir off beginning of $fileDirPart
		$sourceDirLen = strlen($sourceDir);
		$fileDirPart = (substr($fileDirPart,0,$sourceDirLen) == $sourceDir)
			? substr($fileDirPart,$sourceDirLen)
			: $fileDirPart;

		// ensure generated extract dir does not exist on disk
		if ($singleRarFileSet) $fileDirPart = dirname($fileDirPart);
		if (!is_dir($extractDir = $targetDir . $fileDirPart)) return $extractDir;

		// extract dir does exist, keep adding digits to suffix until unique on disk
		$seqCount = 1;
		while (true) {
			$extractDir = sprintf('%s%s-%02d',$targetDir,$fileDirPart,$seqCount);
			if (!is_dir($extractDir)) return $extractDir;
			$seqCount++;
		}
	}

	private function unrarFileSet(array $optionList,$singleRarFileSet,$groupBaseDir,array $rarFileSetList) {

		// determine the first rar file in set
		$firstRarFile = false;
		$rarFilenameCount = 0;
		foreach ($rarFileSetList as $fileSetItem) {
			if (substr($fileSetItem,-4) == '.rar') {
				if ($rarFilenameCount > 0) {
					// more than one '.rar' file found
					$firstRarFile = false;
					break;
				}

				$rarFilenameCount++;
				$firstRarFile = $fileSetItem;
			}
		}

		if ($firstRarFile === false) {
			// can't find a single .rar file so order filelist - the first sorted item will be our starting archive
			sort($rarFileSetList,SORT_STRING);
			$firstRarFile = $rarFileSetList[0];
		}

		// have now determined the first rar file in set, build target extract dir
		$targetExtractDir = $this->buildTargetExtractDir(
			$singleRarFileSet,
			$optionList['sourceDir'],$optionList['targetDir'],$groupBaseDir
		);

		$this->writeLine($firstRarFile . ' => ' . $targetExtractDir . self::LE);

		if (!$optionList['dryRun']) {
			// create target dir and unrar archive
			mkdir($targetExtractDir,0777,true);
			system(sprintf(
				'unrar e %s"%s" "%s"',
				($optionList['verbose']) ? '' : '-inul ',
				$firstRarFile,
				$targetExtractDir
			));
		}
	}

	private function getBaseRarFileName($filename) {

		if (!preg_match(self::RAR_FILEEXT,$filename)) {
			// not a rar file - discard
			return false;
		}

		// strip off file ext and possible '.partXXX' bit
		return preg_replace([self::RAR_FILEEXT,self::RAR_PART],'',$filename);
	}

	private function getGroupedFileList(array $fileList) {

		// group all rar files by their 'base'
		$groupedFileList = [];
		foreach ($fileList as $fileItem) {
			// if not a rar file, skip
			if (($baseFileItem = $this->getBaseRarFileName($fileItem)) === false) continue;

			if (!isset($groupedFileList[$baseFileItem])) $groupedFileList[$baseFileItem] = [];
			$groupedFileList[$baseFileItem][] = $fileItem;
		}

		return $groupedFileList;
	}

	private function workSourceDir(array $optionList,$readDir = false) {

		$baseDir = ($readDir === false) ? $optionList['sourceDir'] : $readDir;
		$handle = opendir($baseDir);

		$fileList = [];
		while (($fileItem = readdir($handle)) !== false) {
			// skip '.' and '..'
			if (($fileItem == '.') || ($fileItem == '..')) continue;
			$fileItem = $baseDir . '/' . $fileItem;

			// if dir found call again recursively
			if (is_dir($fileItem)) {
				$this->workSourceDir($optionList,$fileItem);
				continue;
			}

			// add item to $fileList
			$fileList[] = $fileItem;
		}

		closedir($handle);
		if (!$fileList) return;

		// process file list, looking for rar sets
		$groupedFileList = $this->getGroupedFileList($fileList);
		if (!$groupedFileList) return;

		// if only a single rar file set found, dont extract into unique sub dirs (no need)
		$singleRarFileSet = (sizeof($groupedFileList) == 1);

		// now work over grouped rar file list sets
		foreach ($groupedFileList as $groupBaseDirItem => $rarFileSetList) {
			$this->unrarFileSet(
				$optionList,$singleRarFileSet,
				$groupBaseDirItem,$rarFileSetList
			);
		}
	}

	private function validateDirs(array $optionList) {

		// source dir
		if (!is_dir($optionList['sourceDir'])) {
			$this->writeLine('Invalid source dir - ' . $optionList['sourceDir'],true);
			return false;
		}

		// target dir (only if not dry run)
		$targetDir = $optionList['targetDir'];
		if ((!$optionList['dryRun']) && (!is_dir($targetDir))) {
			// target dir does not exist, attempt to create it
			if (!@mkdir($targetDir,0777,true)) {
				$this->writeLine('Unable to create target dir - ' . $targetDir,true);
				return false;
			}
		}

		// all good
		return true;
	}

	private function getOptions(array $argv) {

		$optionList = getopt('s:t:v',['dry-run']);

		// are all required options given?
		if (!isset($optionList['t'])) {
			// no - display usage
			$this->writeLine(
				'Usage:  php ' . $argv[0] . ' -t[dir] -s[dir] -v --dry-run' . self::LE . self::LE .
				'<Required>' . self::LE .
				'  -t[dir]       Target directory for unrared files' . self::LE . self::LE .
				'<Optional>' . self::LE .
				'  -s[dir]       Source directory to scan (current working directory if omitted)' . self::LE .
				'  -v            Increase verbosity of unrar, otherwise silent operation' . self::LE .
				'  --dry-run     Simulation of process, won\'t attempt to unrar archives' . self::LE
			);

			return false;
		}

		// return options
		return [
			'sourceDir' => (isset($optionList['s'])) ? rtrim($optionList['s'],'/') : __DIR__,
			'targetDir' => rtrim($optionList['t'],'/'),
			'verbose' => isset($optionList['v']),
			'dryRun' => isset($optionList['dry-run'])
		];
	}

	private function writeLine($text,$isError = false) {

		echo((($isError) ? 'Error: ' : '') . $text . self::LE);
	}
}


$unrarAllTheFiles = new UnrarAllTheFiles();
$unrarAllTheFiles->execute($argv);
