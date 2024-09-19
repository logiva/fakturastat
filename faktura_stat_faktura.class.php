<?php

require_once("faktura_stat_pdo.class.php");

class faktura_stat_faktura
{
	public $org;
	public $stamp;
	public $typename;
	public $mini;
	public $maxi;
	public $total;

	private static $_inst;
	private static $_stmtInterval;
	private static $_stmtInsert;

	/* Prevent instantiation */
	private function __construct() {
	}

	/* Prevent cloning */
	public function __clone() {
		throw new Exception("Clone '".__CLASS__."' is not allowed");
	}

	private static function throwError($msg) {
		$pdo = faktura_stat_pdo::getPDO();
		$err = $pdo->errorInfo();
		throw new Exception($msg . ": " . $err[2]);
	}

	private static function getInstance() {
		if(!isset(self::$_inst)) {
			$c = __CLASS__;
			self::$_inst = new $c;
			/* Prepare db call */
			$pdo = faktura_stat_pdo::getPDO();
			$sel = <<<EOS
SELECT p.org, p.tagdate AS stamp, t.typename, p.min AS mini, p.max AS maxi, p.sum AS total
 FROM 
  (SELECT f.org, date_trunc(?, f.stamp)::date AS tagdate, f.stattype, min(f.cnt), max(f.cnt), sum(f.cnt)
    FROM
     (SELECT * FROM stat_faktura WHERE (dbref = ? OR ?) AND stamp >= ? AND stamp <= ?) f
    GROUP BY f.org, tagdate, f.stattype) p
  JOIN stat_type t ON p.stattype = t.id
 WHERE t.issum = ?
 ORDER BY p.org, p.tagdate, t.typename
EOS;
			if(false === (self::$_stmtInterval = $pdo->prepare($sel)))
				self::throwError("Cannot prepare selectInterval statement");

			$sel = "INSERT INTO stat_faktura (dbref, stamp, org, stattype, cnt) VALUES (?, ?, ?, (SELECT id FROM stat_type WHERE lower(typename) = lower(?)), ?)";
			if(false === (self::$_stmtInsert = $pdo->prepare($sel)))
				self::throwError("Cannot prepare insert statement");
		}
		return self::$_inst;
	}

	public static function getInterval($id, $startdate, $enddate, $issum, $aggr) {
		self::getInstance();
		if(is_null($id)) {
			$id = 0;
			$any = 'true';
		} else
			$any = 'false';
		if(false === self::$_stmtInterval->execute(array($aggr, $id, $any, $startdate, $enddate, $issum ? "true" : "false")))
			self::throwError("Cannot execute select interval statement");
		$res = self::$_stmtInterval->fetchAll(PDO::FETCH_CLASS, __CLASS__);
		self::$_stmtInterval->closeCursor();
		return $res;
	}

	public static function insert($id, $tagdate, $org, $docname, $cnt) {
		self::getInstance();
		if(false === self::$_stmtInsert->execute(array($id, $tagdate, $org, $docname, $cnt)))
			self::throwError("Cannot execute insert statement");
		self::$_stmtInsert->closeCursor();
	}
}

?>
