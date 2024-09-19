<?php

define('FAKTURA_STAT_DSN', 'pgsql:host=localhost;dbname=faktura_stat');
define('FAKTURA_STAT_USER', 'postgres');
define('FAKTURA_STAT_PASS', '');

class faktura_stat_pdo
{
	private static $_inst;
	private static $_pdo;

	/* Prevent instantiation */
	private function __construct() {
	}

	/* Prevent cloning */
	public function __clone() {
		throw new Exception("Clone '".__CLASS__."' is not allowed");
	}

	public static function getInstance() {
		if(!isset(self::$_inst)) {
			$c = __CLASS__;
			self::$_inst = new $c;
			try {
				self::$_pdo = new PDO(FAKTURA_STAT_DSN, FAKTURA_STAT_USER, FAKTURA_STAT_PASS);
				self::$_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				self::$_pdo->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
				self::$_pdo->exec("SET NAMES 'UTF8'");
			} catch(PDOException $e) {
				throw new Exception("Cannot connect to database");
			}
		}
		return self::$_inst;
	}

	public static function getPDO() {
		self::getInstance();
		return self::$_pdo;
	}
}

?>
