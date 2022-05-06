<?php
namespace Filebit;
class CDownload {
	function __construct($id, $key) {
		$this->_id = $id;
		$this->_path = '';
		$this->_st = false;
		$this->_progress = false;
		$this->_isDownloading = false;
		$this->handle = new \Filebit\CFile;
		$this->crypto = new \Filebit\Crypto\CCrypto;
		$this->api = new \Filebit\CApi;
		$this->chunks = array();
		$this->_filesize = 0;
		$this->_filesizeFormatted = 0;
		$this->_debug = false;

		$this->_waitingTime = 0;
		$this->_slotTicket = null;
		$this->_downloaded = 0;
		$this->_lastDownloadedForSure = 0;
		$this->_lastSize = 0;

		$this->_setKeyIv($key);
	}

	private function progressBar($done, $total, $useFormatted = false) {
		$perc = floor(($done / $total) * 100);
		$left = 100 - $perc;
		$_done = \Filebit\Utils\formatSize($done);
		$write = sprintf("\033[0G\033[2K[%'={$perc}s>%-{$left}s] - $perc%% - " . ($useFormatted ? $_done : $done) . "/" . ($useFormatted ? $this->_filesizeFormatted : $total) . "", "", "");
		fwrite(STDERR, $write);
	}

	private function _requestInfo() {
		$Request = array("file" => $this->_id);
		if ($this->_st) {
			$Request['st'] = $this->_st;
		}
		$Response = $this->api->Call('storage/bucket/info.json', $Request);
		if (isset($Response->error)) {
			throw new \Exception('file not found');
		}
		if ($Response->state != 'ONLINE') {
			throw new \Exception('file not found');
		}
		$this->_filename = $this->crypto->decrypt(\Filebit\CBase64::decode($Response->filename), $this->_key, $this->_iv);
		$this->_filesize = $Response->filesize;
		$this->_filesizeFormatted = \Filebit\Utils\formatSize($Response->filesize);
		$this->slot = $Response->slot;
		if (!$this->slot->isAvailable) {
			$error = (isset($this->slot->error)) ? $this->slot->error : 'filebit servers full, currently no free download available';
			throw new \Exception($error);
		}
		$this->_slotTicket = $this->slot->ticket;
		$this->_waitingTime = (int) $this->slot->wait;
		if (isset($Response->st) && $this->_st) {
			if ($Response->st->state != 'ok') {
				$this->_st = false;
			}
		}
		$this->_doWaitingTime();
	}

	private function _validateSlot() {
		$Request = array('slot' => $this->_slotTicket);
		$Response = $this->api->Call('file/slot.json', $Request);
		if (isset($Response->error)) {
			throw new \Exception($Response->error);
		}

		// do something with
		// $Response->config
		if (!$Response->success) {
			throw new \Exception('slot was not properly confirmed');
		}
	}

	private function _doWaitingTime() {
		if ($this->_waitingTime <= 0) {
			$this->_validateSlot();
			return;
		}
		$max = $this->_waitingTime;
		if ($this->_debug) {
			echo "Waitingtime (" . $max . ") Seconds" . PHP_EOL;
		}
		do {
			if ($this->_progress) {
				$this->progressBar(($max - $this->_waitingTime), $max);
			}
			--$this->_waitingTime;
			sleep(1);
		} while ($this->_waitingTime > 0);
		if ($this->_progress) {
			$this->progressBar($max, $max);
		}
		sleep(1);
		$this->_validateSlot();
	}

	private function _getChunkInfos() {
		$Response = $this->api->Call('storage/bucket/contents.json', array('id' => $this->_id));
		if (isset($Response->error)) {
			throw new \Exception($Response->error);
		}
		$this->chunks = $Response->chunks;
	}

	// this is a helper function for the progress bar, it get's called by curl
	function __progress($resource, $download_size, $downloaded, $upload_size, $uploaded) {
		if (!$this->_progress) {
			return;
		}
		$bytesNow = ($downloaded - $this->_lastSize);
		if ($bytesNow > 0) {
			$this->_downloaded += $bytesNow;
			$this->progressBar($this->_downloaded, $this->_filesize, true);
			$this->_lastSize = $downloaded;
		}
	}

	private function _internalDownloadChunk($chunkID, $offset0, $length, $downloadId) {

		$buf = $this->api->download($downloadId, $this->_slotTicket, $this);
		if ($buf === 'Forbidden') {
			// something went wrong...
			return false;
		}
		if (substr($buf, 0, 1) == '{' && substr($buf, -1) == '}') {
			// some error happened
			$json = json_decode($buf);
			if ($json->error) {
				echo 'Error: ' . $json->error . PHP_EOL;
			}
			return false;
		}
		$decrypted = $this->crypto->decrypt($buf, $this->_key, $this->_iv);
		if (strlen($decrypted) != $length) {
			throw new \Exception('Invalid buflength received');
		}
		$this->handle->write($offset0, $decrypted);
		$buf = null;
		return true;
	}

	private function _doDownloadFile() {
		foreach ($this->chunks as $chunkinfo) {
			$this->_lastSize = 0; // reset lastSize for each chunk...
			//id,offset0, offset1, length, crc32, downloadid
			$chunkID = $chunkinfo[0];
			$offset0 = $chunkinfo[1];
			$length = $chunkinfo[3];
			$downloadId = $chunkinfo[5];
			$try = 0;
			$success = false;
			do {
				$success = $this->_internalDownloadChunk($chunkID, $offset0, $length, $downloadId);
				if ($success) {
					$this->_lastDownloadedForSure = $this->_downloaded;
				} else {
					$this->_downloaded = $this->_lastDownloadedForSure;
					if ($this->_debug) {
						echo 'chunk ' . $chunkID . ' failed to download, will retry in ' . ($try * 5) . ' seconds' . PHP_EOL;
					}
					sleep($try * 5);
				}
				++$try;
				if ($try >= 5) {
					throw new \Exception('max amount of retrys (5) reached, aborting download...');
				}
			} while (!$success);
			// @TODO: most likely you should validate the contents downloaded with crc32
		}
		$this->handle->close();
		if ($this->_progress) {
			$this->progressBar($this->_filesize, $this->_filesize, true); // just correct the progress bar in case...
			echo PHP_EOL;
		}
		if ($this->_debug) {
			echo PHP_EOL . "the file was downloaded successfully..." . PHP_EOL;
		}
	}

	private function _setKeyIv($key) {
		$temp = $this->crypto->unmergeKeyIv(\Filebit\CBase64::decode($key));
		$this->_key = \Filebit\CBase64::decode($temp['key']);
		$this->_iv = \Filebit\CBase64::decode($temp['iv']);
	}

	private function writeChunk2File($offset, $chunk) {
		if (!$this->handle->isOpen) {
			throw new \Exception('could not write chunk, file is not yet open please call, setStoragePath(path) first.');
		}
		$this->handle->write($offset, $chunk);
	}

	public function setSpeedTicket($st) {
		$this->_st = $st;
	}

	public function setStoragePath($path) {
		$this->_path = $path;
		$this->handle->open($path, 'w+');
	}

	public function setProgress($state) {
		$this->_progress = $state;
	}

	public function setDebug($state) {
		$this->_debug = $state;
	}

	public function download() {
		if ($this->_isDownloading) {
			throw new \Exception('download already running');
		}
		$this->_isDownloading = true;
		$this->_requestInfo();

		$this->_getChunkInfos();
		$this->_doDownloadFile();
	}

}