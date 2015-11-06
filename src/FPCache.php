<?php

namespace Schalkt\RedisFullPageCache;


/**
 * Redis full page cache
 */
class FPCache
{

	/**
	 * @var null
	 */
	protected static $config = array(

		'prefix'        => 'brite',
		'debug'         => true,
		'expire'        => 3600,
		'redis'         => array(
			'host' => '127.0.0.1',
			'port' => 6379,
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
			'/\/api\/.*/',
			'/\/admin\/.*/',
			'/\.(jpg|png|gif|css|js|ico|txt)/',
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

		if (!empty($_GET['rfpc'])) {

			// skip load and save
			if ($_GET['rfpc'] == 'skip') {
				return self::checkFalse();
			}

			// skip only load
			if ($action == 'load' && $_GET['rfpc'] == 'save') {
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
	 * Get clean URL
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
		$key = self::$config['prefix'] . ':' . md5(self::getUrl());

		$html = RedisLight::executeCommand('GET', array($key));

		if (empty($html)) {
			return false;
		}


		// get additional info
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

		if (!empty(self::$config['redis']) && defined('APP_START')) {
			$time = '<!-- ' . (microtime(true) - APP_START) . ' -->';
			$html = preg_replace('/<\/body>/i', $time . '</body>', $html, 1);
		}

		die($html);

	}

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

		$key = self::$config['prefix'] . ':' . md5(self::getUrl());
		$data = array(
			'headers' => headers_list(),
			'status'  => $status,
		);

		RedisLight::config(self::$config['redis']);
		RedisLight::executeCommand('SETEX', array($key, $expire, json_encode($data) . '|||' . $value));

	}

}


