<?php

namespace Schalkt\RedisFullPageCache;


/**
 * Class FPCache
 *
 * @package Schalkt\RedisFullPageCache
 */
class FPCache
{

	/**
	 * Config
	 *
	 * @var null
	 */
	protected static $config = array(

		'debug'         => true,
		'prefix'        => 'fpc', // change this to unique
		'expire'        => 3600, // cache expire time
		'redis'         => array(
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => null,
			'database' => 0,
			'timeout'  => 5,
		),
		'commands'      => array(
			'key'  => 'rfpc',  // change this to custom
			'skip' => 'preview', // skip cache : http://domain.com/?rfpc=preview
			'save' => 'regenerate' // save cache : http://domain.com/?rfpc=regenerate
		),
		'enable_http'   => array(
			'method' => array(
				'GET',
			),
			'status' => array(
				200,
			),
		),
		'skip_patterns' => array(
			'/\/api\/.*/',  // skip api url by regexp pattern
			'/\/admin\/.*/',  // skip admin url by regexp pattern
			'/\.(jpg|png|gif|css|js|ico|txt)/',  // skip files by regexp pattern
		),

	);

	/**
	 * @var null
	 */
	protected static $check = null;


	/**
	 * Set check to false and return false
	 *
	 * @return bool
	 */
	protected static function checkFalse()
	{

		self::$check = false;

		return false;

	}

	/**
	 * Check url
	 *
	 * @return bool|null
	 */
	protected static function check($action)
	{

		if (self::$check != null) {
			return self::$check;
		}

		if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
			return self::checkFalse();
		}

		if (!empty($_GET[self::$config['commands']['key']])) {

			$qp = $_GET[self::$config['commands']['key']];

			// skip load and save
			if ($qp == self::$config['commands']['skip']) {
				return self::checkFalse();
			}

			// skip only load
			if ($action == 'load' && $qp == self::$config['commands']['save']) {
				return self::checkFalse();
			}

		}

		if (!in_array($_SERVER['REQUEST_METHOD'], self::$config['enable_http']['method'])) {
			return self::checkFalse();
		}

		foreach (self::$config['skip_patterns'] as $pattern) {

			if (preg_match($pattern, $_SERVER['REQUEST_URI'], $mathces)) {
				return self::checkFalse();
			}
		}

	}


	/**
	 * Get cleaned URL
	 *
	 * @return string
	 */
	protected static function getUrl()
	{

		$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		// remove query params
		$pos = strpos($url, '?');
		if ($pos !== false) {
			$url = substr($url, 0, $pos);
		}

		// remove last char if a slash
		if ($url[strlen($url) - 1] == '/') {
			$url = substr($url, 0, -1);
		};

		return $url;

	}


	/**
	 * Generate key
	 *
	 * @param null $url
	 *
	 * @return string
	 */
	protected static function getKey($url = null)
	{
		return self::$config['prefix'] . ':' . hash('sha1', empty($url) ? self::getUrl() : $url);
	}


	/**
	 * Load cache
	 *
	 * @return bool
	 */
	public static function load()
	{

		if (self::check('load') === false) {
			return false;
		}

		RedisLight::config(self::$config['redis']);
		$html = RedisLight::executeCommand('GET', array(self::getKey()));

		if (empty($html)) {
			return false;
		}

		// get additional info (headers and http status)
		$pos = strpos($html, '|||');
		$json = substr($html, 0, $pos);
		$html = substr($html, $pos + 3);
		$data = json_decode($json, true);

		http_response_code($data['status']);
		foreach ($data['headers'] as $header) {
			header($header);
		}

		// clean ob and echo the cached full page
		if (ob_get_length()) ob_end_clean();

		if (!empty(self::$config['debug']) && defined('APP_START')) {
			$time = '<!-- ' . (microtime(true) - APP_START) . ' -->';
			$html = preg_replace('/<\/body>/i', $time . '</body>', $html, 1);
		}

		die($html);

	}

	/**
	 * @param      $value
	 * @param int  $status
	 * @param null $expire
	 *
	 * @return bool
	 */
	public static function save($value, $status = 200, $expire = null)
	{

		if (self::check('save') === false) {
			return false;
		}

		if (!in_array($status, self::$config['enable_http']['status'])) {
			return false;
		}

		if ($expire === null) {
			$expire = self::$config['expire'];
		}

		$data = array(
			'headers' => headers_list(),
			'status'  => $status,
		);

		RedisLight::config(self::$config['redis']);
		RedisLight::executeCommand('SETEX', array(self::getKey(), $expire, json_encode($data) . '|||' . $value));

	}

	public static function delete($url = null)
	{

		if (empty($url)) {
			$key = self::getKey();
		} else {
			$key = self::getKey($url);
		}

		RedisLight::config(self::$config['redis']);
		RedisLight::executeCommand('DEL', array($key));

	}

}

