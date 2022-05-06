<?php
namespace Filebit\Utils;
function formatSize($bytes) {
	$tresh = 1024;
	if (abs($bytes) < $tresh) {
		return $bytes . ' B';
	}
	$units = array('KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB');
	$u = -1;
	do {
		$bytes /= $tresh;
		++$u;
	} while (abs($bytes) >= $tresh && $u < (count($units) - 1));
	$temp = explode(".", $bytes);
	if (!isset($temp[1]) || $temp[1] == 0) {
		return $temp[0] . ' ' . $units[$u];
	}
	return round($bytes, 2) . ' ' . $units[$u];
}

function getRegex() {
	return '/https?:\/\/(www\.|.*\.)?(?P<tld>filebit\.ch|filebit\.net|filebit\.org)\/f\/(?P<id>([a-zA-Z0-9]+){6,9})((\?|&)(.+?))?#(?P<key>([a-zA-Z0-9-_]+){16,25})$/';
}

function isValidURL($url) {
	preg_match(\Filebit\Utils\getRegex(), $url, $match);
	return (isset($match['tld']) && isset($match['id']) && isset($match['key']));
}

function getParts($url) {
	preg_match(\Filebit\Utils\getRegex(), $url, $match);
	if (!isset($match['id']) || !isset($match['key'])) {
		return false;
	}
	return array(
		'id' => $match['id'],
		'key' => $match['key'],
	);
}