<?php
	require_once("functions.php");
	require_once("faktura_stat_db.class.php");
	require_once("faktura_stat_faktura.class.php");

function make_form($db, $isnew)
{
	$submit = $isnew ? "    Tilf&oslash;j    " : "    Opdater    ";
	echo "<form method=\"post\" action=\"".$_SERVER['PHP_SELF']."\">\n";
	echo "<div><input type=\"hidden\" name=\"action\" value=\"commit\" />\n";
	echo "<input type=\"hidden\" name=\"db\" value=\"".$db->hash_id()."\" /></div>\n";
	echo "<table border=\"1\">\n";
	echo " <tr><td>Host</td><td><input type=\"text\" name=\"dbhost\" value=\"".he($db->dbhost)."\"/></td></tr>\n";
	echo " <tr><td>Port</td><td><input type=\"text\" name=\"dbport\" value=\"".he($db->dbport)."\"/></td></tr>\n";
	echo " <tr><td>Name</td><td><input type=\"text\" name=\"dbname\" value=\"".he($db->dbname)."\"/></td></tr>\n";
	echo " <tr><td>User</td><td><input type=\"text\" name=\"dbuser\" value=\"".he($db->dbuser)."\"/></td></tr>\n";
	echo " <tr><td>Pass</td><td><input type=\"text\" name=\"dbpass\" value=\"".he($db->dbpass)."\"/></td></tr>\n";
	echo " <tr><td>VHost</td><td><input type=\"text\" name=\"vhost\" value=\"".he($db->vhost)."\"/></td></tr>\n";
	echo " <tr><td>Enabled</td><td><input type=\"checkbox\" name=\"enabled\" value=\"1\"".($db->ignore ? '' : ' checked="checked"')." /></td></tr>\n";
	echo " <tr><td>Oldstyle</td><td><input type=\"checkbox\" name=\"oldstyle\" value=\"1\"".($db->oldstyle ? ' checked="checked"' : '')." /></td></tr>\n";
	echo " <tr><td>Startdate</td><td><input type=\"text\" name=\"startdate\" value=\"".he($db->startdate)."\"/></td></tr>\n";
	echo " <tr><td>Visible&nbsp;from</td><td><input type=\"text\" name=\"visiblefrom\" value=\"".he($db->visiblefrom)."\"/></td></tr>\n";
	echo " <tr><td>&nbsp;</td><td><input type=\"submit\" value=\"$submit\" /></td></tr>\n";
	echo "</table>\n";
	echo "</form>\n";
}

/*---------------------------------------------------------------------------*/
	header("Cache-Control: no-cache, must-revalidate");
	header("Expires: Fri, 31 Dec 1999 23:59:59 GMT");

	$seldb = param("db", false);
	$action = param("action", 'none');

	/* The proxy server forwards the real "Host:" header so we chan match it here */
	$proxy = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_HOST'] : false;

try {
	if($proxy) {
		html_head("Fejl");
		echo "Siden kan ikke vises fra request oprindelse.\n";
		echo "</body>\n</html>\n";
		exit;
	}

	$dbs = faktura_stat_db::getAll();

	/* Try to find the correct database from the quary arg 'db' */
	$selkey = false;
	if($seldb !== false) {
		foreach($dbs as $k => $db) {
			if($db->hash_id() == $seldb)
				$selkey = $k;
		}
	}

	html_head("Signflow Statistik Admin");
	echo "<p><a href=\"index.php\">G&aring; til statistik siden</a></p>\n";
	if($action == 'commit') {
		/* Commit an entry */
		$isnew = $selkey === false;

		$db = new faktura_stat_db;
		$db->dbhost    = param("dbhost", '');
		$db->dbport    = param("dbport", 5432);
		$db->dbname    = param("dbname", '');
		$db->dbuser    = param("dbuser", '');
		$db->dbpass    = param("dbpass", '');
		$db->vhost     = param("vhost", '');
		$db->ignore    = !(param("enabled", 0) == 1);
		$db->oldstyle  = param("oldstyle", 0) == 1;
		$db->startdate = param("startdate", false);
		$db->visiblefrom = param("visiblefrom", null);

		if(false !== ($msg = $db->validate())) {
			echo "<h4>Fejl</h4>\n";
			echo "$msg\n";
			echo "<div>&nbsp;</div>\n";
			make_form($db, $isnew);
			echo "</body>\n</html>\n";
			exit;
		}
		try {
			if($isnew)
				$db->add();
			else
				$db->update($dbs[$selkey]->id);
		} catch (Exception $e) {
			echo "<h4>Fejl</h4>\n";
			echo "Database exception: ".$e->getMessage()."\n";
			echo "<div>&nbsp;</div>\n";
			make_form($db, $isnew);
			echo "</body>\n</html>\n";
			exit;
		}
		header("Location: ".$_SERVER['PHP_SELF']);
		exit;
	}

	if($action == 'none') {
		/* Display all databases */
		echo "<table border=\"1\">\n";
		echo " <tr>\n";
		echo "  <th>Id</th>\n";
		echo "  <th>Host</th>\n";
		echo "  <th>Port</th>\n";
		echo "  <th>Name</th>\n";
		echo "  <th>User</th>\n";
		echo "  <th>Pass</th>\n";
		echo "  <th>VHost</th>\n";
		echo "  <th>Ena</th>\n";
		echo "  <th>Old</th>\n";
		echo "  <th>Start&nbsp;Date</th>\n";
		echo "  <th>Last&nbsp;Update</th>\n";
		echo "  <th>Visible&nbsp;from</th>\n";
		echo " </tr>\n";
		foreach($dbs as $db) {
			echo " <tr>\n";
			echo "  <td class=\"num\">".sprintf("%d", $db->id)."</td>\n";
			echo "  <td>".he($db->dbhost)."</td>\n";
			echo "  <td class=\"num\">".sprintf("%d", $db->dbport)."</td>\n";
			echo "  <td><a href=\"".$_SERVER['PHP_SELF']."?action=edit&amp;db=".$db->hash_id()."\">".he($db->dbname)."</a></td>\n";
			echo "  <td>".he($db->dbuser)."</td>\n";
			echo "  <td>".he($db->dbpass)."</td>\n";
			if($db->vhost)
				echo "  <td><a href=\"https://".he($db->vhost)."/fakturastat/\">".he($db->vhost)."</a></td>\n";
			else
				echo "  <td></td>\n";
			echo "  <td><input type=\"checkbox\" readonly=\"readonly\"".($db->ignore ? '' : ' checked="checked"')." /></td>\n";
			echo "  <td><input type=\"checkbox\" readonly=\"readonly\"".($db->oldstyle ? ' checked="checked"' : '')." /></td>\n";
			echo "  <td>".he($db->startdate)."</td>\n";
			echo "  <td>".he($db->lastupdate)."</td>\n";
			echo "  <td>".he($db->visiblefrom)."</td>\n";
			echo " </tr>\n";
		}
		echo "<tr><td>&nbsp;</td><td colspan=\"11\"><a href=\"".$_SERVER['PHP_SELF']."?action=edit\">Tilf&oslash;j database</a></td></tr>\n";
		echo "</table>\n";
	} else {
		/* Edit/add a database */
		if($selkey === false) {
			$db = new faktura_stat_db;
			$db->dbport = 5432;
			$db->dbuser = "postgres";
			$db->startdate = strftime("%Y-%m-%d");
			make_form($db, true);
		} else {
			make_form($dbs[$selkey], false);
		}
	}
} catch (Exception $e) {
	echo "Caught exception: ".$e->getMessage()."<br><pre>".$e->getTraceAsString()."</pre>\n";
}
	echo "</body>\n</html>\n";
?>
