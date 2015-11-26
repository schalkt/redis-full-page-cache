<?php

namespace Schalkt\Schache;

/**
 * Class FPCache
 *
 * @package Schalkt\Schache
 */
class FPCache
{

	/**
	 * Config
	 *
	 * @var null
	 */
	public static $config = array(

		'debug'       => true, // show render time in hidden html tag at the bottom of html
		'system'      => 'redis',  // currently redis only
		'suffix'      => 'fpc-brite', // change this to unique
		'expire'      => 5, // cache expire time, 604800 = 1 week, 86400 = 1day, 0 = disabled
		'compress'    => array(
			'comments' => true, // remove comments from html (except IE version comments)
			'eol'      => true // remove line endings from html (\r\n)
		),
		'redis'       => array(
			'password' => null,
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'database' => 0,
			'timeout'  => 2,
		),
		'url'         => array(
			'remove_query_strings' => true, // remove query params form url
			'remove_ending_slash'  => true, // remove ending slash from url
			'hash'                 => 'md5', // store method of url : 'md5', 'sha1' or false, recommended md5 or sha1
		),
		'commands'    => array(
			'key'  => 'rfpc',  // change this to custom
			'skip' => 'preview', // skip cache : http://domain.tld/?rfpc=preview
			'save' => 'regenerate' // save cache : http://domain.tld/?rfpc=regenerate
		),
		'ids'         => array(
			'limit' => 7, // maximum number of separately stored module ids list
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
	 * Module and module id relations
	 *
	 * @var array
	 */
	protected static $mids = [];


	/**
	 * Get config param
	 *
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

		if ($action == 'load') {
			return;
		}


		if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
			return self::checkFalse();
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
	 * Get the cache key
	 *
	 * @param null $url
	 *
	 * @return string
	 */
	protected static function getKey($url)
	{

		$key = empty(self::$config['url']['hash']) ? $url : hash(self::$config['url']['hash'], $url);

		return $key . '-' . strtolower($_SERVER['REQUEST_METHOD']) . '-html:' . self::$config['suffix'];
	}

	/**
	 * Get the list key
	 *
	 * @param $key
	 * @param $id
	 *
	 * @return string
	 */
	protected static function getListKey($key, $id = '')
	{

		$name = $key . '#' . $id;
		$listkey = empty(self::$config['url']['hash']) ? $name : hash(self::$config['url']['hash'], $name);

		return $listkey . '-list:' . self::$config['suffix'];

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

		if (self::check('load') === false) {
			return null;
		}

		list($html, $data) = self::get($url);

		if (empty($html)) {
			return;
		}

		if (!empty($data)) {
			http_response_code($data['http_status']);
			foreach ($data['headers'] as $header) {
				header($header);
			}
		}

		if (ob_get_length()) ob_end_clean();
		die($html);

	}


	/**
	 * Get cached page
	 *
	 * @param null $url
	 *
	 * @return bool
	 */
	public static function get($url = null)
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
			return array(null, null);
		}

		// get additional info (headers and http status)
		list($html, $data) = self::prepareData($html);

		if (!empty(self::$config['debug']) && defined('APP_START')) {
			$time = '<!-- ' . (microtime(true) - APP_START) . ' -->';
			$html = preg_replace('/<\/body>/i', $time . '</body>', $html, 1);
		}

		return array($html, $data);

	}

	/**
	 * Split params and html from cached data
	 *
	 * @param $html
	 *
	 * @return array
	 */
	protected static function prepareData($html)
	{

		// get additional info (headers and http status)
		$pos = strpos($html, '|||');
		$json = substr($html, 0, $pos);
		$html = substr($html, $pos + 3);
		$data = json_decode($json, true);

		return array($html, $data);

	}


	/**
	 * Save page to cache
	 *
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
			'headers'     => headers_list(),
		);

		$params = array_replace($defaults, $params);

		if (!in_array($params['http_status'], self::$config['enable_http']['status'])) {
			return false;
		}

		if (isset($params['compress'])) {
			self::$config['compress'] = $params['compress'];
		}

		$data = array(
			'http_status' => $params['http_status'],
			'url'         => $params['url'],
			'expire'      => $params['expire'],
			'headers'     => $params['headers'],
		);

		$value = self::compress($params['content']);

		switch (self::$config['system']) {

			case 'redis':
				self::saveRedis($params['url'], $params['expire'], json_encode($data) . '|||' . $value);
				break;

		}

	}

	/**
	 * Load cached page
	 *
	 * @return array|string|void
	 */
	protected static function loadRedis($url)
	{

		self::initRedis();

		return Redis::executeCommand('GET', array(self::getKey($url)));

	}


	/**
	 * Save redis cache and relations list
	 *
	 * @param $expire
	 * @param $data
	 */
	protected static function saveRedis($url, $expire, $data)
	{

		self::initRedis();
		$urlkey = self::getKey($url);
		Redis::executeCommand('SETEX', array($urlkey, $expire, $data));

		foreach (self::$mids as $module => $moduleId) {

			if (is_array(($moduleId))) {

				if (count($moduleId) > self::$config['ids']['limit']) {
					self::addToList($module, '', $urlkey, $expire);
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
	 * Add module and module ids to the relation list
	 *
	 * @param $module
	 * @param $moduleId
	 * @param $urlkey
	 * @param $expire
	 */
	protected static function addToList($module, $moduleId, $urlkey, $expire)
	{

		Redis::executeCommand('LPUSH', array(self::getListKey($module, $moduleId), $urlkey));
		Redis::executeCommand('EXPIRE', array(self::getListKey($module, $moduleId), $expire + rand(1, 60)));

	}


	/**
	 * Delete cached page by url
	 *
	 * @param null $url
	 */
	public static function deleteByUrl($url = null)
	{

		self::initRedis();
		$url = self::getUrl($url);
		Redis::executeCommand('DEL', array(self::getKey($url)));

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
	 * Get or set the module ids limit / module / page
	 *
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
	 * Store module and id for cached page relation
	 *
	 * @param        $module
	 * @param string $moduleIds
	 *
	 * @return array
	 */
	public static function element($module, $moduleIds = '')
	{

		if ($moduleIds === '') {
			self::$mids[$module] = $moduleIds;

			return self::$mids;
		}

		if (isset(self::$mids[$module])) {
			if (self::$mids[$module] === '') {
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
	 * Delete cached pages by module and id
	 *
	 * @param      $module
	 * @param null $moduleId
	 */
	public static function deleteByModule($module, $moduleId = null, $regenerate = null)
	{

		// check list without id
		$listKey = self::getListKey($module);
		$list = Redis::executeCommand('LRANGE', array($listKey, 0, -1));

		if (!empty($list)) {


			Redis::executeCommand('DEL', $list);
		}

		// check list with id
		$listKey = self::getListKey($module, $moduleId);
		$list = Redis::executeCommand('LRANGE', array($listKey, 0, -1));
		if (!empty($list)) {
			Redis::executeCommand('DEL', $list);
		}

	}

	public static function regenerate()
	{

		// redirecting...
		// TODO
		ignore_user_abort(true);
		set_time_limit(0);
		ob_end_flush();
		flush();
		fastcgi_finish_request(); // important when using php-fpm!
		sleep(5); // User won't feel this sleep because he'll already be away
		// do some work after user has been redirected

	}

	/**
	 * Delete all items from cache
	 */
	public static function flush()
	{

		self::initRedis();
		$keys = Redis::executeCommand('KEYS', array('*:' . self::$config['suffix']));

		return Redis::executeCommand('DEL', $keys);

	}


	/**
	 * Load Redis Cache Class and set config
	 */
	protected static function initRedis()
	{

		require_once __DIR__ . '/Redis.php';
		Redis::config(self::$config['redis']);

	}


	/**
	 * Walk
	 *
	 * @param $callback
	 */
	public static function walk($callback)
	{

		self::initRedis();

		// get all cached html page keys
		$keys = Redis::executeCommand('KEYS', array('*-html:' . self::$config['suffix']));

		foreach ($keys as $key) {

			// get a html page data
			$html = Redis::executeCommand('GET', array($key));

			// split params and html
			list($html, $params) = self::prepareData($html);

			// for html modification checking
			$crc = crc32($html);

			if (is_callable($callback)) {

				$html = $callback($html, $params);

				// html modified?
				if ($crc != crc32($html)) {
					$params['content'] = $html;
					self::save($params);
				}

			}
		}

	}

}

