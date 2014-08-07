<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."lib/category.php");
require_once(WWW_DIR."lib/groups.php");
require_once(WWW_DIR."lib/nfo.php");
require_once(WWW_DIR."lib/site.php");
require_once ("ConsoleTools.php");
require_once ("namecleaner.php");
require_once("namefixer.php");
require_once ("functions.php");
require_once ("ColorCLI.php");

//this script is adapted from nZEDb decrypt_hashes.php
$c = new ColorCLI();
if (!isset($argv[1]) || ($argv[1] != "all" && $argv[1] != "full" && !is_numeric($argv[1]))) {
	exit($c->error(
		"\nThis script tries to match hashes of the releases.name or releases.searchname to predb hashes.\n"
		. "To display the changes, use 'show' as the second argument.\n\n"
		. "php hash_decrypt.php 1000		...: to limit to 1000 sorted by newest postdate.\n"
		. "php hash_decrypt.php full 		...: to run on full database.\n"
		. "php hash_decrypt.php all 		...: to run on all hashed releases(including previously renamed).\n"
	));
}

echo $c->header("\nHash Decrypt (${argv[1]}) Started at " . date('g:i:s'));
echo $c->primary("Matching prehash hashes to hash(releases.name or releases.searchname)");

preName($argv);

function preName($argv)
{
	$db = new DB();
	$timestart = TIME();
	$namefixer = new NameFixer();

	if (isset($argv[1]) && $argv[1] === "all") {
		$res = $db->queryDirect('SELECT ID AS releaseid, name, searchname, groupID, categoryID, dehashstatus FROM releases WHERE ishashed = 1 AND prehashID = 0');
	} else if (isset($argv[1]) && $argv[1] === "full") {
		$res = $db->queryDirect('SELECT ID AS releaseid, name, searchname, groupID, categoryID, dehashstatus FROM releases WHERE ishashed = 1 AND prehashID = 0 AND dehashstatus BETWEEN -6 AND 0');
	} else if (isset($argv[1]) && is_numeric($argv[1])) {
		$res = $db->queryDirect('SELECT ID AS releaseid, name, searchname, groupID, categoryID, dehashstatus FROM releases WHERE ishashed = 1 AND prehashID = 0 AND dehashstatus BETWEEN -6 AND 0 ORDER BY postdate DESC LIMIT ' . $argv[1]);
	}
	$c = new ColorCLI();

	$total = $res->rowCount();
	$counter = $counted = 0;
	$show = (!isset($argv[2]) || $argv[2] !== 'show') ? 0 : 1;
	if ($total > 0) {
		echo $c->header("\n" . number_format($total) . ' releases to process.');
		sleep(2);
		$consoletools = new ConsoleTools();

		foreach ($res as $row) {
			$success = 0;
			if (preg_match('/[a-fA-F0-9]{32,40}/i', $row['name'], $matches)) {
				$success = $namefixer->matchPredbHash($matches[0], $row, 1, 1, true, $show);
			} else if (preg_match('/[a-fA-F0-9]{32,40}/i', $row['searchname'], $matches)) {
				$success = $namefixer->matchPredbHash($matches[0], $row, 1, 1, true, $show);
			}

			if ($success === 0) {
				$db->queryDirect(sprintf('UPDATE releases SET dehashstatus = dehashstatus - 1 WHERE id = %d', $row['releaseid']));
			} else {
				$counted++;
			}
			if ($show === 0) {
				$consoletools->overWritePrimary("Renamed Releases: [" . number_format($counted) . "] " . $consoletools->percentString(++$counter, $total));
			}
		}
	}
	if ($total > 0) {
		echo $c->header("\nRenamed " . $counted . " releases in " . $consoletools->convertTime(TIME() - $timestart) . ".");
	} else {
		echo $c->info("\nNothing to do.");
	}
}