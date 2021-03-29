<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/set_name','App\Http\Controllers\Test\TestController@index');
Route::post('/get_name_list','App\Http\Controllers\Test\TestController@getNameList');
Route::post('/test','App\Http\Controllers\Test\TestController@test');
Route::get('/excel','App\Http\Controllers\Test\TestController@excel');
Route::get('/publish_message','App\Http\Controllers\Test\TestController@publish');
