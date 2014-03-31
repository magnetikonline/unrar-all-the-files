#!/usr/bin/env php
<?php
class UnrarAllTheFiles {

	const LE = "\n";
	const RAR_FILEEXT = '/\.(rar|r\d{2,3})$/';
	const RAR_PART = '/\.part\d{1,3}$/i';


	public function __construct(array $argv) {

		// fetch command line options and validate given dirs - exit on error
		if (
			(($optionList = $this->getOptions($argv)) === false) ||
			(!$this->validateDirs($optionList))
		) exit(1);

		// work over source dir recursively
		list($extractArchiveCount,$extractErrorList) = $this->workSourceDir($optionList);

		// all done
		$this->displayReport($extractArchiveCount,$extractErrorList);
		exit(($extractErrorList) ? 1 : 0);
	}

	private function getUnrarErrorMessage($returnCode) {

		$errorMessageList = [
			1	=> 'Non fatal error(s) occurred',
			2	=> 'A fatal error occurred',
			3	=> 'A CRC error occurred when unpacking',
			4	=> 'Attempt to modify an archive previously locked by the \'k\' command',
			5	=> 'Write to disk error',
			6	=> 'Open file error',
			7	=> 'Command line option error',
			8	=> 'Not enough memory for operation',
			9	=> 'Create file error',
			10	=> 'You need to start extraction from a previous volume to unpack',
			255 => 'User stopped the process'
		];

		return (isset($errorMessageList[$returnCode]))
			? $errorMessageList[$returnCode]
			: 'Unknown error code: ' . $returnCode;
	}

	private function displayReport($extractArchiveCount,array $extractErrorList) {

		// processed archive count
		$summaryText = sprintf(
			'All done - %d archive%s processed',
			$extractArchiveCount,
			($extractArchiveCount != 1) ? 's' : ''
		);

		if ($extractErrorList) {
			// display error count
			$summaryText .= sprintf(
				', %d error%s',
				count($extractErrorList),
				(count($extractErrorList) > 1) ? 's' : ''
			);
		}

		$this->writeLine(
			self::LE . str_repeat('=',strlen($summaryText)) . self::LE .
			$summaryText
		);

		if ($extractErrorList) {
			// display listing of archive errors
			$this->writeLine(self::LE . 'Archive errors encountered:');
			foreach ($extractErrorList as $errorItem) {
				list($rarFile,$errorCode) = $errorItem;
				$this->writeLine($rarFile . ' => ' . $this->getUnrarErrorMessage($errorCode));
			}
		}

		$this->writeLine();
	}

	private function buildTargetExtractDir($targetDir,$targetDirSub,$singleRarFileSet) {

		// check generated extract dir does not exist on disk
		if ($singleRarFileSet) {
			// if only a single rar file set in current work dir remove final path from target dir - but only if more than one sub-path
			$targetDirSubParent = dirname($targetDirSub);
			if ($targetDirSubParent != '/') $targetDirSub = $targetDirSubParent;
		}

		if (!is_dir($extractDir = $targetDir . $targetDirSub)) return $extractDir;

		// ...extract dir already exists, keep adding digits to suffix until unique
		$retryCount = 1;
		while (true) {
			$extractDir = sprintf('%s%s-%02d',$targetDir,$targetDirSub,$retryCount);
			if (!is_dir($extractDir)) return $extractDir;
			$retryCount++;
		}
	}

	private function unrarArchive(array $optionList,$rarFile,$baseRarName,$singleRarFileSet) {

		// have now determined the first rar file in set, build target extract dir
		$targetExtractDir = $this->buildTargetExtractDir(
			$optionList['targetDir'],
			$this->truncatePrefix($baseRarName,$optionList['sourceDir']),
			$singleRarFileSet
		);

		// display source rar file and target extract path
		$this->writeLine(
			$this->truncatePrefix($rarFile,$optionList['sourceDir']) . ' => ' .
			$targetExtractDir . (($optionList['verbose']) ? self::LE : '')
		);

		// if dry run, return success unrar status code
		if ($optionList['dryRun']) return 0;

		// create target dir and unrar archive
		mkdir($targetExtractDir,0777,true);
		system(
			sprintf(
				'unrar e %s"%s" "%s"',
				($optionList['verbose']) ? '' : '-inul ',
				$rarFile,
				$targetExtractDir
			),
			$unrarReturnCode
		);

		return $unrarReturnCode;
	}

	private function getStartingRarFileFromSet(array $rarFileSetList) {

		$startingFile = false;
		$fileCount = 0;
		foreach ($rarFileSetList as $item) {
			if (substr($item,-4) == '.rar') {
				if ($fileCount > 0) {
					// more than one '.rar' file found
					$startingFile = false;
					break;
				}

				$fileCount++;
				$startingFile = $item;
			}
		}

		if ($startingFile === false) {
			// not a single .rar file in set - order filelist - first item will be starting file
			sort($rarFileSetList,SORT_STRING);
			$startingFile = $rarFileSetList[0];
		}

		return $startingFile;
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

		$extractArchiveCount = 0;
		$extractErrorList = [];

		$baseDir = ($parentDir === false) ? $optionList['sourceDir'] : $parentDir;
		$handle = opendir($baseDir);
		$fileList = [];

		while (($fileItem = readdir($handle)) !== false) {
			// skip '.' and '..'
			if (($fileItem == '.') || ($fileItem == '..')) continue;
			$fileItem = $baseDir . '/' . $fileItem;

			// if dir found call again recursively
			if (is_dir($fileItem)) {
				list($archiveCount,$errorList) = $this->workSourceDir($optionList,$fileItem);
				$extractArchiveCount += $archiveCount;
				$extractErrorList = array_merge($extractErrorList,$errorList);

				continue;
			}

			// add item to $fileList
			$fileList[] = $fileItem;
		}

		closedir($handle);
		if (!$fileList) return [$extractArchiveCount,$extractErrorList];

		// process file list, looking for rar sets
		if (!($groupedFileList = $this->getGroupedFileList($fileList))) return [$extractArchiveCount,$extractErrorList];

		// if only a single rar file set found, dont extract into individual sub dirs
		$singleRarFileSet = (count($groupedFileList) == 1);

		// now work over grouped rar file list sets
		foreach ($groupedFileList as $baseRarName => $rarFileSetList) {
			// determine starting rar file from set and unrar archive
			$startingRarFile = $this->getStartingRarFileFromSet($rarFileSetList);
			$unrarReturnCode = $this->unrarArchive($optionList,$startingRarFile,$baseRarName,$singleRarFileSet);

			if ($unrarReturnCode == 0) {
				// success
				$extractArchiveCount++;

			} else {
				// error - store the starting rar file and error code
				$extractErrorList[] = [
					$this->truncatePrefix($startingRarFile,$optionList['sourceDir']),
					$unrarReturnCode
				];
			}
		}

		// total extracted file count & error list
		return [$extractArchiveCount,$extractErrorList];
	}

	private function validateDirs(array $optionList) {

		// source dir
		if (!is_dir($optionList['sourceDir'])) {
			$this->writeLine('Invalid source directory - ' . $optionList['sourceDir'],true);
			return false;
		}

		// target dir (only if not dry run)
		$targetDir = $optionList['targetDir'];
		if ((!$optionList['dryRun']) && (!is_dir($targetDir))) {
			// target dir does not exist, attempt to create it
			if (!@mkdir($targetDir,0777,true)) {
				$this->writeLine('Unable to create target directory - ' . $targetDir,true);
				return false;
			}
		}

		// all good
		return true;
	}

	private function getOptions(array $argv) {

		$optionList = getopt('s:t:v',['dry-run']);

		// required options given?
		if (!isset($optionList['t'])) {
			// no - display usage
			$this->writeLine(
				'Usage: ' . basename($argv[0]) . ' -t[dir] -s[dir] -v --dry-run' . self::LE . self::LE .
				'<Required>' . self::LE .
				'  -t[dir]      Target directory for unrared files' . self::LE . self::LE .
				'<Optional>' . self::LE .
				'  -s[dir]      Source directory to scan (current working directory if omitted)' . self::LE .
				'  -v           Increase verbosity of unrar, otherwise silent operation' . self::LE .
				'  --dry-run    Simulation of process, won\'t attempt to unrar archives' . self::LE
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

	private function writeLine($text = '',$isError = false) {

		echo((($isError) ? 'Error: ' : '') . $text . self::LE);
	}

	private function truncatePrefix($val,$truncate) {

		$len = strlen($truncate);
		return (substr($val,0,$len) == $truncate)
			? substr($val,$len)
			: $val;
	}
}


new UnrarAllTheFiles($argv);
