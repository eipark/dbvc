#!/usr/bin/php
<?
require_once('../helpers.php');
require_once("../aws-sdk/sdk.class.php");

if (empty($argv[1])) $password = prompt_silent("Prod DB Password: ");
else $password = $argv[1];

/* ---------- START CONFIG -------------- */
date_default_timezone_set('America/Los_Angeles');

$s3_db_prod_bucket = "proddb-dump-bucket";

/* ---------- END CONFIG -------------- */

$prefix = "dbprefix-";
$tmpDir = "tmp/";
$sqlFile = $tmpDir.$prefix.date('Ymd_hisA').".sql";
$attachment = $tmpDir.$prefix.date('Ymd_hisA').".tgz";

$createBackup = "mysqldump somedbname -u dbuser --password=$password --host=dbhost.com > $sqlFile";
$createZip = "tar cvzf $attachment $sqlFile";
exec($createBackup);
exec($createZip);

if (!file_exists($attachment) || !is_file($attachment)) {
  fail("[ERROR] Couldn't run mysqldump successfully");
}

$s3 = new AmazonS3();
$result = false;
if ($s3->create_object($s3_db_prod_bucket, baseName($attachment), array(
  'acl' => $s3::ACL_PRIVATE,
  'fileUpload' => $attachment))) {
  $result = baseName($attachment);
}

unlink($sqlFile);
unlink($attachment);

if ($result) die("Backup to S3 success: $result\n");
else fail("[ERROR] Could not back up to S3");

?>
