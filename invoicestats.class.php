<?php

class invoicestats
{
	private $_pdo;
	private $_stmtdoc;
	private $_stmtusr;
	private $_stmtusrold;

	public function __construct($dbhost, $dbport, $dbname, $dbuser, $dbpass)
	{
		$_pdo = null;
		try {
			$dsn = "pgsql:host=$dbhost;port=$dbport;dbname=$dbname";
			$this->_pdo = new PDO($dsn, $dbuser, $dbpass);
			$this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->_pdo->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
			$this->_pdo->exec("SET NAMES 'UTF8'");
		} catch(PDOException $e) {
                       printf("%s", $e->getMessage()); 
		       throw new Exception("Cannot connect to database");
		}
	}

	public function __destruct()
	{
		if($this->_pdo)
			$this->_pdo = null;
	}

	private function throwError($msg) {
		$err = $this->_pdo->errorInfo();
		throw new Exception($msg . ": " . $err[2]);
	}

	private function prepareDocStats($sd, $ed)
	{
		$sds = $this->_pdo->quote($sd);
		$eds = $this->_pdo->quote($ed);
		$sel = <<<EOS
WITH ps AS (
 SELECT documentpackageoid, folderstatusoid FROM sspackagestatus
 UNION
 SELECT documentpackageoid, folderstatusoid FROM ssprocessortransfolderstatus
)
, oiofak AS (
 SELECT 'efaktura' AS typename, d.documentpackageoid, count(*) AS cnt 
 FROM ssdocument d, (SELECT unnest(ARRAY['uts%.xml', 'efaktura%.xml']) basename) names
 WHERE d.isversionofdocumentoid IS NULL AND d.isdeleted = 0
 AND d.basename LIKE names.basename
 GROUP BY 1,2
)
, imagefak AS (
 SELECT 'faktura' AS typename, d.documentpackageoid, count(*) AS cnt 
 FROM ssdocument d, (SELECT unnest(ARRAY['faktura%']) basename) names
 WHERE d.isversionofdocumentoid IS NULL AND d.isdeleted = 0
 AND d.basename LIKE names.basename
 GROUP BY 1,2
)
, imagefakbilag AS (
 SELECT 'bilag' AS typename, d.documentpackageoid, count(*) AS cnt 
 FROM ssdocument d, (SELECT unnest(ARRAY['bilag%']) basename) names
 WHERE d.isversionofdocumentoid IS NULL AND d.isdeleted = 0
 AND d.basename LIKE names.basename
 GROUP BY 1,2
)
SELECT o.domainname AS org, dp.creationtime::date AS tagdate, allfak.typename AS docname, sum(allfak.cnt) AS cnt
FROM ssorganization o
 JOIN ssfolder f ON o.organizationoid = f.organizationoid
 JOIN ssfolderstatus fs ON f.folderoid = fs.folderoid
 JOIN ps ON ps.folderstatusoid = fs.folderstatusoid
 JOIN ssdocumentpackage dp ON ps.documentpackageoid = dp.documentpackageoid
 JOIN (
  SELECT * FROM oiofak 
  UNION 
  SELECT * FROM imagefak WHERE documentpackageoid NOT IN (SELECT documentpackageoid FROM oiofak)
  UNION 
  SELECT * FROM imagefakbilag WHERE documentpackageoid NOT IN (SELECT documentpackageoid FROM oiofak)
 ) allfak ON dp.documentpackageoid = allfak.documentpackageoid
WHERE dp.creationtime >= $sds AND dp.creationtime < $eds
GROUP BY 1,2,3
EOS;
		return $sel;
	}

	private function prepareUserStats()
	{
		if(!$this->_stmtusr) {
			$sel = <<<EOS
SELECT
    o.domainname AS org,
    dd.dates AS tagdate,
    CASE WHEN u.active <> 0 THEN 'usersactive' ELSE 'usersdisabled' END AS docname,
    count(*) AS cnt
 FROM ssuser u
  JOIN ssorganization o USING (organizationoid)
  JOIN (SELECT current_date - s.a AS dates FROM generate_series(current_date - (?)::date, current_date - (?)::date, -1) AS s(a)) dd ON u.creationtime::date <= dd.dates
 WHERE u.userid NOT ILIKE '%test%'
   AND u.userid NOT ILIKE '%support%'
   AND u.userid NOT ILIKE '%logiva%'
   AND u.userid NOT ILIKE '%anonymous%'
   AND u.useroid IN (SELECT useroid FROM invoiceusercompany)
 GROUP BY org, tagdate, docname
 ORDER BY org, tagdate, docname
EOS;
			if(false === ($this->_stmtusr = $this->_pdo->prepare($sel)))
				$this->throwError("Cannot prepare statement");
		}
		if(!$this->_stmtusrold) {
			$sel = <<<EOS
SELECT
    o.domainname AS org,
    dd.dates AS tagdate,
    CASE WHEN u.active <> 0 THEN 'usersactive' ELSE 'usersdisabled' END AS docname,
    count(*) AS cnt
 FROM ssuser u
  JOIN ssorganization o USING (organizationoid)
  JOIN (SELECT current_date - s.a AS dates FROM generate_series(current_date - (?)::date, current_date - (?)::date, -1) AS s(a)) dd ON u.creationtime::date <= dd.dates
 WHERE u.userid NOT ILIKE '%test%'
   AND u.userid NOT ILIKE '%support%'
   AND u.userid NOT ILIKE '%logiva%'
   AND u.userid NOT ILIKE '%anonymous%'
 GROUP BY org, tagdate, docname
 ORDER BY org, tagdate, docname
EOS;
			if(false === ($this->_stmtusrold = $this->_pdo->prepare($sel)))
				$this->throwError("Cannot prepare statement");
		}
	}

	public function getDocStats($start, $end)
	{
		/* Entry: $start and $end are inclusive in YYYY-mm-dd format
		 * DB query:
		 * Start date: creationtime >= $start 00:00:00
		 * End date  : creationtime <  ($end + 1day) 00:00:00
		 */
		/* Php 5.1 fails on prepare/execute of this query and also
		 * barfs when the dates are appended with a 00:00:00 stamp.
		 * Php seems to assume that ':xx" means a named parameter
		 * substitution and postgres then gets to eat "2011-02-20 00$1$1".
		 * So, instead, we construct a query and feed it into the system
		 * without any time. The default for postgres is to assume 00:00:00
		 * on a timestamp if it is missing (i.e. "YYYY-mm-dd" ==
		 * "YYYY-mm-dd 00:00:00"). Lucky us!
		 */
		$ed = strftime("%Y-%m-%d", strtotime("$end +1 day"));
		$q = $this->prepareDocStats($start, $ed);
		if(false === ($s = $this->_pdo->query($q)))
			$this->throwError("Cannot execute DocStats statement");
		$res = $s->fetchAll(PDO::FETCH_ASSOC);
		$s->closeCursor();
		return $res;
	}

	public function getUserStats($start, $end, $oldstyle = false)
	{
		$this->prepareUserStats();
		if($oldstyle) {
			if(false === $this->_stmtusrold->execute(array($start, $end)))
				$this->throwError("Cannot execute (old) UserStats statement");
			$res = $this->_stmtusrold->fetchAll(PDO::FETCH_ASSOC);
			$this->_stmtusrold->closeCursor();
		} else {
			if(false === $this->_stmtusr->execute(array($start, $end)))
				$this->throwError("Cannot execute UserStats statement");
			$res = $this->_stmtusr->fetchAll(PDO::FETCH_ASSOC);
			$this->_stmtusr->closeCursor();
		}
		return $res;
	}
}

?>
