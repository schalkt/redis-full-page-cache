<?php

namespace Schalkt\RedisFullPageCache;

/**
 * Class RedisLight
 */
class RedisLight
{

	/**
	 * @var string
	 */
	private static $hostname = null;
	/**
	 * @var int
	 */
	private static $port = null;
	/**
	 * @var
	 */
	private static $password;
	/**
	 * @var int
	 */
	private static $database = 0;
	/**
	 * @var int
	 */
	private static $timeout = 5;
	/**
	 * @var
	 */
	private static $_socket;


	/**
	 * Connect to Redis
	 */
	private static function connect()
	{

		self::$_socket = @stream_socket_client(
			self::$hostname . ':' . self::$port,
			$errorNumber,
			$errorDescription,
			self::$timeout ? self::$timeout : ini_get("default_socket_timeout")
		);
		if (self::$_socket) {
			if (self::$password !== null)
				self::executeCommand('AUTH', array(self::$password));
			self::executeCommand('SELECT', array(self::$database));
		} else {
			self::error('Failed to connect to the cache server');
		}
	}


	/**
	 * Execute Redis command
	 *
	 * @param       $name
	 * @param array $params
	 *
	 * @return array|string|void
	 */
	public static function executeCommand($name, $params = array())
	{

		if (self::$_socket === null)
			self::connect();

		// ha üres a key akkor nincs mit lekérdezni és a twemproxy el is fekszik emiatt
		if (empty($params[0])) {
			return;
		}

		array_unshift($params, $name);
		$command = '*' . count($params) . "\r\n";

		// ha SETEX jön, de a time 0, akkor vissza, mert Redis nem szereti
		if ($params[0] == 'SETEX') {
			if (empty($params[2])) {
				return;
			}
		}

		foreach ($params as $arg)
			$command .= '$' . strlen($arg) . "\r\n" . $arg . "\r\n";

		fwrite(self::$_socket, $command);

		return self::parseResponse(implode(' ', $params));

	}


	/**
	 * Default error page
	 *
	 * @param string $msg
	 */
	private static function error($msg = '')
	{

		header("HTTP/1.1 503 Service Unavailable");
		require_once('error.php');
		die();

	}


	/**
	 * Parse Redis response
	 *
	 * @return array|bool|string
	 */
	private static function parseResponse()
	{

		if (($line = fgets(self::$_socket)) === false)
			self::error('Failed reading data from cache connection socket.');
		$type = $line[0];
		$line = substr($line, 1, -2);

		switch ($type) {
			case '+': // Status reply
				return true;

			case '-': // Error reply
				self::error('Cache server error' . $line);

			case ':': // Integer reply
				// no cast to int as it is in the range of a signed 64 bit integer
				return $line;

			case '$': // Bulk replies
				if ($line == '-1')
					return null;
				$length = $line + 2;
				$data = '';
				while ($length > 0) {
					if (($block = fread(self::$_socket, $length)) === false)
						self::error('Failed reading data from cache connection socket.');
					$data .= $block;
					$length -= (function_exists('mb_strlen') ? mb_strlen($block, '8bit') : strlen($block));
				}

				return substr($data, 0, -2);

			case '*': // Multi-bulk replies
				$count = (int)$line;
				$data = array();
				for ($i = 0; $i < $count; $i++)
					$data[] = self::parseResponse();

				return $data;

			default:
				self::error('Unable to parse data received from cache.');
		}
	}


	/**
	 * Redis config
	 *
	 * @param $params
	 */
	public static function config($params)
	{

		self::$hostname = $params['host'];
		self::$port = $params['port'];

	}

}