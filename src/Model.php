<?php

namespace Schalkt\Schache;


/**
 * Class Model
 *
 * @package Schalkt\Schache
 */
abstract class Model extends \Illuminate\Database\Eloquent\Model
{

	/**
	 * Return new Eloquent Builder
	 *
	 * @param \Illuminate\Database\Query\Builder $query
	 *
	 * @return Builder
	 */
	public function newEloquentBuilder($query)
	{

		return new \Schalkt\Schache\Builder($query);

	}


	/**
	 * Delete cached items by events
	 */
	public static function boot()
	{

		parent::boot();

		self::created(function ($model) {
			FPCache::deleteByModule($model->getTable());
		});

		self::deleted(function ($model) {
			FPCache::deleteByModule($model->getTable());
			FPCache::deleteByModule($model->getTable(), $model->getKey());
		});

		self::updated(function ($model) {
			FPCache::deleteByModule($model->getTable(), $model->getKey());
		});

	}

    public static function getTableName()
    {
        return with(new static)->getTable();
    }


}