<?php

namespace Schalkt\Schache;


/**
 * Class Redis
 *
 * @package Schalkt\Schache
 */
class Redis
{

	/**
	 * Default config
	 *
	 * @var array
	 */
	private static $config = [
		'host'     => '127.0.0.1',
		'port'     => 6379,
		'password' => null,
		'database' => 0,
		'timeout'  => 1,
	];

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
			self::$config['host'] . ':' . self::$config['port'], $errorNumber, $errorDescription,
			self::$config['timeout'] ? self::$config['timeout'] : ini_get("default_socket_timeout")
		);

		if (self::$_socket) {

			if (self::$config['password'] !== null) {
				self::executeCommand('AUTH', array(self::$config['password']));
			}

			self::executeCommand('SELECT', array(self::$config['database']));

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

		if (self::$_socket === null) {
			self::connect();
		}

		if (empty($params[0])) {
			return;
		}

		array_unshift($params, $name);
		$command = '*' . count($params) . "\r\n";

		if ($params[0] == 'SETEX') {
			if (empty($params[2])) {
				return;
			}
		}

		foreach ($params as $arg) {
			$command .= '$' . strlen($arg) . "\r\n" . $arg . "\r\n";
		}

		fwrite(self::$_socket, $command);

		return self::parseResponse(implode(' ', $params));

	}


	/**
	 * Parse Redis response
	 *
	 * @return array|bool|string
	 */
	private static function parseResponse()
	{

		if (($line = fgets(self::$_socket)) === false) {
			self::error('Failed reading data from cache connection socket.');
		}

		$type = $line[0];
		$line = substr($line, 1, -2);

		switch ($type) {

			case '+': // status reply
				return true;

			case '-': // error reply
				self::error('Cache server error' . $line);

			case ':': // Integer reply
				// no cast to int as it is in the range of a signed 64 bit integer
				return $line;

			case '$': // bulk replies

				if ($line == '-1') {
					return null;
				}

				$length = $line + 2;
				$data = '';

				while ($length > 0) {

					if (($block = fread(self::$_socket, $length)) === false) {
						self::error('Failed reading data from cache connection socket.');
					}
					$data .= $block;
					$length -= (function_exists('mb_strlen') ? mb_strlen($block, '8bit') : strlen($block));

				}

				return substr($data, 0, -2);

			case '*': // multi-bulk replies

				$count = (int)$line;
				$data = array();

				for ($i = 0; $i < $count; $i++) {
					$data[] = self::parseResponse();
				}

				return $data;

			default:

				self::error('Unable to parse data received from cache.');
		}
	}


	/**
	 * Set redis config
	 *
	 * @param $config
	 */
	public static function config($config)
	{

		self::$config = array_replace(self::$config, $config);

	}

	/**
	 * Error page
	 *
	 * @param string $msg
	 */
	private static function error($msg = '')
	{

		header("HTTP/1.1 503 Service Unavailable");
		require_once(__DIR__ . '/../views/error.php');
		die();

	}

}