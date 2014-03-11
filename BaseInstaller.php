<?php
/**
 * This action executes drush site-install on the installation if there are no
 * tables in the database.
 */

include_once("ApplicableState.php");

class BaseInstaller extends ApplicableStateAbstract {
    function __construct(RunParameters $runParameters, DatabaseConnection $dbConn, $drupalUser, $drupalUserPassword, $drupalInstallationDir, $log, $mysqli) {
        parent::__construct($runParameters);
        $this->log = $log;
        $this->mysqli = $mysqli;
        $this->dbConn = $dbConn;
        $this->dbName = $dbConn->getDbName();
        $this->drupalUser = $drupalUser;
        $this->drupalUserPassword = $drupalUserPassword;
        $this->drupalInstallationDir = $drupalInstallationDir;
    }

    function isActionRequired() {
        $ps = 'select count(table_schema) from information_schema.tables where table_schema = ?';
        $stmt = $this->mysqli->prepare($ps);
        $stmt->bind_param("s", $this->dbName);
        $stmt->execute();
        $stmt->bind_result($numTabs);
        $stmt->fetch();
        $stmt->close();
        return ($numTabs == 0 ? true : false);
    }

    function logAction() {
        $this->log->addInfo('Running the drupal installer with drush for database: ', [$this->dbName]);
    }

    function doAction() {
        $cmdStr = 'drush site-install standard -y --account-name=' . $this->drupalUser 
            . ' --account-pass=' . $this->drupalUserPassword . ' --db-url='
            . $this->dbConn->getDbConnectionUrl()
            . ' -r ' . $this->drupalInstallationDir;
        exec($cmdStr);
    }

} 