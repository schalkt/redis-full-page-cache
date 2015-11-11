# redis-full-page-cache

> The project is under development, but this is a working and stable version.


Install
---

Download from here:  [https://github.com/schalkt/redis-full-page-cache](https://github.com/schalkt/redis-full-page-cache)
> Composer reguire soon.

Setup in Laravel 4.2
---
1. Put these lines to the top of public/index.php 
```
define('APP_START', microtime(true)); // require for debug only
require_once __DIR__.'/../vendor/schalkt/redis-full-page-cache/src/FPCache.php'; // 10 times faster than composer autoload :)
Schalkt\RedisFullPageCache\FPCache::load(); 
```
2. Save the page content in the Controller after rendering the view
```
$content = View::make('index');
Schalkt\RedisFullPageCache\FPCache::save(array(
    'content' => (string)$content) // required
    'http_status' => 200, // not required, default 200
	'expire'      => 3600, // not required, default in FPCache::$config['expire']
	'url'         => 'www.domain.tld/shop' // not required, default $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], see FPCache::$config['url']
	'headers'     => null // not required, default headers_list()    
);
return Response::make($content, 200);
```
3. Extends all model class with FPCEloquent
```
use Schalkt\RedisFullPageCache\FPCEloquent;
class BaseModel extends FPCEloquent
{
    ...
}
```
4. Replace Eloquent methods to FpcFirst(), FpcGet() and FpcPluck('title')
```
Office::FpcGet();
Users::active()->FpcFirst();
Project::where('status', 2)->FpcPluck('title');
```
5. Setup config in FPCache.php file
```
'prefix' => 'fpc',
'debug'  => true,
'redis'  => array(
    'host' => '127.0.0.1',
    'port' => 6379,
)
```

TODO
---
- Unit tests
- File cache for large HTML pages