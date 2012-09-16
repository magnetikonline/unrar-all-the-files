# unrarallthefiles

Scans a given/current source directory for rar archives, including multi part (spanned) and extracts each found to a target folder using unrar binary. Reports extracted archive count and any errors encountered by unrar.

Correctly determines the starting rar file in multi part archives, in the following formats (the only two I am aware of)

	- archive.rar
	- archive.r01
	- archive.r02
	- archive.r0X

and...

	- archive.part01.rar
	- archive.part02.rar
	- archive.part03.rar
	- archive.part0X.rar

## Requires
+ PHP 5.4 (using short ([]) array syntax)
+ `/usr/bin/unrar`
+ Tested/used under Linux (Ubuntu) - should be fine with other *nix shells

## Usage
Also shown by running `unrarallthefiles.php` without required command line option(s).

	php unrarallthefiles.php -t[dir] -s[dir] -v --dry-run

	<Required>
	  -t[dir]       Target directory for unrared files

	<Optional>
	  -s[dir]       Source directory to scan (current working directory if omitted)
	  -v            Increase verbosity of unrar, otherwise silent operation
	  --dry-run     Simulation of process, won't attempt to unrar archives
