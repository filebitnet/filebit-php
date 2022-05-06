<?php
namespace Filebit\Crypto;

class CKeyGen {
	function Get($bits = 128) {
		// by dividing / 8 we transform out bits in bytes which are required
		return random_bytes($bits / 8);
	}
}
class CCrypto {
	function pack($arr) {
		$out = '';
		foreach ($arr as $c) {
			$out .= pack('C*', $c);
		}
		return $out;
	}

	function nksh(string $name, int $size, string $key) {
		$key64 = \Filebit\CBase64::encode($key);
		$nkh = \Filebit\CSha256::pack($key64 . $name . $key64);
		$encr = "{n:$name:s$size:k$key64}";
		$sha = \Filebit\CSha256::pack($encr . $nkh);
		return $sha;
	}

	function mergeKeyIv($key, $iv) {
		$bufArr = array(1);
		$_key = array_values(unpack('C*', $key));
		$_iv = array_values(unpack('C*', $iv));
		$len = count($_key) + count($_iv);
		for ($i = 0; $i < $len; ++$i) {
			$posInBuf = floor($i / 2);
			$bit = ($i % 2) ? $_key[$posInBuf] : $_iv[$posInBuf];
			array_push($bufArr, $bit);
		}
		return $this->pack($bufArr);
	}

	function unmergeKeyIv(string $key) {
		$buf = array_values(unpack('C*', $key));
		if (!!((count($buf) - 1) % 2)) {
			throw new \Exception('invalid key provided');
		}
		$version = $buf[0];
		$keys = array_slice($buf, 1);
		$_key = array();
		$_iv = array();
		for ($i = 0; $i < count($keys); ++$i) {
			$bit = $keys[$i];
			if ($i % 2) {
				array_push($_key, $bit);
			} else {
				array_push($_iv, $bit);
			}
		}
		return array(
			'version' => $version,
			'key' => \Filebit\CBase64::encode($this->pack($_key)),
			'iv' => \Filebit\CBase64::encode($this->pack($_iv)),
		);
	}

	function decrypt($data, $key, $iv) {
		return openssl_decrypt($data, "aes-128-cbc", $key, 1, $iv);
	}

	function encrypt($data, $key, $iv) {
		return openssl_encrypt($data, "aes-128-cbc", $key, 1, $iv);
	}

	function getSliceOffset($fileSize) {
		$chunklist = array();
		$bytesDone = 0;
		$offsetA = 512000;
		$offsetB = 67108864;
		for ($i = 1; $i <= 8; ++$i) {
			if ($bytesDone < $fileSize) {
				$bytes = min($i * $offsetA, $fileSize);
				array_push($chunklist, array($bytesDone, min($fileSize, $bytesDone + $bytes)));
				$bytesDone += $bytes;
			}
		}
		while ($bytesDone < $fileSize) {
			array_push($chunklist, array($bytesDone, min($fileSize, $bytesDone + $offsetB)));
			$bytesDone += $offsetB;
		}
		return $chunklist;
	}
}