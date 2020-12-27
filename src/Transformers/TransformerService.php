<?php

namespace Gfernandez\LaravelRestApi\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class TransformerService extends JsonResource
{
    protected $modelName;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->modelName = (new \ReflectionClass($resource))->getShortName();
    }
}
