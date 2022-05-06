<?php
include "filebit.php";

$URL = $argv[1];
$IsValidURL = \Filebit\Utils\IsValidURL($URL);
if (!$IsValidURL) {
	die('no valid filebit URL was provided' . PHP_EOL);
}
$URLParts = \Filebit\Utils\getParts($URL);
$tempfile = tempnam("/tmp", "dat");
echo "Will download to: " . $tempfile . PHP_EOL;
$DownloadHandle = new \Filebit\CDownload($URLParts['id'], $URLParts['key']);
$DownloadHandle->setStoragePath($tempfile);
$DownloadHandle->setProgress(true);
$DownloadHandle->setDebug(true);
$DownloadHandle->download();
