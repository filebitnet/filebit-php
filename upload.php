<?php
include "filebit.php";

$File2Upload = $argv[1];

$UploadHandle = new \Filebit\CUpload($File2Upload);
$UploadHandle->setProgress(true);
$UploadHandle->upload();

echo "Done: " . $UploadHandle->getLink() . " Admincode: ".$UploadHandle->getAdminCode(). PHP_EOL;