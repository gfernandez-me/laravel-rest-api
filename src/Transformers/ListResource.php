<?php

namespace Gfernandez\LaravelRestApi\Transformers;

class ListResource extends TransformerService
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // $this->model has the name of the model. e.g. Role, User
        $resource = [
            'name'          => $this->name,
            'label'         => $this->description ?? $this->name ?? $this->id ?? '-',
            'value'         => $this->id,
            'is_default'    => $this->is_default ?? 0
        ];

        return $resource;
    }
}
