<?php

namespace Gfernandez\LaravelRestApi\Repositories;

use Gfernandez\LaravelRestApi\Exceptions\ConstrainException;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

abstract class RepositoryService implements RepositoryInterface
{

    public $model;
    public $queryBuilder;

    /**
     * Constructor
     */
    public function __construct($model)
    {
        $this->model = $model;
        $this->queryBuilder = $model::query();
    }

    /**
     * @inheritdoc
     */
    public function findOne($id)
    {
        return $this->model->where('id', $id)->first();
    }

    public function modelCount()
    {
        return $this->model->count();
    }

    /**
     * @inheritdoc
     */
    public function findOneBy(array $criteria)
    {
        return $this->model->where($criteria)->first();
    }

    /**
     * apply filters and return a paginated list
     */
    public function findBy(array $searchCriteria = [])
    {
        $isList = !empty($searchCriteria['list']) && $searchCriteria['list'];

        if (isset($searchCriteria['order_by']) && !empty($searchCriteria['order_by']['field'])) {
            if(!empty($searchCriteria['order_by']['table'])){
                $this->queryBuilder->with([$searchCriteria['order_by']['table'] => function ($q) use($searchCriteria){
                    $q->orderBy($searchCriteria['order_by']['field'], $searchCriteria['order_by']['direction']);
                }]);
            }
            else{
                $this->queryBuilder->orderBy($searchCriteria['order_by']['field'], $searchCriteria['order_by']['direction']);
            }
        }

        if(!empty($searchCriteria['include'])){
            $find = $this->model->where($searchCriteria['include']['field'], $searchCriteria['include']['value']);
            $this->queryBuilder->union($find);
        }

        if(!empty($searchCriteria['exclude'])){
            $this->queryBuilder->where($searchCriteria['exclude']['field'], '!=', $searchCriteria['exclude']['value']);
        }

        $limit = !empty($searchCriteria['per_page']) ? (int) $searchCriteria['per_page'] : ($isList ? 300 : 20); // it's needed for pagination

        $this->queryBuilder->where(function ($query) use ($searchCriteria) {
            $this->applySearchCriteriaInQueryBuilder($query, $searchCriteria);
        });

        if($isList){
            return $this->queryBuilder->limit($limit)->get();
        }
        else{
            return $this->queryBuilder->paginate($limit);
        }
    }

    /**
     * Apply condition on query builder based on search criteria
     *
     * @param Object $queryBuilder
     * @param array $searchCriteria
     * @return mixed
     */
    protected function applySearchCriteriaInQueryBuilder($queryBuilder, array $searchCriteria = [])
    {
        foreach ($searchCriteria as $key => $value) {

            $doCriteria = $this->model->isFillable($key) && // only apply criteria if field is present in fillable array
                !in_array($key, ['page', 'total', 'per_page', 'order_by', 'with', 'query_type', 'where']) && //reserved keys
                (is_array($value) || trim($value) != ''); // if is empty, we don't need to filter

            if ($doCriteria) {
                //we can pass multiple params for a filter with commas
                if(is_array($value)){
                    $allValues = $value;
                }
                else{
                    $allValues = explode(',', $value);
                }

                if (count($allValues) > 1) {
                    $queryBuilder->whereIn($key, $allValues);
                } else {
                    $operator = isset($searchCriteria['query_type']) ? $searchCriteria['query_type'] : '=';
                    $join = explode('.', $key);
                    if (isset($join[1])) {

                        if (isset($searchCriteria['where']) && strtoupper($searchCriteria['where']) == 'AND') {
                            $queryBuilder->whereHas($join[0], function ($query) use ($join, $operator, $value) {
                                $query->where(Str::plural($join[0]) . '.' . $join[1], $operator, $value);
                            });
                        } else {
                            $queryBuilder->orWhereHas($join[0], function ($query) use ($join, $operator, $value) {
                                $query->where(Str::plural($join[0]) . '.' . $join[1], $operator, $value);
                            });
                        }
                    } else {
                        if (isset($searchCriteria['where']) && strtoupper($searchCriteria['where']) == 'OR') {
                            $queryBuilder->orWhere($key, $operator, $value);
                        } else {

                            $queryBuilder->where($key, $operator, $value);
                        }
                    }
                }
            }
        }

        return $queryBuilder;
    }

    /**
     * @inheritdoc
     */
    public function store(array $data)
    {
        //ignore id to store a new row
        unset($data['id']);

        //logic to disabled entities
        if(isset($data['is_enabled']) && !$data['is_enabled']){
            $data['disabled_at'] = now();
        }

        foreach ($data as $key => $value) {
            // WHEN ID IS 0 -> SET NULL ON DB
            if (strpos($key, '_id') !== FALSE && $value == 0) {
                $data[$key] = null;
            }
            if (!$this->model->isFillable($key)) {
                unset($data[$key]);
            }
        }
        $this->model = $this->model->create($data);
    }

    /**
     * @inheritdoc
     */
    public function update($model, array $data)
    {
        //logic to disabled entities
        if(isset($model->is_enabled) && $model->is_enabled && isset($data['is_enabled']) && !$data['is_enabled']){
            $data['disabled_at'] = now();
        }
        if (!is_null($model)) {
            $this->model = $model;
            foreach ($data as $key => $value) {
                // WHEN ID IS 0 > SET NULL ON DB
                if (strpos($key, '_id') !== FALSE && $value === 0) {
                    $value = null;
                }
                // update only fillAble properties
                if ($this->model->isFillable($key)) {
                    $this->model->{$key} = $value;
                }
            }
            // update the model
            $this->model->save();
        }
    }

    /**
     * @inheritdoc
     */
    public function delete($model)
    {
        $result = TRUE;
        // we don't need to delete NULL, right?
        if (is_null($model)) {
            $result = FALSE;
        }
        else{
            try {
                $result = $model->delete();
            } catch (QueryException $e) {
                if($e->errorInfo[1] !== 1451){
                    Log::error($e->errorInfo);
                }
                throw new ConstrainException('delete', $e->errorInfo[1]);
            }
        }
        return $result;
    }

    public function isDirty($model, array $data){
        if (!is_null($model)) {
            $this->model = $model;
            foreach ($data as $key => $value) {
                // WHEN ID IS 0 > SET NULL ON DB
                if (strpos($key, '_id') !== FALSE && $value == 0) {
                    $value = null;
                }
                // update only fillAble properties
                if ($this->model->isFillable($key)) {
                    $this->model->{$key} = $value;
                }
            }
        }
        return  $this->model->isDirty();
    }
    public function getDirty($model, array $data){
        if (!is_null($model)) {
            $this->model = $model;
            foreach ($data as $key => $value) {
                // WHEN ID IS 0 > SET NULL ON DB
                if (strpos($key, '_id') !== FALSE && $value == 0) {
                    $value = null;
                }
                // update only fillAble properties
                if ($this->model->isFillable($key)) {
                    $this->model->{$key} = $value;
                }
            }
        }
        return  $this->model->getDirty();
    }
}
