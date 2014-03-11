<?php

require 'vendor/autoload.php';

use Monolog\Logger;

include_once("ArgumentsRunParameters.php");
include_once("BaseInstaller.php");
include_once("DatabaseConnection.php");

$log = new Logger('name');

$json = file_get_contents("config.json");
$data = json_decode($json, TRUE);
$drupalInstalls = $data['drupal-installs'];

$dbRootUser = $data['db-root-user'];
$dbRootPassword = $data['db-root-password'];
$dbHost = $data['db-host'];
$documentRoot = $data['document-root'];

foreach ($drupalInstalls as $key => $value) {
    $main_drupal_dir = $documentRoot . '/' . $key;
    $drupal_version = $value['drupal-version'];
    $drupal_dir_name = 'drupal-' . $drupal_version;
    $drupal_dir = $documentRoot . '/' . $drupal_dir_name;

    if (is_dir($main_drupal_dir)) {
        $present_version = trim(file_get_contents($main_drupal_dir . '/drupal-version'));
        if ($present_version != $drupal_version) {
            $log->addWarning('present: ' . $present_version . '; version: ' . $drupal_version);
        }
    } else {
        if (!is_dir($drupal_dir)) {
            $phar = new PharData('packages/' . $drupal_dir_name . '.tar.gz');
            $phar->extractTo($documentRoot);
            $fp = fopen($drupal_dir . "/drupal-version", "wb");
            fwrite($fp, $drupal_version);
            fclose($fp);
            rename($drupal_dir, $main_drupal_dir);
        }
    }

    $filesDir = $main_drupal_dir . '/sites/default/files';
    if (!is_dir($filesDir)) {
        mkdir($filesDir);
        chmod($filesDir, 777);
    }

    $databaseName = $value['db-name'];
    if (!preg_match('/^[A-Za-z0-9]+$/', $databaseName)) {
        die("Database name should only contain letters and numbers");
    }
    $userName = $value['db-user'];
    if (!preg_match('/^[A-Za-z0-9]+$/', $userName)) {
        die("User name should only contain letters and numbers");
    }
    $password = $value['db-password'];
    if (!preg_match('/^[^ ]+$/', $userName)) {
        die("User db password should not contain spaces");
    }
    $domainName = $value['domain-name'];
    $drupalAdminUser = $value['drupal-admin-user'];
    $drupalAdminPassword = $value['drupal-admin-password'];

    $loader = new Twig_Loader_Filesystem('etc/');
    $twig = new Twig_Environment($loader);

    $settings_php_contents = $twig->render('settings.php', 
        array(
            'db_name' => $databaseName, 
            'db_username' => $userName, 
            'db_password' => $password,
            'db_host' => $dbHost
    ));
    $fp = fopen($main_drupal_dir . "/sites/default/settings.php", "wb");
    fwrite($fp, $settings_php_contents);
    fclose($fp);

    // write nginx configuration
    $nginx_conf_contents = $twig->render('drupal.conf', array('domain_name' => $domainName, 'document_root' => $main_drupal_dir));
    $fp = fopen('/opt/nginx/sites-enabled/' . $key . '.conf'  , "wb");
    fwrite($fp, $nginx_conf_contents);
    fclose($fp);

    $mysqli = mysqli_connect($dbHost, $dbRootUser, $dbRootPassword);
    $mysqli->select_db('mysql');

    // creating db user if necessary
    $result = $mysqli->query("select User from mysql.user");
    $userExists = false;
    while ($row = mysqli_fetch_row($result) and !$userExists):
        if ($row[0] == $userName) {
            $userExists = true;
        }
    endwhile;
    if (!$userExists) {
        $log->addInfo('creating database user ' . $userName);
        $stmt = $mysqli->prepare('CREATE USER ' . '\'' . $userName . '\'@\'' . $dbHost . '\'');
        if (!$stmt->execute()) {
            $log->error($stmt->error);
        }
        $stmt->close();
    }

    $query = 'UPDATE user SET Password=PASSWORD(\'' . $password . '\') WHERE User=\''. $userName . '\' AND Host=\'' . $dbHost . '\'';
    $stmt2 = $mysqli->prepare($query);
    if (!$stmt2->execute()) {
        $log->error($stmt2->error);
    }
    $stmt2->close();

    // creating db if necessary
    $databaseExists = false;
    $result = $mysqli->query("SHOW DATABASES");
    while ($row = mysqli_fetch_row($result) and !$databaseExists):
        if ($row[0] == $databaseName) {
            $databaseExists = true;
        }
    endwhile;

    if (!$databaseExists) {
        $log->addInfo('creating database ' . $databaseName);
        // protected against sql injection with regexp
        $query = "CREATE DATABASE " . $databaseName;
        $stmt = $mysqli->prepare($query);
        if (!$stmt->execute()) {
            $log->error($stmt->error);
        }
        $stmt->close();
    }

    $query = 'grant all on ' . $databaseName . '.* to ' . $userName . '@' . $dbHost . ' identified by \'' . $password . '\'';
    $stmt = $mysqli->prepare($query);
    if (!$stmt->execute()) {
        $log->error($stmt->error);
    }
    $stmt->close();

    $stmt = $mysqli->prepare('FLUSH PRIVILEGES');
    if (!$stmt->execute()) {
        $log->error($stmt->error);
    }
    $stmt->close();

    $dbConn = new DatabaseConnection($dbHost, $userName, $password, $databaseName);
    $runParams = new ArgumentsRunParameters();
    $baseInstaller = new BaseInstaller($runParams, $dbConn, $drupalAdminUser, 
            $drupalAdminPassword, $main_drupal_dir, $log, $mysqli);
    $baseInstaller->execute();
    $mysqli->close();
}

