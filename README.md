# redis-full-page-cache

This project under development, but this is a working version.

Install
---
` composer require schalkt/redis-full-page-cache `


Setup
---
1. Put these lines to the top of public/index.php 
```
define('APP_START', microtime(true));
require_once __DIR__.'/../vendor/autoload.php';
Schalkt\RedisFullPageCache\FPCache::load();
```
2. Put this filter to the filter.php (in Laravel 4.2)
```
Route::filter('cache', function ($route, $request, $response = null) {
    if (!is_null($response)) {
        FPCache::save($response->getContent(), $response->getStatusCode(), 3600 * 24);
    }
});
```
3. Add before and after filter in the routes.php (in Laravel 4.2)
```
Route::group(array('before' => 'cache', 'after' => 'cache'), function () {
    Route::get('/', "IndexController@indexAction");
    ...
});
```
4. Setup Redis config in FPCache.php file
```
'prefix' => 'fpc',
'debug'  => true,
'redis'  => array(
    'host' => '127.0.0.1',
    'port' => 6379,
)
```