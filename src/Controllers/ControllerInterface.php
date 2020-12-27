<?php

namespace Gfernandez\LaravelRestApi\Controllers;

use Illuminate\Http\Request;

interface ControllerInterface
{

    /**
     * Find a resource by id
     *
     * @param Request $request
     * @return Collection
     */
    public function index(Request $request);

    public function show($id);

    public function store(Request $request);

    public function update(Request $request, $id);

    public function destroy($id);
}
