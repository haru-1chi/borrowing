<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\userController;
use App\Http\Controllers\productController;
use App\Http\Controllers\borrowController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('user/insert',[userController::class,'insert']);
Route::post('user/update/{id}',[userController::class,'update']);
Route::post('user/delete/{id}',[userController::class,'delete']);
Route::post('user/getList',[userController::class,'getList']);
Route::post('user/getDetail/{id}',[userController::class,'getDetail']);

Route::post('product/insert',[productController::class,'insert']);
Route::post('product/update/{id}',[productController::class,'update']);
Route::post('product/delete/{id}',[productController::class,'delete']);
Route::post('product/getList',[productController::class,'getList']);
Route::post('product/getDetail/{id}',[productController::class,'getDetail']);

Route::post('borrow/borrow',[borrowController::class,'borrow']);
Route::post('borrow/return/{id}',[borrowController::class,'return']);
Route::post('borrow/update/{id}',[borrowController::class,'update']);
Route::post('borrow/delete/{id}',[borrowController::class,'delete']);
Route::post('borrow/getList',[borrowController::class,'getList']);
Route::post('borrow/getDetail/{id}',[borrowController::class,'getDetail']);
Route::post('borrow/getHistory',[borrowController::class,'getHistory']);
Route::post('borrow/dashboard',[borrowController::class,'dashboard']);