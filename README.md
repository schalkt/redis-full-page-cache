# schache

**Redis full page cache for Laravel 4.2**

> The project is under development, but this is a working and stable version.


Install
---

Download from here:  [https://github.com/schalkt/schache](https://github.com/schalkt/schache)
> Composer reguire coming soon.

Setup in Laravel 4.2
---
1. Copy default configuration file (schache/config/default.php) to custom location
```
'prefix' => 'fpc-sitename',
'debug'  => true,
'redis'  => array(
    'host' => '127.0.0.1',
    'port' => 6379,
)
```

Setup session store to Redis or

2. Put these lines to the top of public/index.php 
```
define('APP_START', microtime(true)); // require for render time calculation
require_once __DIR__.'/../vendor/schalkt/schache/src/FPCache.php'; // 10 times faster than composer autoload :)
Schalkt\Schache\FPCache::boot(__DIR__ . '/../app/config/schache.php'); // boot cache system and load the custom config file
Schalkt\Schache\FPCache::load(); // show the page from the cache if available
```
3. Save the page content in the Controller after rendering the view
```
$content = View::make('index');
Schalkt\Schache\FPCache::save(array(
    'content' => (string)$content) // required
    'http_status' => 200, // not required, default 200
	'expire'      => 3600, // not required, default in FPCache::$config['expire']
	'url'         => 'www.domain.tld/shop' // not required, default $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], see FPCache::$config['url']
	'headers'     => null // not required, default headers_list()    
);
return Response::make($content, 200);
```
4. Extends all model class with \Schalkt\Schache\Model
```
class BaseModel extends \Schalkt\Schache\Model
{
    ...
}
```

TODO
---
- Unit tests
- File cache for large HTML pages