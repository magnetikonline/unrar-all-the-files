<?php
// unrarallthefiles.php



class UnrarAllTheFiles {

	const LE = "\n";
	const RAR_FILEEXT = '/\.(rar|r\d{2,3})$/';
	const RAR_PART = '/\.part\d{1,3}$/i';



	public function execute(array $argv) {

		// fetch command line options and validate given dirs - exit on error
		if (
			(($optionList = $this->getOptions($argv)) === false) ||
			(!$this->validateDirs($optionList))
		) exit(1);

		// work over source dir recursively
		$extractFileCount = $this->workSourceDir($optionList);

		// all done
		$this->writeLine(sprintf(
			self::LE . 'All done - %d file%s extracted',
			$extractFileCount,
			($extractFileCount > 1) ? 's' : ''
		));

		exit(0);
	}

	private function buildTargetExtractDir($sourceDir,$targetDir,$singleRarFileSet,$baseRarName) {

		// remove $sourceDir from beginning of $baseRarName
		$baseRarName = $this->truncatePrefix($baseRarName,$sourceDir);

		// check generated extract dir does not exist on disk
		if ($singleRarFileSet) $baseRarName = dirname($baseRarName);
		if (!is_dir($extractDir = $targetDir . $baseRarName)) return $extractDir;

		// ...extract dir does exist, keep adding digits to suffix until unique
		$seqCount = 1;
		while (true) {
			$extractDir = sprintf('%s%s-%02d',$targetDir,$baseRarName,$seqCount);
			if (!is_dir($extractDir)) return $extractDir;
			$seqCount++;
		}
	}

	private function unrarFileSet(array $optionList,$singleRarFileSet,$baseRarName,array $rarFileSetList) {

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
			$optionList['sourceDir'],$optionList['targetDir'],
			$singleRarFileSet,$baseRarName
		);

		// display source rar file and target extract path
		$this->writeLine(
			$this->truncatePrefix($firstRarFile,$optionList['sourceDir']) . ' => ' .
			$targetExtractDir . (($optionList['verbose']) ? self::LE : '')
		);

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

	private function getBaseRarName($filename) {

		if (!preg_match(self::RAR_FILEEXT,$filename)) {
			// not a rar file - discard
			return false;
		}

		// strip off file ext and possible '.partXXX'
		return preg_replace([self::RAR_FILEEXT,self::RAR_PART],'',$filename);
	}

	private function getGroupedFileList(array $fileList) {

		// group all rar files by their 'base'
		$groupedFileList = [];
		foreach ($fileList as $fileItem) {
			// if not a rar file, skip
			if (($baseRarName = $this->getBaseRarName($fileItem)) === false) continue;

			// add rar file to $groupedFileList
			if (!isset($groupedFileList[$baseRarName])) $groupedFileList[$baseRarName] = [];
			$groupedFileList[$baseRarName][] = $fileItem;
		}

		return $groupedFileList;
	}

	private function workSourceDir(array $optionList,$parentDir = false) {

		$extractFileCount = 0;
		$baseDir = ($parentDir === false) ? $optionList['sourceDir'] : $parentDir;
		$handle = opendir($baseDir);

		$fileList = [];
		while (($fileItem = readdir($handle)) !== false) {
			// skip '.' and '..'
			if (($fileItem == '.') || ($fileItem == '..')) continue;
			$fileItem = $baseDir . '/' . $fileItem;

			// if dir found call again recursively
			if (is_dir($fileItem)) {
				$extractFileCount += $this->workSourceDir($optionList,$fileItem);
				continue;
			}

			// add item to $fileList
			$fileList[] = $fileItem;
		}

		closedir($handle);
		if (!$fileList) return;

		// process file list, looking for rar sets
		if (!($groupedFileList = $this->getGroupedFileList($fileList))) return;

		// if only a single rar file set found, dont extract into individual sub dirs
		$singleRarFileSet = (sizeof($groupedFileList) == 1);

		// now work over grouped rar file list sets
		foreach ($groupedFileList as $baseRarName => $rarFileSetList) {
			$this->unrarFileSet(
				$optionList,$singleRarFileSet,
				$baseRarName,$rarFileSetList
			);

			$extractFileCount++;
		}

		// return total extracted file count
		return $extractFileCount;
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
			'sourceDir' => rtrim((isset($optionList['s'])) ? $optionList['s'] : __DIR__,'/'),
			'targetDir' => rtrim($optionList['t'],'/'),
			'verbose' => isset($optionList['v']),
			'dryRun' => isset($optionList['dry-run'])
		];
	}

	private function writeLine($text,$isError = false) {

		echo((($isError) ? 'Error: ' : '') . $text . self::LE);
	}

	private function truncatePrefix($val,$truncate) {

		$len = strlen($truncate);
		return (substr($val,0,$len) == $truncate)
			? substr($val,$len)
			: $val;
	}
}


$unrarAllTheFiles = new UnrarAllTheFiles();
$unrarAllTheFiles->execute($argv);
