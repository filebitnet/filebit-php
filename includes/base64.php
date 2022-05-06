<?php
namespace Filebit;
class CBase64 {
	public static function encode($data) {
		$data = base64_encode($data);
		$data = str_replace('+', '-', $data);
		$data = str_replace('/', '_', $data);
		$data = str_replace('=', '', $data);
		return $data;
	}

	public static function decode($data) {
		$data = str_replace('-', '+', $data);
		$data = str_replace('_', '/', $data);
		return base64_decode($data);
	}
}