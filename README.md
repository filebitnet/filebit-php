## Check Filebit URL & Get Parts
```php
require_once "filebit.php";
$URL = 'https://filebit.net/f/qknI9LX#Ac1A3HJ13aBRn66XHnkktQNlOK1dxItiRqnkAovV82uU';
var_dump(\Filebit\Utils\isValidURL($URL)); // => true
var_dump(\Filebit\Utils\getParts($URL));
/*
array(2) {
  ["id"]=>
  string(7) "qknI9LX"
  ["key"]=>
  string(44) "Ac1A3HJ13aBRn66XHnkktQNlOK1dxItiRqnkAovV82uU"
}
*/
```


## Get Upload Server
```php
require_once "filebit.php";
$CApi = new Filebit\CApi();
$ServerResponse = $CApi->Call('storage/server.json');
var_dump($ServerResponse);
```

## Get File Informations
```php
require_once "filebit.php";
$CApi = new Filebit\CApi();
$CCrypto = new Filebit\Crypto\CCrypto;
$Response = $CApi->Call('storage/bucket/info.json', array("file" => "teBKKQ6"));
$EncryptedName = $Response->filename;

$Key = 'Abts8F6i70LmwgoeUrDe_8RWMmuXBtQj5C_BguRzJL-p';
$DecryptedKeys = $CCrypto->unmergeKeyIv(Filebit\CBase64::decode($Key));
$DecryptedName = $CCrypto->decrypt(Filebit\CBase64::decode($EncryptedName), Filebit\CBase64::decode($DecryptedKeys['key']), Filebit\CBase64::decode($DecryptedKeys['iv']));

echo $DecryptedName . " Filesize: " . Filebit\Utils\formatSize($Response->filesize) . PHP_EOL;
//Example.zip Filesize: 101.23 MiB
```

## Multi File Information(s)
```php
require_once "filebit.php";
$CApi = new Filebit\CApi;
$CCrypto = new Filebit\Crypto\CCrypto;

$FilesArray = array(
	'tlBKQQ6' => 'Abts8F6i70LmwgoeUrDe_1RWMmuXBtQj5C_BguRzJL-p',
	'AfAiPEM' => 'AbotIF8zJdU44b6cF_9f9kXIir_U5AmODfRiWE9xDo2U',
);

$Response = $CApi->Call('storage/multiinfo.json', array('files' => array_keys($FilesArray)));

foreach ((array) $Response as $id => $data) {
	$Key = $FilesArray[$id];
	$DecryptedKey = $CCrypto->unmergeKeyIv(Filebit\CBase64::decode($Key));
	$DecryptedName = $CCrypto->decrypt(Filebit\CBase64::decode($data->name), Filebit\CBase64::decode($DecryptedKey['key']), Filebit\CBase64::decode($DecryptedKey['iv']));
	echo '['.$data->state.'] '.$DecryptedName . " Filesize: " . Filebit\Utils\formatSize($data->size) . PHP_EOL;
}
```

## File Upload
```php
include "filebit.php";

$File2Upload = 'test.txt';

$UploadHandle = new \Filebit\CUpload($File2Upload);
$UploadHandle->setProgress(true);
$UploadHandle->upload();

echo "Done: " . $UploadHandle->getLink() . PHP_EOL;
```

## File Download
```php
include "filebit.php";

$URL = 'https://filebit.net/f/…#…';
$URLParts = \Filebit\Utils\getParts($URL);

$DownloadHandle = new \Filebit\CDownload($URLParts['id'], $URLParts['key']);
$DownloadHandle->setStoragePath('./test.txt');
$DownloadHandle->setProgress(true);
$DownloadHandle->setDebug(false);
$DownloadHandle->download();
```