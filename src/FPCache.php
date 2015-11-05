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

		'prefix' => 'brite',
		'debug'  => true,
		'redis'  => array(
			'host' => '127.0.0.1',
			'port' => 6379,
		),

	);

	/**
	 * @var null
	 */
	protected static $check = null;
	/**
	 * @var null
	 */
	protected static $data = null;
	/**
	 * @var string
	 */
	protected static $html = '';


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
			self::$check = false;

			return false;
		}

		if (!empty($_GET['rfpc'])) {

			// skip load and save
			if ($_GET['rfpc'] == 'skip') {
				return false;
			}

			// skip only load
			if ($action == 'load' && $_GET['rfpc'] == 'save') {
				return false;
			}

		}

		if (strpos($_SERVER['REQUEST_URI'], '/api') === 0 || strpos($_SERVER['REQUEST_URI'], '/admin')) {
			self::$check = false;

			return false;
		}

		if (isset($_GET['preview'])) {
			return false;
		}

		// is a file?
		$pos = strrpos($_SERVER['REQUEST_URI'], '.');
		if ($pos !== false) {

			$extension = substr($_SERVER['REQUEST_URI'], $pos + 1);
			$extensions = array('jpg', 'png', 'gif', 'css', 'js', 'ico', 'txt');
			if (in_array($extension, $extensions)) {
				self::$check = false;

				return false;
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

		if (!empty(self::$config['redis']) && defined('APP_START')) {
			$time = '<!-- ' . (microtime(true) - APP_START) . ' -->';
			$html = preg_replace('/<\/body>/i', $time . '</body>', $html, 1);
		}

		// clean ob and echo the cached full page
		if (ob_get_length()) ob_end_clean();
		die($html);

	}

	public static function save($value, $status = 200, $expire = 500)
	{

		if (self::check('save') === false) {
			return false;
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


