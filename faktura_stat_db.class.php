<?php

require_once("faktura_stat_pdo.class.php");

class faktura_stat_db
{
	public $id;
	public $dbhost;
	public $dbport;
	public $dbname;
	public $dbuser;
	public $dbpass;
	public $vhost;
	public $ignore;
	public $oldstyle;
	public $startdate;
	public $lastupdate;
	public $visiblefrom;

	private function throwError($msg) {
		$pdo = faktura_stat_pdo::getPDO();
		$err = $pdo->errorInfo();
		throw new Exception("$msg: " . $err[2]);
	}

	public function getAll($active = null) {
		$pdo = faktura_stat_pdo::getPDO();
		$where = is_null($active) ? "" : " WHERE ignore=".($active ? "false" : "true");
		if(false === ($s = $pdo->query("SELECT * FROM stat_db$where ORDER BY dbhost, dbname")))
			$this->throwError("Cannot execute statement");
		$res = $s->fetchAll(PDO::FETCH_CLASS, __CLASS__);
		$s->closeCursor();
		return $res;
	}

	public static function setLastupdate($id, $date) {
		$pdo = faktura_stat_pdo::getPDO();
		$res = $pdo->exec("UPDATE stat_db SET lastupdate = ".$pdo->quote($date)." WHERE id = ".$pdo->quote((integer)$id, PDO::PARAM_INT));
		if($res !== 1)
			$this->throwError("Update lastupdate failed");
		return $res;
	}

	public function hash_id() {
		return md5(sprintf("%d-%s:%d-%s", $this->id, $this->dbhost, $this->dbport, $this->dbname));
	}

	public function add() {
		if(trim($this->visiblefrom) == '')
			$this->visiblefrom = null;
		$pdo = faktura_stat_pdo::getPDO();
		$res = $pdo->exec("INSERT INTO stat_db (dbhost, dbport, dbname, dbuser, dbpass, vhost, ignore, oldstyle, startdate, visiblefrom) VALUES ("
			.$pdo->quote($this->dbhost).", "
			.$pdo->quote((integer)$this->dbport, PDO::PARAM_INT).", "
			.$pdo->quote($this->dbname).", "
			.$pdo->quote($this->dbuser).", "
			.$pdo->quote($this->dbpass).", "
			.(is_null($this->vhost) || trim($this->vhost) == '' ? "NULL" : $pdo->quote($this->vhost)).", "
			.($this->ignore ? "true" : "false").", "
			.($this->oldstyle ? "true" : "false").", "
			.$pdo->quote($this->startdate).", "
			.(is_null($this->visiblefrom) ? "NULL" : $pdo->quote($this->visiblefrom))
			.")");
		if($res !== 1)
			$this->throwError("Insert failed");
		return $res;
	}

	public function update($id) {
		if(trim($this->visiblefrom) == '')
			$this->visiblefrom = null;
		$pdo = faktura_stat_pdo::getPDO();
		$res = $pdo->exec($q = "UPDATE stat_db SET "
			."dbhost=".$pdo->quote($this->dbhost).", "
			."dbport=".$pdo->quote((integer)$this->dbport, PDO::PARAM_INT).", "
			."dbname=".$pdo->quote($this->dbname).", "
			."dbuser=".$pdo->quote($this->dbuser).", "
			."dbpass=".$pdo->quote($this->dbpass).", "
			."vhost=".(is_null($this->vhost) || trim($this->vhost) == '' ? "NULL" : $pdo->quote($this->vhost)).", "
			."ignore=".$pdo->quote($this->ignore ? "true" : "false").", "
			."oldstyle=".$pdo->quote($this->oldstyle ? "true" : "false").", "
			."startdate=".$pdo->quote($this->startdate).", "
			."visiblefrom=".(is_null($this->visiblefrom) ? "NULL" : $pdo->quote($this->visiblefrom))." "
			." WHERE id=".$pdo->quote((integer)$id, PDO::PARAM_INT)
			);
		if($res !== 1)
			$this->throwError("Update failed $q ".print_r($res, true));
		return $res;
	}

	public function validate() {
		if(!$this->dbhost)
			return "Mangler database host";
		if(!is_numeric($this->dbport) || $this->dbport < 1 || $this->dbport > 65534)
			return "Port skal v&aelig;re mellen 1 og 65534";
		if(!$this->dbname)
			return "Mangler database navn";
		if(!$this->dbuser)
			return "Mangler database brugernavn";
		$t = strtotime($this->startdate);
		if($t === false || $t === -1)
			return "Startdato ikke gyldigt";
		return false;
	}
}

?>
