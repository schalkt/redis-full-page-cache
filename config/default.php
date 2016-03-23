<?php

// default example config
return array(

	'debug'       => true, // show render time in hidden html tag at the bottom of html
	'system'      => 'redis',  // currently redis only
	'suffix'      => 'schache', // change this to unique
	'expire'      => 86400, // cache expire time, 604800 = 1 week, 86400 = 1day, 0 = disabled
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
		'hash'                 => 'md5', // store method of url : 'md5', 'sha1' or false (NOT secure!), recommended md5 or sha1
	),
	'commands'    => array(
		'key'  => 'rfpc',  // change this to custom
		'skip' => 'preview', // skip cache : http://domain.tld/?rfpc=preview
		'save' => 'regenerate' // save cache : http://domain.tld/?rfpc=regenerate
	),
	'ids'         => array(
		'limit' => 4, // maximum number of separately stored module ids list
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
			'api'   => '/\/api\/.*/',  // skip api url by regexp pattern
			'admin' => '/\/admin\/.*/',  // skip admin url by regexp pattern
			'files' => '/\.(jpg|png|gif|css|js|ico|txt)/',  // skip files by regexp pattern
		),
	),
);