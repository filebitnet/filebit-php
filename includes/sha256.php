<?php
namespace Filebit;

class CSha256 {
	public static function pack($data) {
		return hash('sha256', $data);
	}

	public static function packFile($path) {
		return hash_file('sha256', $path);
	}
}