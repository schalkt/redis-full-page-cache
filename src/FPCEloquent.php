<?php

namespace Schalkt\RedisFullPageCache;


class FPCEloquent extends \Illuminate\Database\Eloquent\Model
{

	public function scopeFpcPluck($query, $pluck = '')
	{

		$result = $this->scopeFpcFirst($query);

		return $result->pluck($pluck);

	}

	public function scopeFpcFirst($query, $columns = array('*'))
	{

		$result = $query->first($columns);
		FPCache::element($this->table, $result->getKey());

		return $result;

	}

	public function scopeFpcGet($query, $columns = array('*'))
	{

		$result = $query->get($columns);
		$count = count($result);

		if ($count > FPCache::elementLimit()) {
			FPCache::element($this->table);
		}

		foreach ($result as $item) {

			if ($count <= FPCache::elementLimit()) {
				FPCache::element($this->table, $item->getKey());
			}

			$relations = $item->getRelations();

			if (!empty($relations)) {

				foreach ($relations as $relation) {

					$count_item = count($relation);

					if ($count_item > FPCache::elementLimit()) {
						FPCache::element($relation[0]->table);
					} else {
						foreach ($relation as $relation_item) {
							FPCache::element($relation_item->table, $relation_item->getKey());
						}
					}
				}
			}

		}

		return $result;

	}

	public static function boot()
	{

		parent::boot();

		self::created(function ($model) {
			FPCache::deleteByModule($model->table);
		});

		self::deleted(function ($model) {
			FPCache::deleteByModule($model->table);
		});

		self::updated(function ($model) {
			FPCache::deleteByModule($model->table, $model->getKey());
		});

	}


}