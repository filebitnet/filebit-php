<?php
namespace Filebit;
class CUpload {
	private $path;
	private $handle;
	private $crypto;
	private $api;
	private $key;
	private $iv;
	private $_filenameRaw;
	private $filename;
	private $filesize;
	private $filesizeFormatted;
	private $nksh;
	private $hash;
	private $upload_id;
	private $slices;
	private $servers;
	private $crcMap;
	private $upload_completed;
	private $fileid;
	private $admincode;
	private $_uploaded;
	private $progress;
	function __construct($path, $filename = false) {
		$this->path = $path;
		$this->handle = new \Filebit\CFile;
		$this->handle->open($path);
		$this->crypto = new \Filebit\Crypto\CCrypto;
		$this->keygen = new \Filebit\Crypto\CKeyGen;
		$this->api = new \Filebit\CApi;
		$this->key = $this->keygen->Get();
		$this->iv = $this->keygen->Get();
		$this->_filenameRaw = !($filename) ? basename($path) : $filename;
		$this->filename = $this->_makeFileName($path, $filename);
		$this->filesize = $this->handle->size();
		$this->filesizeFormatted = \Filebit\Utils\formatSize($this->filesize);
		$this->nksh = $this->crypto->nksh($this->_filenameRaw, $this->filesize, $this->key);
		$this->hash = $this->_makeHash();
		$this->upload_id = $this->_genUploadId();
		$this->slices = $this->crypto->getSliceOffset($this->filesize);
		$this->servers = $this->_getUploadServers();
		$this->crcMap = array();
		$this->upload_completed = false;
		$this->fileid = null;
		$this->_uploaded = 0;
		$this->progress = false;
	}

	function setProgress($state) {
		$this->progress = $state;
	}

	function __destruct() {
		$this->handle->close();
	}

	private function progressBar($done, $total) {
		$perc = floor(($done / $total) * 100);
		$left = 100 - $perc;
		$_done = \Filebit\Utils\formatSize($done);
		$write = sprintf("\033[0G\033[2K[%'={$perc}s>%-{$left}s] - $perc%% - $_done/$this->filesizeFormatted", "", "");
		fwrite(STDERR, $write);
	}

	private function _pickServer() {
		shuffle($this->servers);
		return $this->servers[0];
	}

	private function _getUploadServers() {
		$ServerResponse = $this->api->Call('storage/server.json');
		return $ServerResponse->checkin;
	}

	private function _makeHash() {
		return \Filebit\CSha256::packFile($this->path);
	}

	private function _makeFileName() {
		$encrypted = $this->crypto->encrypt($this->_filenameRaw, $this->key, $this->iv);
		return \Filebit\CBase64::encode($encrypted);
	}

	private function _genUploadId() {
		$Request = array(
			'name' => $this->filename,
			'size' => $this->filesize,
			'sha256' => $this->hash,
			'nksh' => $this->nksh,
		);

		$Response = $this->api->Call('storage/bucket/create.json', $Request);
		if (!$Response->id) {
			throw new \Exception('could not create upload id');
		}
		return $Response->id;
	}

	private function _storeUploadRequest($server, $upload_id) {
		ksort($this->crcMap);
		$sorted = array_values($this->crcMap);
		$sha256 = \Filebit\CSha256::pack(implode(",", $sorted));
		$Request = array(
			'uploadid' => $upload_id,
			'server' => $server,
			'sha' => $sha256,
			'chunks' => count($this->crcMap),
		);
		$Response = $this->api->Call('storage/bucket/finalize.json', $Request);
		if (isset($Response->error)) {
			throw new \Exception($Response->error);
		}
		$id = $Response->id;
		$hash = \Filebit\CBase64::encode($this->crypto->mergeKeyIv($this->key, $this->iv));
		$this->fileid = $id;
		$this->hash = $hash;
		$this->admincode = $Response->admincode;
		$this->upload_completed = true;

		return true;
	}

	function __progress($resource, $download_size, $downloaded, $upload_size, $uploaded) {
		if (!$this->progress) {
			return;
		}
		$bytesNow = ($uploaded - $this->_lastSize);
		if ($bytesNow > 0) {
			$this->_uploaded += $bytesNow;
			$this->progressBar($this->_uploaded, $this->filesize);
			$this->_lastSize = $uploaded;
		}
		return 0;
	}

	public function upload($progress = true) {
		$len = 0;
		if ($this->progress) {
			$this->progressBar(0, $this->filesize);
		}
		foreach ($this->slices as $chunk_id => $offset) {
			$Server = $this->_pickServer();
			$this->_lastSize = 0;
			$buffer = $this->handle->read($offset[0], $offset[1]);
			$SizeRequired = ($offset[1] - $offset[0]);
			if (strlen($buffer) != $SizeRequired) {
				throw new \Exception("Invalid filesize read...");
			}
			$encrypted = $this->crypto->encrypt($buffer, $this->key, $this->iv);
			$len += strlen($encrypted);
			$response = $this->api->upload($Server, $this->upload_id, $chunk_id, $offset, $encrypted, $this);
			$this->crcMap[$chunk_id] = $response->crc32;
			if ($this->progress) {
				$this->progressBar($len, $this->filesize);
			}
		}
		if ($this->progress) {
			echo PHP_EOL;
		}
		$Server = $this->_pickServer();
		$this->_storeUploadRequest($Server, $this->upload_id);
	}

	public function getLink() {
		if (!$this->upload_completed) {
			throw new \Exception('upload not yet finished');
		}
		return $this->api->getURL() . 'f/' . $this->fileid . '#' . $this->hash;
	}

	public function getAdminCode() {
		if (!$this->upload_completed) {
			throw new \Exception('upload not yet finished');
		}
		return $this->admincode;
	}
}