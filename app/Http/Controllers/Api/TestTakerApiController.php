<?php

namespace App\Http\Controllers\Api;

use App\TestTaker;
use App\TestTakerCsv;
use App\TestTakerJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\ModelResource;
use Illuminate\Http\Request as Request;
use App\Http\QueryBuilders\QueryBuilder;
use App\Http\Resources\TestTaker as TestTakerResource;
use App\Http\Resources\TestTakers as TestTakerResourcesCollection;

/**
 * Class TestTakerApiController
 *
 * @package App\Http\Controllers\API
 */
class TestTakerApiController extends Controller
{
    /**
     * Create a new TestTakerApiController instance.
     *
     * @param  TestTaker  $testTaker
     * @return void
     */
    public function __construct()
    {
        $sourceType = config('sources.data_source');

        switch ($sourceType) {
            case 'json':
                $this->model = app(TestTakerJson::class);
                break;
            case 'csv':
                $this->model = app(TestTakerCsv::class);
                break;
            default:
                $this->model = app(TestTaker::class);
        }
    }

    /**
     * Get a listing of all the test takers.
     *
     * @param  Request  $request
     * @return Response
     */
    public function fetchResourceCollection(Request $request)
    {
        return filter(new TestTakerResourcesCollection($this->model->all()));
    }

    /**
     * Get a single test taker.
     *
     * @param  string  $login
     * @param  Request  $request
     * @return Response
     */
    public function fetchResource($login, Request $request)
    {
        return new TestTakerResource($this->model->all()->where('login', $login)->first());
    }
}
