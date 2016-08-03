<?php

namespace Schalkt\Schache;

/**
 * Class Builder
 *
 * @package Schalkt\Schache
 */
class Builder extends \Illuminate\Database\Eloquent\Builder
{

	/**
	 * Execute the query as a "select" statement.
	 *
	 * @param  array $columns
	 *
	 * @return \Illuminate\Database\Eloquent\Collection|static[]
	 */
	public function get($columns = array('*'))
	{
		$models = $this->getModels($columns);

		// If we actually found models we will also eager load any relationships that
		// have been specified as needing to be eager loaded, which will solve the
		// n+1 query issue for the developers to avoid running a lot of queries.
		if (count($models) > 0) {
			$models = $this->eagerLoadRelations($models);
		}

		$result = $this->model->newCollection($models);

		$this->fpCache($result);

		return $result;
	}


	/**
	 * Store table and id for cache
	 *
	 * @param $result
	 *
	 * @return mixed
	 */
	protected function fpCache($result)
	{

		$count = count($result);

		if ($count > FPCache::elementLimit()) {
			FPCache::element($this->model->getTable());
		}

		foreach ($result as $item) {

			if ($count <= FPCache::elementLimit()) {
				FPCache::element($this->model->getTable(), $item->getKey());
			}

			$relations = $item->getRelations();

			if (!empty($relations)) {
				$this->fpCacheRelations($relations);
			}

		}

		return $result;

	}

    /**
     * Store relations for cache
     *
     * @param $relations
     * @internal param $result
     */
	protected function fpCacheRelations($relations)
	{

		foreach ($relations as $items) {

			if (empty($items)) {
				continue;
			}

			$count_item = count($items);

			if ($count_item > FPCache::elementLimit()) {

			    if (!empty($items[0])) {
                    FPCache::element($items[0]->getTable());
                }

			}

			if ($items instanceof \Illuminate\Database\Eloquent\Collection) {

				foreach ($items as $item) {

					if ($count_item <= FPCache::elementLimit()) {
						FPCache::element($item->getTable(), $item->getKey());
					}

					$recursiveRelations = $item->getRelations();

					// recursive call if not empty relations
					if (!empty($recursiveRelations)) {
						$this->fpCacheRelations($recursiveRelations);
					}

				}

			} else {

				FPCache::element($items->getTable(), $items->getKey());

			}

		}

	}


}