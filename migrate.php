#!/usr/bin/php
<?
require_once('../helpers.php');

/* Runs all migrations in the migration folder sequentially if it has not
   been run yet. Enforces proper version through 'db_version' in 'configs'
   table

   Args: --no-backup : no backup to S3
         --no-pullprod: don't pull from prod beforehand
  */

$env = $argv[1];
if (empty($env) || !($env == "local" || $env == "staging" || $env == "prod")){
  fail("[ERROR] Missing or incorrect database arg, either 'local', 'staging', or 'prod'\n");
}

$backup = true;
if ($argv[2] == "--no-backup") $backup = false;
$pull_prod = true;
if ($argv[2] == "--no-pullprod") $pull_prod = false;

$password = prompt_silent("Enter $env DB Password:");

/* ---------- CONFIG -------------- */
$config = array(
  'local'     => array(
    'host'      => '127.0.0.1',
    'host-no-port'      => '127.0.0.1',
    'user'      => 'username',
  ),
  'staging' => array(
    'host'      => 'hostwithport:1234',
    'host-no-port'      => 'hostwithoutport',
    'user'      => 'username',

  ),
  'prod' => array(
    'host'      => 'hostwithport:1234',
    'host-no-port'      => 'hostwithoutport',
    'user'      => 'username',
  )
);

$file_format = "./migrations/0*.*.*.php";


/* ---------- END CONFIG -------------- */
sysout("\nDBVC Migration Script: $env");
divider();

mysql_connect($config[$env]['host'], $config[$env]['user'], $password) or
  die("Couldn't connect to $env\n");
mysql_select_db('dbname');

if ($env == "prod" && $backup) {
  sysout("Backing up production DB to S3...");
  echo exec('./backup-to-s3.php ' . $password, $out, $exit);
  sysout();
  if ($exit == 1) die();
}

// Drop tables in staging and pull down prod
if (($env == "staging" || $env == "local") && $pull_prod) {
  if ($env == "local") {
    $prod_password = "somepassword";
  } else {
    $prod_password = prompt_silent("\nProd DB Password: ");
  }

  $drop_q = "DROP DATABASE `dbname`";
  $create_q = "CREATE DATABASE `dbname`";
  mysql_query($drop_q) or fail_migration(mysql_error());
  sysout("Dropped $env DB dbname");
  mysql_query($create_q) or fail_migration(mysql_error());
  sysout("Created $env DB dbname");

  echo exec("mysqldump dbname --user=".$config['prod']['user']." --host=".$config['prod']['host-no-port']." --password=$prod_password |
             mysql -D dbname --host=".$config[$env]['host-no-port']." --user=".$config[$env]['user']." --password=$password");

  sysout("Successfully pulled prod to staging?\n");

  mysql_select_db('dbname');

}

//select DB after so we can drop database if in staging


$done_count = 0;
$start_version = get_version();
$version = $start_version;

sysout("DB Version Pre-Migration: $start_version\n");

// Migrations should be in format:
$migrations = glob($file_format);
$migration_count = count($migrations);
sysout("Migration Files:");
foreach($migrations as $m) {
  sysout($m);
}

if ($migration_count < $start_version) fail_migration("[ERROR] DB Version is greater than number of migrations");
divider();

$loop_count = 1;
foreach ($migrations as $m) {
  $m_filename = basename($m);
  $m_version_arr = explode(".", $m_filename);
  $m_version = ltrim($m_version_arr[0], "0");

  sysout("$m_version: $m_filename");
  if ($loop_count != $m_version) {
    fail_migration("Bad migration sequence: $m_version, Expecting: $loop_count");
  }
  if ($m_version <= $version) {
    sysout("   [!] Already Completed");
    sysout();
  } else if ($m_version == $version + 1){

    include_once $m;

    sysout("   [+] Migration successfully run");

    $version = update_version($m_version);
    $done_count++;

    sysout("   [+] Version updated to: $version");
    sysout();
  } else {
    fail_migration("[ERROR] DB @ Version #: $version but trying to run Migration #$m__version.
          Check if migrations properly named and numbered");
  }

  $loop_count++;
}

sysout("\n\n----- ALL MIGRATIONS COMPLETE ------");
sysout("From Version: $start_version");
sysout("To Version: " . get_version());
sysout("# of Migrations run: $done_count\n\n\n");

function update_version($version){
  $table = "configs";
  $q = "UPDATE `dbname`.`$table`
        SET `value` = '$version'
        WHERE `key` = 'db_version'";
  mysql_query($q) or fail("[ERROR] Failed setting version");
  return $version;
}

function get_version() {
  $table = "configs";
  $q = "SELECT `value` FROM `dbname`.`$table` WHERE `key` = 'db_version'";
  $result = mysql_query($q) or fail("[ERROR] Failed getting version");;
  $ret = mysql_result($result, 0);
  return $ret;
}

function fail_migration($msg = "") {
  sysout($msg);
  fail("\n\n[ERROR] Migration killed. DB at version #". get_version() . "\n");
}

function mysql_query_with_errors($query) {
  return mysql_query($query) or fail_migration(mysql_error());
}

?>
