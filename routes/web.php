<?php

use Illuminate\Support\Facades\Route;

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
Route::get('/', ['uses' => 'App\Http\Controllers\IndexController@index', 'as' => 'index']);
Route::post('/post-data', ['uses' => 'App\Http\Controllers\IndexController@post', 'as' => 'post']);
Route::post('/destroy', ['uses' => 'App\Http\Controllers\IndexController@destroy', 'as' => 'destroy']);

Route::get('/diagram', ['uses' => 'App\Http\Controllers\IndexController@diagram', 'as' => 'diagram']);
