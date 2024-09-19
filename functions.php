<?php

define("AG_DAILY", 0);
define("AG_WEEKLY", 1);
define("AG_MONTHLY", 2);
define("AG_YEARLY", 3);

function param($id, $default = "")
{
	return isset($_REQUEST[$id]) ? $_REQUEST[$id] : $default;
}

function html_head($title)
{
	echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n";
	echo "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\">\n";
	echo "<head>\n";
	echo "  <title>$title</title>\n";
	echo "  <link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"faktura_stat.css\" />\n";
        echo "  <meta http-equiv=\"Content-Type\" content=\"text/html;charset=utf-8\" />\n";
	echo "</head>\n";
	echo "<body>\n";
	/* Signflow header */
	echo "<table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
	echo " <tr>\n";
	echo "  <td><img class=\"imgnoborder\" src=\"logo-signflow.png\" alt=\"Signflow logo\" /></td>\n";
	echo "  <td class=\"headline\">$title</td>\n";
	echo "  <td align=\"right\"><a href=\"http://www.logiva.dk\"><img class=\"imgnoborder\" src=\"logo-logiva.png\" alt=\"Logiva logo\" /></a></td>\n";
	echo " </tr>\n";
	echo "</table>\n";
	echo "<div id=\"menu\">&nbsp;</div>\n";
}

function he($s)
{
	return htmlentities($s, ENT_COMPAT, "UTF-8");
}

function aggr_key($aggr)
{
	switch($aggr) {
	case AG_WEEKLY:  return "week";
	case AG_MONTHLY: return "month";
	case AG_YEARLY:  return "year";
	default:	 return "day";
	}
}

?>
