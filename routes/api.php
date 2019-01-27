<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::prefix('testtakers')->group(function () {
            Route::get('', 'Api\TestTakerApiController@fetchResourceCollection');
            Route::get('{test_taker}', 'Api\TestTakerApiController@fetchResource');
        });
    });
// });

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
