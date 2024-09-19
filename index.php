<?php
	require_once("functions.php");
	require_once("faktura_stat_db.class.php");
	require_once("faktura_stat_faktura.class.php");

$months = array("", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");

function fix_startdate($db, $proxy)
{
	if(!$proxy)
		return $db->startdate;
	if(is_null($db->visiblefrom) || trim($db->visiblefrom) == '')
		return strftime("%Y-%m-%d");
	else if($db->visiblefrom > $db->startdate)
		return $db->visiblefrom;
	return $db->startdate;
}

function get_stats($db, $issum, $aggr, $sd, $ed, &$a, &$st)
{
	if(!$sd)
		$sd = $db->startdate;
	if(!$ed)
		$ed = strftime("%Y-%m-%d");
	$ag = aggr_key($aggr);
	$stats = faktura_stat_faktura::getInterval($db->id, $sd, $ed, $issum, $ag);
	foreach($stats as $stat) {
		$k = $stat->stamp . $stat->org;
		if(!isset($a[$k]))
			$a[$k] = array('stamp' => $stat->stamp, 'org' => $stat->org);
		$a[$k][$stat->typename] = array($stat->mini, $stat->maxi, $stat->total);
		$st[$stat->typename] = 1;
	}
	ksort($a);
	ksort($st);
}

function print_stats($db, $issum, $str, $aggr, $proxy, $sd, $ed)
{
	$a = array();
	$st = array();
	get_stats($db, $issum, $aggr, $sd, $ed, $a, $st);
	if($proxy)
		echo "<h1>".he(sprintf("%s - %s", $db->dbname, $db->vhost))." - $str</h1>\n";
	else
		echo "<h1>".he(sprintf("%s:%d - %s", $db->dbhost, $db->dbport, $db->dbname))." - $str</h1>\n";
	echo "<table border=\"1\">\n";
	echo " <tr>\n";
	echo "  <th".($issum ? ' rowspan="2"' : "").">Timestamp</th>\n";
	echo "  <th".($issum ? ' rowspan="2"' : "").">Org</th>\n";
	foreach($st as $kk => $vv)
		echo "  <th".($issum ? ' colspan="2"' : "").">".he($kk)."</th>\n";
	echo "  <th".($issum ? ' rowspan="2"' : "").">Total</th>\n";
	echo " </tr>\n";
	if($issum) {
		echo " <tr>\n";
		foreach($st as $kk => $vv)
			echo "<td align=\"center\">min</td><td align=\"center\">max</td>\n";
		echo " </tr>\n";
	}
	foreach($a as $v) {
		echo " <tr>\n";
		$stamp = strtotime($v['stamp']);
		switch($aggr) {
		case 1:  $s = strftime("%Y uge %U", $stamp); break;
		case 2:  $s = strftime("%Y %b", $stamp); break;
		case 3:  $s = strftime("%Y", $stamp); break;
		default: $s = $v['stamp']; break;
		}
		echo "  <td>".he($s)."</td>\n";
		echo "  <td>".he($v['org'])."</td>\n";
		$sum = 0;
		foreach($st as $kk => $vv) {
			if(isset($v[$kk])) {
				if($issum) {
					echo "  <td class=\"num\">".sprintf("%d", $v[$kk][0])."</td><td class=\"num\">".sprintf("%d", $v[$kk][1])."</td>\n";
					$sum += $v[$kk][1];
				} else {
					echo "  <td class=\"num\">".sprintf("%d", $v[$kk][2])."</td>\n";
					$sum += $v[$kk][2];
				}
			} else {
				if($issum)
					echo "  <td>&nbsp;</td><td>&nbsp;</td>\n";
				else
					echo "  <td>&nbsp;</td>\n";
			}
		}
		echo "  <td class=\"num\">".sprintf("%d", $sum)."</td>\n";
		echo " </tr>\n";
	}
	echo "</table>\n";
}

function csv_stats($db, $issum, $aggr, $proxy)
{
	$a = array();
	$st = array();
	$sd = fix_startdate($db, $proxy);
	get_stats($db, $issum, $aggr, $sd, false, $a, $st);
	echo '"Year";"Month";"Day";"Week";"Org";';
	if($issum) {
		foreach($st as $kk => $vv)
			echo "\"$kk(min)\";\"$kk(max)\";";
	} else {
		foreach($st as $kk => $vv)
			echo "\"$kk\";";
	}
	echo "\"Total\"\n";
	foreach($a as $v) {
		$stamp = strtotime($v['stamp']);
		echo strftime("%Y;%m;%d;%U;", $stamp);
		echo '"'.$v['org'].'";';
		$sum = 0;
		foreach($st as $kk => $vv) {
			if(isset($v[$kk])) {
				if($issum) {
					printf("%d;%d;", $v[$kk][0], $v[$kk][1]);
					$sum += $v[$kk][1];
				} else {
					printf("%d;", $v[$kk][2]);
					$sum += $v[$kk][2];
				}
			} else {
				if($issum)
					echo "0;0;";
				else
					echo "0;";
			}
		}
		printf("%d\n", $sum);
	}
}

function get_all_stats($dbs, $ag, $month, $year, &$org, &$st) {
	if($ag == AG_YEARLY) {
		$sd = "$year-01-01";
		$ed = "$year-12-31";
	} else {
		$sd = "$year-$month-01";
		$ed = strftime("%Y-%m-%d", strtotime("$sd +1 month -1 day"));
	}
	$a = array();	/* All data */
	foreach($dbs as $db) {
		$stt = array();
		$at = array();
		get_stats($db, false, $ag, $sd, $ed, $at, $stt);
		$a = array_merge($a, array_values($at));
		foreach($stt as $k => $v)
			$st[$k] = false;

		$stt = array();
		$at = array();
		get_stats($db, true, $ag, $sd, $ed, $at, $stt);
		$a = array_merge($a, array_values($at));
		foreach($stt as $k => $v)
			$st[$k] = true;
	}
	foreach($a as $k => $v) {
		if(!isset($org[$v['org']]))
			$org[$v['org']] = array();
		foreach($st as $stk => $stv) {
			if(isset($v[$stk]))
			$org[$v['org']][$stk] = $v[$stk];
		}
	}
	ksort($org);
	ksort($st);
}

function print_all_stats($dbs, $ag, $month, $year)
{
	$org = array();	/* All org domains */
	$st = array();	/* All aggregate types */
	get_all_stats($dbs, $ag, $month, $year, $org, $st);

	echo "<h1>Alle DB statistik</h1>\n";
	echo "<table border=\"1\">\n";
	echo " <tr>\n";
	echo "  <th>Year</th>\n";
	if($ag == AG_MONTHLY)
		echo "  <th>Month</th>\n";
	echo "  <th>Org</th>\n";
	foreach($st as $stk => $stv) {
		if($stv)
			echo "  <th>".he($stk)."(min)</th><th>".he($stk)."(max)</th>\n";
		else
			echo "  <th>".he($stk)."</th>\n";
	}
	echo "  <th>FakturaTotal</th>\n";
	echo "  <th>BrugereTotal</th>\n";
	echo " </tr>\n";
	foreach($org as $k => $v) {
		echo " <tr>\n";
		echo "  <td>$year</td>\n";
		if($ag == AG_MONTHLY)
			echo "  <td>$month</td>\n";
		echo "  <td>".he($k)."</td>\n";
		$tott = 0;
		$totf = 0;
		foreach($st as $stk => $stv) {
			if($stv) {
				if(isset($v[$stk])) {
					printf("  <td class=\"num\">%d</td><td class=\"num\">%d</td>\n", $v[$stk][0], $v[$stk][1]);
					$tott += $v[$stk][1];
				} else
					echo "  <td class=\"num\">0</td><td class=\"num\">0</td>\n";
			} else {
				if(isset($v[$stk])) {
					printf("  <td class=\"num\">%d</td>\n", $v[$stk][2]);
					$totf += $v[$stk][2];
				} else
					echo "  <td class=\"num\">0</td>\n";
			}
		}
		printf("  <td class=\"num\">%d</td><td class=\"num\">%d</td>\n", $totf, $tott);
		echo " </tr>\n";
	}
	echo "</table>\n";
}

function all_csv_stats($dbs, $ag, $month, $year)
{
	$org = array();	/* All org domains */
	$st = array();	/* All aggregate types */
	get_all_stats($dbs, $ag, $month, $year, $org, $st);
	echo '"Year"';
	if($ag == AG_MONTHLY)
		echo ';"Month"';
	echo ';"Org"';
	foreach($st as $stk => $stv) {
		if($stv)
			echo ";\"$stk(min)\";\"$stk(max)\"";
		else
			echo ";\"$stk\"";
	}
	echo ";\"FakturaTotal\";\"BrugereTotal\"\n";
	foreach($org as $k => $v) {
		echo "$year";
		if($ag == AG_MONTHLY)
			echo ";$month";
		echo ";\"$k\"";
		$tott = 0;
		$totf = 0;
		foreach($st as $stk => $stv) {
			if($stv) {
				if(isset($v[$stk])) {
					printf(";%d;%d", $v[$stk][0], $v[$stk][1]);
					$tott += $v[$stk][1];
				} else
					echo ";0;0";
			} else {
				if(isset($v[$stk])) {
					printf(";%d", $v[$stk][2]);
					$totf += $v[$stk][2];
				} else
					echo ";0";
			}
		}
		printf(";%d;%d\n", $totf, $tott);
	}
}

function generate_dateset($s, $e)
{
	global $months;
	if(!$s || !$e)
		return array();
	$sd = explode("-", $s);
	$ed = explode("-", $e);
	$a = array();
	$sy = (int)$sd[0];
	$ey = (int)$ed[0];
	$sm = (int)$sd[1];
	$em = (int)$ed[1];
	if($sm < 1)  $sm = 1;
	if($sm > 12) $sm = 12;
	if($em < 1)  $em = 1;
	if($em > 12) $em = 12;
	$stop = sprintf("%04d-%02d", $ey, $em);
	do {
		$a[$tag = sprintf("%04d-%02d", $sy, $sm)] = sprintf("%04d-%s", $sy, $months[$sm]);
		$sm++;
		if($sm > 12) {
			$sm = 1;
			$sy++;
		}
	} while($tag < $stop);
	return $a;
}

/*---------------------------------------------------------------------------*/
	header("Cache-Control: no-cache, must-revalidate");
	header("Expires: Fri, 31 Dec 1999 23:59:59 GMT");

	$seldb = param("db", false);
	$aggre = param("ag", AG_MONTHLY);
	$csv   = param("csv", false);
	$usr   = param("usr", false);
	$sdate = param("sd", false);
	$edate = param("ed", false);
	$mdate = param("m", false);
	$ydate = param("y", false);
	$notcsv = param("notcsv", false);

	/* The proxy server forwards the real "Host:" header so we can match it here */
	$proxy = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_HOST'] : false;

	if($sdate) {
		if(!preg_match('/^\d{4}-\d{2}$/', $sdate))
			$sdate = false;
		else
			$sdate .= "-01";	/* Set to start of month */
	}
	if($edate) {
		if(!preg_match('/^\d{4}-\d{2}$/', $edate))
			$edate = false;
		else
			$edate = strftime("%Y-%m-%d", strtotime("$edate-01 +1 month -1 day")); /* Set to end of month */
	}
	if($sdate > $edate) {
		$tmp = $sdate;
		$sdate = $edate;
		$edate = $tmp;
	}

try {
	$dblink = new faktura_stat_db;
	$dbs = $dblink->getAll(true);

	/* Special case: we want all data */
	if(!$proxy && $csv && $seldb === "all") {
		$t = localtime(time(), true);
		$minyear = $t['tm_year'] + 1900;
		foreach($dbs as $db) {
			$s = substr(fix_startdate($db, $proxy), 0, 4);
			if($s < $minyear)
				$minyear = $s;
		}
		$mdate = sprintf("%02d", (!$mdate || $mdate < 1 || $mdate > 12) ? $t['tm_mon'] + 1 : $mdate);
		$ydate = sprintf("%04d", (!$ydate || $ydate < $minyear || $ydate > $t['tm_year'] + 1900) ? $t['tm_year'] + 1900 : $ydate);
		if($notcsv) {
			html_head("Signflow Statistik");
			print_all_stats($dbs, $aggre, $mdate, $ydate);
			echo "</body>\n</html>\n";
		} else {
			header("Content-Type: text/csv");
			header("Content-Disposition: attachment; filename=\"fakturastat-all-db-".aggr_key($aggre)."ly-".($aggre == AG_YEARLY ? "$ydate" : "$ydate-$mdate").".csv\"");
			all_csv_stats($dbs, $aggre, $mdate, $ydate);
		}
		exit;
	}

	/* Try to find the correct database from the query arg 'db' */
	$selkey = false;
	if($seldb !== false) {
		foreach($dbs as $k => $db) {
			if($db->hash_id() == $seldb)
				$selkey = $k;
		}
	}

	/* If none found and we came from a proxy, try to match against the vhost */
	if($proxy && $selkey === false) {
		foreach($dbs as $k => $db) {
			if($db->vhost === $proxy)
				$selkey = $k;
		}
	}

	if($proxy && ($selkey === false || $dbs[$selkey]->vhost !== $proxy)) {
		/* Mismatch between hash and vhost which requested */
		echo "Request failed: No database match.\n";
		exit;
	}

	/* If we want CVS data, only return a CSV file */
	if($csv) {
		header("Content-Type: text/csv");
		if($selkey === false) {
			echo "Request failed: No database selected\n";
			exit;
		}
		header("Content-Disposition: attachment; filename=\"".$dbs[$selkey]->dbname.($usr ? "-users-" : "-faktura-").aggr_key($aggre)."ly.csv\"");
		if($usr)
			csv_stats($dbs[$selkey], true, $aggre, $proxy);
		else
			csv_stats($dbs[$selkey], false, $aggre, $proxy);
		exit;
	}

	/* We want visual stats */
	html_head("Signflow Statistik");
	if(!$proxy) {
		echo "<p><a href=\"admin.php\">G&aring; til statistik administration</a></p>\n";
	}

	echo "<div>&nbsp;</div>\n";

	if(!$proxy) {
		/* Show an all-aggrgate download */
		$t = localtime(time(), true);
		$minyear = $maxyear = strftime("%Y");
		foreach($dbs as $db) {
			$s = substr(fix_startdate($db, $proxy), 0, 4);
			if($s < $minyear)
				$minyear = $s;
		}
		echo "<form method=\"get\" action=\"".$_SERVER['PHP_SELF']."\">\n";
		echo "<input type=\"hidden\" name=\"db\" value=\"all\" />\n";
		echo "<input type=\"hidden\" name=\"csv\" value=\"1\" />\n";
		echo "<table style=\"border-right: none; border-left: dotted; border-bottom: dotted; border-top: none; border-width: thin;\">\n";
		echo " <tr>\n";
		echo "  <td>Periode:</td>\n";
		echo "  <td><select name=\"m\">\n";
		for($i = 1; $i <= 12; $i++)
			echo "       <option".($i - 1 == $t['tm_mon'] ? ' selected="selected"' : '')." value=\"".sprintf("%d", $i)."\">".$months[$i]."</option>\n";
		echo "      </select>\n";
		echo "  <br /><select name=\"y\">\n";
		for($i = $minyear; $i <= $maxyear; $i++) {
			$y = sprintf("%04d", (int)$i);
			echo "       <option".((int)$i == $t['tm_year'] + 1900 ? ' selected="selected"' : '')." value=\"$y\">$y</option>\n";
		}
		echo "      </select></td>\n";
		echo " </tr>\n";
		echo " <tr>\n";
		echo "  <td>Aggregate:</td>\n";
		echo "  <td><select name=\"ag\">\n";
		echo "       <option value=\"".sprintf("%d", AG_MONTHLY)."\">Monthly</option>\n";
		echo "       <option value=\"".sprintf("%d", AG_YEARLY)."\">Yearly</option>\n";
		echo "      </select></td>\n";
		echo " </tr>\n";
		echo " <tr>\n";
		echo "  <td>&nbsp;</td>\n";
		echo "  <td><input type=\"submit\" value=\"Hent alle DB stats CSV\" /></td>\n";
		echo " </tr>\n";
		echo " <tr>\n";
		echo "  <td>&nbsp;</td>\n";
		echo "  <td><input type=\"submit\" name=\"notcsv\" value=\"Vis alle DB stats\" /></td>\n";
		echo " </tr>\n";
		echo "</table>\n";
		echo "</form>\n";
		echo "<div>&nbsp;</div>\n";
	}

	echo "<form method=\"get\" action=\"".$_SERVER['PHP_SELF']."\">\n";
	echo "<table style=\"border-right: none; border-left: dashed; border-bottom: dashed; border-top: none; border-width: thin;\">\n";
	echo " <tr>\n";
	echo " <td>Database</td>\n";
	if($proxy) {
		echo "<td>".$dbs[$selkey]->dbname." (".$dbs[$selkey]->vhost.")</td>";
	} else {
		echo " <td><select name=\"db\">\n";
		echo "  <option value=\"-1\">-- Select one --</option>\n";
		foreach($dbs as $k => $db) {
			$s = $db->hash_id() == $seldb ? " selected=\"selected\"" : "";
			echo "  <option$s value=\"".$db->hash_id()."\">".he(sprintf("%s | %s", $db->dbhost, $db->dbname))."</option>\n";
		}
		echo " </select></td>\n";
	}
	echo " </tr>\n";
	echo " <tr>\n";
	echo " <td>Aggregate</td>\n";
	echo " <td><select name=\"ag\">\n";
	echo "  <option ".($aggre == AG_YEARLY  ? 'selected="selected" ' : "")."value=\"3\">Yearly</option>\n";
	echo "  <option ".($aggre == AG_MONTHLY ? 'selected="selected" ' : "")."value=\"2\">Monthly</option>\n";
	echo "  <option ".($aggre == AG_WEEKLY  ? 'selected="selected" ' : "")."value=\"1\">Weekly</option>\n";
	echo "  <option ".($aggre == AG_DAILY   ? 'selected="selected" ' : "")."value=\"0\">Daily</option>\n";
	echo " </select></td>\n";
	echo " </tr>\n";
	if($proxy && is_numeric($selkey) && !is_null($dbs[$selkey]->lastupdate)) {
		$stag = fix_startdate($dbs[$selkey], $proxy);
		$etag = $dbs[$selkey]->lastupdate;
		if(false === $sdate || $sdate < $stag)
			$sdate = $stag;
		if(false === $edate || $edate > $etag)
			$edate = $etag;
		$dates = generate_dateset($stag, $etag);
		echo " <tr>\n";
		echo " <td>Start:</td>\n";
		echo " <td><select name=\"sd\">\n";
		foreach($dates as $k => $v) {
			$s = substr($sdate, 0, 7) == $k ? " selected=\"selected\"" : "";
			echo "  <option$s value=\"$k\">$v</option>\n";
		}
		echo " </select></td>\n";
		echo " </tr>\n";
		echo " <tr>\n";
		echo " <td>Slut:</td>\n";
		echo " <td><select name=\"ed\">\n";
		foreach($dates as $k => $v) {
			$s = substr($edate, 0, 7) == $k ? " selected=\"selected\"" : "";
			echo "  <option$s value=\"$k\">$v</option>\n";
		}
		echo " </select></td>\n";
		echo " </tr>\n";
	}
	echo " <tr><td>&nbsp;</td><td><input type=\"submit\" value=\"Hent kunde DB stats\" /></td></tr>\n";
	echo "</table>\n";
	echo "</form>\n";

	if(is_numeric($selkey)) {
		$mindate = fix_startdate($dbs[$selkey], $proxy);
		if(!$sdate)
			$sdate = $mindate;
		if(!$edate)
			$edate = strftime("%Y-%m-%d");
		if($sdate < $mindate)
			$sdate = $mindate;
		if($edate < $mindate)
			$edate = $mindate;
		$sps = $_SERVER['PHP_SELF'];
		echo "<div>&nbsp;</div>\n";
		echo "<table border=\"0\">\n";
		echo " <tr><td>Start dato:</td><td>".he($mindate)."</td></tr>\n";
		if(!$proxy)
			echo " <tr><td>Synlig fra:</td><td>".he(is_null($dbs[$selkey]->visiblefrom) ? "aldrig" : $dbs[$selkey]->visiblefrom)."</td></tr>\n";
		echo " <tr><td>Seneste opdatering:</td><td>".he(is_null($dbs[$selkey]->lastupdate) ? "aldrig" : $dbs[$selkey]->lastupdate)."</td></tr>\n";
		echo " <tr><td>Statistik fra:</td><td>$sdate</td></tr>\n";
		echo " <tr><td>Statistik til:</td><td>$edate</td></tr>\n";
		echo "</table>\n";
		echo "<div>&nbsp;</div>\n";
		echo "<table border=\"0\">\n";
		echo " <tr><td colspan=\"5\">Hent CSV-filer:</td></tr>\n";
		echo " <tr><td>Faktura</td>\n";
		echo "  <td><a href=\"$sps?".sprintf("db=%s&amp;ag=%d&amp;csv=1", $dbs[$selkey]->hash_id(), AG_DAILY)."\">Daily</a></td>\n";
		echo "  <td><a href=\"$sps?".sprintf("db=%s&amp;ag=%d&amp;csv=1", $dbs[$selkey]->hash_id(), AG_WEEKLY)."\">Weekly</a></td>\n";
		echo "  <td><a href=\"$sps?".sprintf("db=%s&amp;ag=%d&amp;csv=1", $dbs[$selkey]->hash_id(), AG_MONTHLY)."\">Monthly</a></td>\n";
		echo "  <td><a href=\"$sps?".sprintf("db=%s&amp;ag=%d&amp;csv=1", $dbs[$selkey]->hash_id(), AG_YEARLY)."\">Yearly</a></td>\n";
		echo " </tr>\n";
		echo " <tr><td>Brugere</td>\n";
		echo "  <td><a href=\"$sps?".sprintf("db=%s&amp;ag=%d&amp;csv=1&amp;usr=1", $dbs[$selkey]->hash_id(), AG_DAILY)."\">Daily</a></td>\n";
		echo "  <td><a href=\"$sps?".sprintf("db=%s&amp;ag=%d&amp;csv=1&amp;usr=1", $dbs[$selkey]->hash_id(), AG_WEEKLY)."\">Weekly</a></td>\n";
		echo "  <td><a href=\"$sps?".sprintf("db=%s&amp;ag=%d&amp;csv=1&amp;usr=1", $dbs[$selkey]->hash_id(), AG_MONTHLY)."\">Monthly</a></td>\n";
		echo "  <td><a href=\"$sps?".sprintf("db=%s&amp;ag=%d&amp;csv=1&amp;usr=1", $dbs[$selkey]->hash_id(), AG_YEARLY)."\">Yearly</a></td>\n";
		echo " </tr>\n";
		echo "</table>\n";
		print_stats($dbs[$selkey], false, "Faktura", $aggre, $proxy, $sdate, $edate);
		print_stats($dbs[$selkey], true, "Brugere", $aggre, $proxy, $sdate, $edate);
	}
} catch (Exception $e) {
	if($csv) {
		echo "Caught exception: Data may not be valid, please report this\n";
		exit;
	} else
		echo "Caught exception: ".$e->getMessage()."<br><pre>".$e->getTraceAsString()."</pre>\n";
}
	echo "</body>\n</html>\n";
?>
