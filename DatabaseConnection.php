<?php

class DatabaseConnection {
	private $host;
	private $drupalUser;
	private $drupalPassword;
	private $dbName;

	function __construct($host, $drupalUser, $drupalPassword, $dbName) {
		$this->host = $host;
		$this->drupalUser = $drupalUser;
		$this->drupalPassword = $drupalPassword;
		$this->dbName = $dbName;
	}

	/*
	 * @return A string to connect to the db, f.e. mysql://user:password@localhost/db
	 */
	function getDbConnectionUrl() {
    	return 'mysql://' . $this->drupalUser . ':' . $this->drupalPassword . '@' . $this->host . '/' . $this->dbName;
	}

	function getDbName() {
		return $this->dbName;
	}
}