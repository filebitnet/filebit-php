<?php
namespace Filebit;
class CApi {
	private $endpoint = 'https://filebit.net/';
	private $fqdn = 'https://filebit.net/';
	private $ssl = true;
	private $ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3831.6 Safari/537.36';

	function getURL() {
		return $this->fqdn;
	}

	private function _get($url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->ua);
		$response = curl_exec($ch);
		curl_close($ch);
		return json_decode($response);
	}

	private function _post($url, array $params) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
		curl_setopt($ch, CURLOPT_USERAGENT, $this->ua);
		$response = curl_exec($ch);
		curl_close($ch);
		return json_decode($response);
	}

	public function download($downloadId, $slotId, $parent) {
		// never point at a specific download server, always point at the mainserver, which will provide a redirect
		$url = $this->endpoint . 'download/' . $downloadId . '?slot=' . $slotId;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->ua);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // this is important, since we need to follow to the correct current download server
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);
		curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array($parent, '__progress'));
		$content = curl_exec($ch);
		curl_close($ch);
		return $content;
	}

	public function upload($server, $upload_id, $chunk_id, $offset, $buffer, $parent) {
		$tempfile = tempnam("/tmp", "dat");
		file_put_contents($tempfile, $buffer);
		$cf = new \CURLFile($tempfile);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, (($this->ssl) ? 'https' : 'http') . '://' . $server . '/storage/bucket/' . $upload_id . '/add/' . $chunk_id . '/' . $offset[0] . '-' . $offset[1]);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, ["file" => $cf]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->ua);
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);
		curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array($parent, '__progress'));
		$result = curl_exec($ch);
		curl_close($ch);
		unlink($tempfile);
		return json_decode($result);

	}

	public function Call(string $endpoint, array $postData = array()) {
		$url = $this->endpoint . $endpoint;
		if (count($postData) > 0) {
			return $this->_post($url, $postData);
		}
		return $this->_get($url);
	}
}