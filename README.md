# unrarallthefiles
Scans a given/current source directory for rar archives, including multi part (spanned) and extracts each found to a target folder using unrar binary. Reports extracted archive count and any errors encountered by unrar.

Correctly determines the starting rar file in multi part archives, in the following formats (the only two I am aware of).

	- archive.rar
	- archive.r01
	- archive.r02
	- archive.rXX
	- archive.s01
	- archive.s02
	- archive.sXX

and...

	- archive.part01.rar
	- archive.part02.rar
	- archive.part03.rar
	- archive.partXX.rar

## Requires
- PHP 5.4 (using short ([]) array syntax).
- `/usr/bin/unrar`
- Tested/used under Linux (Ubuntu) - but should be 100% with other *nix variants and OSX.

## Usage
Also shown by running `unrarallthefiles.php` without command line option(s).

	Usage:
	  unrarallthefiles.php -t DIR -s DIR -v --dry-run

	Required:
	  -t DIR     Target directory for unrared files

	Optional:
	  -s DIR     Source directory to scan (current working directory if omitted)
	  -v         Increase verbosity of unrar, otherwise silent operation
	  --dry-run  Simulation of process, won't attempt to unrar archives
