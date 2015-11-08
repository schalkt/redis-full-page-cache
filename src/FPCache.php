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

		'debug'       => true, // show render time in hidden html tag at the bottom of html
		'system'      => 'redis',  // currently redis only
		'prefix'      => 'fpc', // change this to unique
		'expire'      => 604800, // cache expire time, 604800 = 1 week, 86400 = 1day
		'compress'    => array(
			'comments' => true, // remove comments from html (except IE version comments)
			'eol'      => true // remove line endings from html (\r\n)
		),
		'redis'       => array(
			'password' => null,
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'database' => 0,
			'timeout'  => 0,
		),
		'url'         => array(
			'remove_query_strings' => true, // remove query params form url
			'remove_ending_slash'  => true, // remove ending slash from url
			'hash'                 => 'md5', // store method of url : 'md5', 'sha1' or false
		),
		'commands'    => array(
			'key'  => 'rfpc',  // change this to custom
			'skip' => 'preview', // skip cache : http://domain.tld/?rfpc=preview
			'save' => 'regenerate' // save cache : http://domain.tld/?rfpc=regenerate
		),
		'ids'         => array(
			'limit' => 64, // maximum number of separately stored module ids
		),
		'enable_http' => array(
			'method' => array(
				'GET',  // store only these methods, recommended only GET
			),
			'status' => array(
				200, // store only these status codes, recommended only 200
			),
		),
		'skip'        => array(
			'patterns' => array(
				'/\/api\/.*/',  // skip api url by regexp pattern
				'/\/admin\/.*/',  // skip admin url by regexp pattern
				'/\.(jpg|png|gif|css|js|ico|txt)/',  // skip files by regexp pattern
			),
		),
	);

	/**
	 * @var null
	 */
	protected static $check = null;


	/**
	 * @var array
	 */
	protected static $mids = [];


	/**
	 * @param $key
	 *
	 * @return mixed
	 */
	public static function config($key)
	{

		return self::$config[$key];

	}


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

		foreach (self::$config['skip']['patterns'] as $pattern) {

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
	protected static function getUrl($url = null)
	{

		if (empty($url)) {
			$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}

		// remove query params
		if (!empty(self::$config['url']['remove_query_strings'])) {
			$pos = strpos($url, '?');
			if ($pos !== false) {
				$url = substr($url, 0, $pos);
			}
		}

		// remove last char if a slash
		if (!empty(self::$config['url']['remove_ending_slash'])) {
			if ($url[strlen($url) - 1] == '/') {
				$url = substr($url, 0, -1);
			};
		}

		return $url;

	}


	/**
	 * Generate key
	 *
	 * @param null $url
	 *
	 * @return string
	 */
	protected static function getKey($url)
	{

		$key = empty(self::$config['url']['hash']) ? $url : hash(self::$config['url']['hash'], $url);

		return self::$config['prefix'] . ':' . $key;
	}

	/**
	 * @param $key
	 * @param $id
	 *
	 * @return string
	 */
	protected static function getListKey($key, $id = '*')
	{

		$listkey = empty(self::$config['url']['hash']) ? $key . $id : hash(self::$config['url']['hash'], $key . $id);

		return self::$config['prefix'] . ':mids-' . $listkey;
	}


	/**
	 * Load cached page
	 *
	 * @param null $url
	 *
	 * @return bool
	 */
	public static function load($url = null)
	{

		if (empty($url)) {
			$url = self::getUrl();
		}

		switch (self::$config['system']) {

			case 'redis':
				$html = self::loadRedis($url);
				break;

		}

		if (empty($html)) {
			return false;
		}

		// get additional info (headers and http status)
		$pos = strpos($html, '|||');
		$json = substr($html, 0, $pos);
		$html = substr($html, $pos + 3);
		$data = json_decode($json, true);

		http_response_code($data['http_status']);
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
	 * @param array $params
	 *
	 * @return bool
	 */
	public static function save($params = array())
	{

		if (!isset($params['content'])) {
			return false;
		}

		if (self::check('save') === false) {
			return false;
		}

		$defaults = array(
			'http_status' => 200,
			'expire'      => self::$config['expire'],
			'url'         => self::getUrl(),
		);

		$params = array_replace($defaults, $params);


		if (!in_array($params['http_status'], self::$config['enable_http']['status'])) {
			return false;
		}

		$data = array(
			'headers'     => headers_list(),
			'http_status' => $params['http_status'],
		);

		$value = self::compress($params['content']);

		switch (self::$config['system']) {

			case 'redis':
				self::saveRedis($params['url'], $params['expire'], json_encode($data) . '|||' . $value);
				break;

		}

	}

	/**
	 * @return array|string|void
	 */
	protected static function loadRedis($url)
	{

		self::initRedis();

		return CacheRedis::executeCommand('GET', array(self::getKey($url)));

	}


	/**
	 * @param $expire
	 * @param $data
	 */
	protected static function saveRedis($url, $expire, $data)
	{

		self::initRedis();
		$urlkey = self::getKey($url);
		CacheRedis::executeCommand('SETEX', array($urlkey, $expire, $data));


		foreach (self::$mids as $module => $moduleId) {

			if (is_array(($moduleId))) {

				if (count($moduleId) > self::$config['ids']['limit']) {

					self::addToList($module, '*', $urlkey, $expire);

				} else {

					foreach ($moduleId as $index => $id) {
						self::addToList($module, $id, $urlkey, $expire);
					}
				}

			} else {

				self::addToList($module, $moduleId, $urlkey, $expire);
			}

		}

	}


	/**
	 * @param $module
	 * @param $moduleId
	 * @param $urlkey
	 * @param $expire
	 */
	protected static function addToList($module, $moduleId, $urlkey, $expire)
	{

		CacheRedis::executeCommand('LPUSH', array(self::getListKey($module, $moduleId), $urlkey));
		CacheRedis::executeCommand('EXPIRE', array(self::getListKey($module, $moduleId), $expire + rand(1, 60)));

	}


	/**
	 * @param string $url
	 */
	public static function deleteByUrl($url = null)
	{

		$url = self::getUrl($url);

		switch (self::$config['system']) {

			case 'redis':
				self::deleteRedis($url);
				break;

		}

	}


	/**
	 * Delete cached page
	 *
	 * @param null $url
	 */
	public static function deleteRedis($url)
	{

		self::initRedis();
		CacheRedis::executeCommand('DEL', array(self::getKey($url)));

	}

	/**
	 * Compress HTML
	 * To minify css or js use gulp tasks
	 *
	 * @param $html
	 *
	 * @return mixed
	 */
	protected static function compress($html)
	{

		// remove comments except IE version query <!--[if lt IE 9]>
		if (!empty(self::$config['compress']['comments'])) {
			$html = preg_replace('/<!--\s*[^\[](.*)-->/Uis', '', $html);
		}

		// remove all EOL character
		if (!empty(self::$config['compress']['eol'])) {
			$html = preg_replace('/[\r\n\t\s]+/s', ' ', $html);
		}

		return $html;

	}

	/**
	 * @param null $limit
	 *
	 * @return null
	 */
	public static function elementLimit($limit = null)
	{

		if ($limit !== null) {
			return self::$config['ids']['limit'] = $limit;
		} else {
			return self::$config['ids']['limit'];
		}

	}


	/**
	 * @param        $module
	 * @param string $moduleIds
	 *
	 * @return array
	 */
	public static function element($module, $moduleIds = '*')
	{

		if ($moduleIds === '*') {
			self::$mids[$module] = $moduleIds;

			return self::$mids;
		}

		if (isset(self::$mids[$module])) {
			if (self::$mids[$module] === '*') {
				return self::$mids;
			}
		}

		$moduleIds = (array)$moduleIds;

		if (isset(self::$mids[$module])) {

			self::$mids[$module] = array_merge(self::$mids[$module], $moduleIds);

		} else {
			self::$mids[$module] = $moduleIds;
		}

		return self::$mids;

	}


	/**
	 * @param      $module
	 * @param null $moduleId
	 */
	public static function deleteByModule($module, $moduleId = null)
	{


		// check list without id
		$listKey = self::getListKey($module);
		$list = CacheRedis::executeCommand('LRANGE', array($listKey, 0, -1));
		if (!empty($list)) {
			CacheRedis::executeCommand('DEL', $list);
		}

		// check list with id
		$listKey = self::getListKey($module, $moduleId);
		$list = CacheRedis::executeCommand('LRANGE', array($listKey, 0, -1));
		if (!empty($list)) {
			CacheRedis::executeCommand('DEL', $list);
		}

	}

	/**
	 * Delete all items from cache
	 */
	public static function flush()
	{

		self::initRedis();
		$keys = CacheRedis::executeCommand('KEYS', array(self::$config['prefix'] . ':*'));

		return CacheRedis::executeCommand('DEL', $keys);

	}


	/**
	 *
	 */
	protected static function initRedis()
	{

		require_once __DIR__ . '/CacheRedis.php';
		CacheRedis::config(self::$config['redis']);

	}

}

