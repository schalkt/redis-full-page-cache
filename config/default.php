<?php

// default example config
return array(

    'debug' => false, // url hash disabled in redis
    'system' => 'redis',  // currently redis only
    'suffix' => 'dmafpc-local', // change this to unique, check laravel session prefix!!!
    'redis' => array(
        'password' => null,
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'timeout' => 2,
    ),
    'url' => array(
        'defaults' => array(
            'expire' => 3600, // cache expire time, 604800 = 1 week, 86400 = 1day, 0 = disabled
            'remove_query_strings' => true, // remove query params form url
            'remove_ending_slash' => true, // remove ending slash from url
            'hash' => 'md5', // store method of url : 'md5', 'sha1' or false (NOT secure!), recommended md5 or sha1
            'compress' => array(
                'comments' => true, // remove comments from html (except IE version comments)
                'eol' => true // remove line endings from html (\r\n)
            ),
        ),
        'rules' => array(
            array(
                'pattern' => '/\.(jpg|png|gif|css|js|ico|txt)/',
                'cache' => false
            ),
            array(
                'pattern' => '/\/api\/.*/',
                'cache' => false
            ),
            array(
                'pattern' => '/\/admin\/rest\/.*/',
                'remove_query_strings' => false, // remove query params form url
                'cache' => true,
                'expire' => 3600,
                'compress' => array(
                    'comments' => false, // remove comments from html (except IE version comments)
                    'eol' => false // remove line endings from html (\r\n)
                ),
            ),
            array(
                'pattern' => '/\/admin/',
                'expire' => 3600,
                'cache' => true,
                'compress' => array(
                    'comments' => false, // remove comments from html (except IE version comments)
                    'eol' => false // remove line endings from html (\r\n)
                ),
            ),
            array(
                'pattern' => '/.*/',
                'expire' => 604800,
                'cache' => true
            )
        ),
    ),
    'commands' => array(
        'key' => 'rfpc',  // change this to custom
        'skip' => 'preview', // skip cache : http://domain.tld/?rfpc=preview
        'save' => 'regenerate' // save cache : http://domain.tld/?rfpc=regenerate
    ),
    'ids' => array(
        'limit' => 4, // maximum number of separately stored module ids list
    ),
    'enable_http' => array(
        'method' => array(
            'GET',  // store only these methods, recommended only GET
        ),
        'status' => array(
            200, // store only these status codes, recommended only 200
            403,
        ),
    )
);