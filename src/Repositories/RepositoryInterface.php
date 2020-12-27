<?php

namespace Gfernandez\LaravelRestApi\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    /**
     * Find a resource by id
     *
     * @param $id
     * @return Model|null
     */
    public function findOne($id);

    /**
     * Find a resource by criteria
     *
     * @param array $criteria
     * @return Model|null
     */
    public function findOneBy(array $criteria);

    /**
     * Search All resources by criteria
     *
     * @param array $searchCriteria
     * @return Collection
     */
    public function findBy(array $searchCriteria = []);

    /**
     * Save a resource
     *
     * @param array $data
     * @return void
     */
    public function store(array $data);

    /**
     * Update a resource
     *
     * @param Model $model
     * @param array $data
     * @return void
     */
    public function update($model, array $data);

    /**
     * Delete a resource
     *
     * @param Model $model
     * @return bool
     */
    public function delete($model);
}
