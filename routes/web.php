<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('v1')->group(function () {
    Route::prefix('testtakers')->group(function () {
        Route::get('', 'Api\TestTakerApiController@fetchResourceCollection');
        Route::get('{login}', 'Api\TestTakerApiController@fetchResource');
    });
});
