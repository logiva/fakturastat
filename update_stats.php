#!/usr/bin/php
<?php
if(isset($_SERVER['REMOTE_ADDR']))
	die("Not for web-access");

require_once("faktura_stat_db.class.php");
require_once("faktura_stat_faktura.class.php");
require_once("invoicestats.class.php");

function usage()
{
	echo "Usage: update_stats.php [options]\n";
	echo "Options:\n";
	echo "  -e date  Set end-date for update (default 'yesterday')\n";
	echo "  -h       This message\n";
	echo "  -s date  Set start-date for update (be careful!)\n";
	exit(1);
}

function mycheckdate($s)
{
	if(is_null($s))
		return null;
	$t = strtotime($s);
	if(version_compare(PHP_VERSION, "5.1.0") >= 0)
		$err = $t === false;
	else
		$err = $t === -1;
	if($err) {
		printf("Datestring '%s' is invalid\n", $s);
		exit(1);
	}
	return strftime("%Y-%m-%d", $t);
}

/*---------------------------------------------------------------------------*/

/* Get the commandline options */
if(false === ($opts = getopt("h")))
	usage();

if(isset($opts['h']))
	usage();

$dateend   = mycheckdate(isset($opts['e']) ? $opts['e'] : 'yesterday');
$datestart = mycheckdate(isset($opts['s']) ? $opts['s'] : null);

if(!is_null($datestart) && $datestart > $dateend) {
	printf("Error: Start date after end date\n");
	exit(1);
}

try {
	/* Go through all databases defined */
	$dblink = new faktura_stat_db;
	$dbs = $dblink->getAll(true);

	foreach($dbs as $db) {
		if($db->ignore)
			continue;

		/* If never updated; set last update one day before we created the database statistics */
		if(is_null($db->lastupdate))
			$db->lastupdate = strftime("%Y-%m-%d", strtotime($db->startdate . " -1 day"));

		/* This is where we want to start */
		$tagdate = strftime("%Y-%m-%d", strtotime($db->lastupdate . " +1 day"));

		/* But if the commandline says later, we'll comply */
		if(!is_null($datestart) && $datestart > $db->lastupdate) {
			printf("Warning: %s:%s:%s: Leaving gap in statistics from '%s' to '%s'\n",
				$db->dbhost, $db->dbport, $db->dbname, $db->lastupdate, $datestart);
			$tagdate = $datestart;
		}

		if($tagdate > $dateend) {
			printf("Warning: %s:%s:%s: Target up-to-date; not updating from '%s' to '%s'\n",
				$db->dbhost, $db->dbport, $db->dbname, $tagdate, $dateend);
			continue;
		}

		try {
			$target = new invoicestats($db->dbhost, $db->dbport, $db->dbname, $db->dbuser, $db->dbpass);
			$docs = $target->getDocStats($tagdate, $dateend);
			$usrs = $target->getUserStats($tagdate, $dateend, $db->oldstyle);
			$pdo = faktura_stat_pdo::getPDO();
			try {
				$pdo->beginTransaction();
				foreach($docs as $doc)
					faktura_stat_faktura::insert($db->id, $doc['tagdate'], $doc['org'], $doc['docname'], $doc['cnt']);
				foreach($usrs as $usr)
					faktura_stat_faktura::insert($db->id, $usr['tagdate'], $usr['org'], $usr['docname'], $usr['cnt']);
				$dblink->setLastupdate($db->id, $dateend);
				$pdo->commit();
			} catch (Exception $e) {
				$pdo->rollback();
				throw $e;
			}
		} catch (Exception $e) {
			printf("Error: Failed to handle database %s:%s:%s: %s\n", $db->dbhost, $db->dbport, $db->dbname, $e->getMessage());
		}
		$target = null;		/* Force destruction */
	}
} catch	(Exception $e) {
	printf("Error: Unhandled exception: %s\n%s\n", $e->getMessage(), $e->getTraceAsString());
	exit(1);
}

exit(0);
?>
