<?php

namespace Schalkt\Schache;

use Illuminate\Support\Facades\Session;


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
    public static $config = null;

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
     * Load config and Redis class
     *
     * @param $configFile
     * @param $laravelConfig
     */

    public static function boot($configFile, $laravelConfig)
    {

        if (self::$config !== null) {
            return;
        }

        self::$config = self::loadConfig($configFile);

        if (self::check('load') === false) {
            return;
        }

        self::$config['unique'] = null;


        require_once __DIR__ . '/Redis.php';
        Redis::config(self::$config['redis']);

        self::sessionKeyGet($laravelConfig);
        self::load();

    }

    /**
     * Load config file
     *
     * @param $configFile
     * @return mixed
     */
    protected static function loadConfig($configFile)
    {

        if (file_exists($configFile)) {
            $config = require_once($configFile);
        }

        if (empty($config)) {
            $config = require_once(__DIR__ . '/../config/default.php');
        }

        return $config;

    }


    /**
     * Set cache key to Latavel session
     *
     * @param $key
     */
    public static function sessionKeyPut($key)
    {

        if (empty(self::$config['debug']) && !empty(self::$config['url']['defaults']['hash'])) {
            $key = hash(self::$config['url']['defaults']['hash'], $key);
        }

        Session::put('schache', $key);

    }


    public static function sessionKeyGet($laravelConfig)
    {

        if (!isset($_COOKIE[$laravelConfig['session']['cookie']])) {
            return;
        }

        require_once __DIR__ . '/Encrypter.php';

        // decrypt laravel 4.2 session :)
        $cookie = $_COOKIE[$laravelConfig['session']['cookie']];
        $crypt = new Encrypter($laravelConfig['app']['key']);
        $hash = $crypt->decrypt($cookie);
        $key = $laravelConfig['cache']['prefix'] . ':' . $hash;
        $data = Redis::executeCommand('GET', array($key));

        // if no session data go back
        if (empty($data)) {
            return;
        }

        $values = unserialize(unserialize($data));

        // if not found schache session key
        if (!isset($values['schache'])) {
            return;
        }

        self::$config['unique'] = $values['schache'];

    }


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

        foreach (self::$config['url']['rules'] as $rules) {

            if (!preg_match($rules['pattern'], $_SERVER['REQUEST_URI'], $mathces)) {
                continue;
            }

            if (isset($rules['cache']) && $rules['cache'] !== true) {
                return self::checkFalse();
            }

            self::$config['url']['defaults'] = array_merge(self::$config['url']['defaults'], $rules);
            break;

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
            $secure = !empty($_SERVER['HTTPS']) ? true : false;

        } else {

            $pos = strpos(strtolower($url), 'https://');

            if ($pos !== null) {
                $secure = ($pos === 0) ? true : false;
            } else {
                $secure = !empty($_SERVER['HTTPS']) ? true : false;
            }

        }

        // remove query params
        if (!empty(self::$config['url']['defaults']['remove_query_strings'])) {
            $pos = strpos($url, '?');
            if ($pos !== false) {
                $url = substr($url, 0, $pos);
            }
        }

        // remove last char if a slash
        if (!empty(self::$config['url']['defaults']['remove_ending_slash'])) {
            if ($url[strlen($url) - 1] == '/') {
                $url = substr($url, 0, -1);
            };
        }

        return $secure ? 'https://' . $url : 'http://' . $url;

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

        return self::getHash($url) . '-' . strtolower($_SERVER['REQUEST_METHOD']) . '-html:' . self::$config['unique'] . ':' . self::$config['suffix'];

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

        return $id . self::getHash($key) . '-list:' . self::$config['suffix'];

    }

    /**
     * Get the hashed key
     *
     * @param $key
     * @return string
     */
    protected static function getHash($key)
    {

        if ($key === null || !empty(self::$config['debug']) || empty(self::$config['url']['defaults']['hash'])) {
            return $key;
        }

        return hash(self::$config['url']['defaults']['hash'], $key);

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

        list($html, $data) = self::get($url);

        if (empty($html)) {
            return;
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        if (!empty($data)) {
            http_response_code($data['http_status']);
            foreach ($data['headers'] as $header) {
                header($header);
            }
        }

        exit($html);

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

        switch (self::$config['driver']) {

            case 'redis':
                $html = self::loadRedis($url);
                break;

        }

        if (empty($html)) {
            return array(null, null);
        }

        // get additional info (headers and http status)
        list($html, $data) = self::prepareData($html);

        if (defined('APP_START')) {
            $data['headers'][] = 'X-FPCache: ' . (microtime(true) - APP_START);
        }

        self::log('GET', $url);

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
            'expire' => self::$config['url']['defaults']['expire'],
            'url' => self::getUrl(),
        );

        if (!empty($params['headers'])) {
            foreach ($params['headers'] as $key => $value) {
                $headers[] = $key . ': ' . $value[0];
            }
            $params['headers'] = array_merge(headers_list(), $headers);
        } else {
            $params['headers'] = headers_list();
        }

        $params = array_replace_recursive($defaults, $params);

        if (!in_array($params['http_status'], self::$config['enable_http']['status'])) {
            return false;
        }

        if (isset($params['config'])) {
            self::$config = array_replace_recursive(self::$config, $params['config']);
        }

        self::$config['unique'] = !empty($params['unique']) ? $params['unique'] : null;

        $data = array(
            'http_status' => $params['http_status'],
            'url' => $params['url'],
            'expire' => (int)$params['expire'],
            'headers' => $params['headers'],
        );

        $value = self::compress($params['content']);

        switch (self::$config['driver']) {

            case 'redis':
                self::saveRedis(
                    $params['url'],
                    $params['expire'],
                    json_encode($data) . '|||' . $value);
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

        //self::boot();

        self::$config['unique'] = self::getHash(self::$config['unique']);
        $urlKey = self::getKey($url);

        Redis::executeCommand('SETEX', array($urlKey, $expire, $data));

        self::log('SAVE', $url);

        foreach (self::$mids as $module => $moduleId) {

            if (is_array(($moduleId))) {

                if (count($moduleId) > self::$config['ids']['limit']) {
                    self::addToList($module, '', $urlKey, $expire);
                } else {

                    foreach ($moduleId as $index => $id) {
                        self::addToList($module, $id, $urlKey, $expire);
                    }
                }

            } else {

                self::addToList($module, $moduleId, $urlKey, $expire);
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

        self::log('LIST ADD', [$module, $moduleId, $urlkey]);

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

        //self::boot();

        $url = self::getUrl($url);
        self::log('DEL', $url);
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
        // works only if no javascript comments in script tags
        if (!empty(self::$config['url']['defaults']['compress']['comments'])) {
            $html = preg_replace('/<!--\s*[^\[](.*)-->/Uis', '', $html);
        }

        // remove all EOL character
        if (!empty(self::$config['url']['defaults']['compress']['eol'])) {
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

        self::log('LIST DEL', [$module, $moduleId, $listKey]);

        if (!empty($list)) {
            Redis::executeCommand('DEL', $list);
        }

        // check list with wildcard, if new or deleted record
        if ($moduleId === null) {

            $listKey = self::getListKey($module, '*');
            $lists = Redis::executeCommand('KEYS', array($listKey));

            if (!empty($lists)) {
                foreach ($lists as $listKey) {
                    $list = Redis::executeCommand('LRANGE', array($listKey, 0, -1));
                    if (!empty($list)) {
                        self::log('LIST DEL', [$module, $moduleId, $listKey]);
                        Redis::executeCommand('DEL', $list);
                    }
                }
            }

        } else {

            // check list with id
            $listKey = self::getListKey($module, $moduleId);
            $list = Redis::executeCommand('LRANGE', array($listKey, 0, -1));
            if (!empty($list)) {
                self::log('LIST DEL', [$module, $moduleId, $listKey]);
                Redis::executeCommand('DEL', $list);
            }

        }

    }


    /**
     * Custom logging because composer autoload not loaded
     *
     * @param $entry
     */
    protected static function log($cmd, $entry)
    {

        if (empty(self::$config['debug'])) {
            return;
        }

        $entry = is_array($entry) ? implode(' | ', $entry) : $entry;
        $logfile = $_SERVER['DOCUMENT_ROOT'] . '/..' . self::$config['log'];
        file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $cmd . ' ' . $entry . PHP_EOL, FILE_APPEND);

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
     * Delete items from cache by pattern
     */
    public static function flush($pattern = null)
    {

        //self::boot();

        if ($pattern === null) {
            $pattern = '*:' . self::$config['suffix'];
        }

        $keys = Redis::executeCommand('KEYS', array($pattern));

        if (!empty($keys)) {
            return Redis::executeCommand('DEL', $keys);
        }

    }


    /**
     * Walk
     *
     * @param $callback
     */
    public static function walk($callback)
    {

        self::boot();

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

