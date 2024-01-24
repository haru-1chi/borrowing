<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\userController;
use App\Http\Controllers\productController;
use App\Http\Controllers\borrowController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
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
Route::get('user/getList',[userController::class,'getList']);
Route::get('user/getDetail/{id}',[userController::class,'getDetail']);

Route::post('product/insert',[productController::class,'insert']);
Route::post('product/update/{id}',[productController::class,'update']);
Route::post('product/delete/{id}',[productController::class,'delete']);
Route::get('product/getList',[productController::class,'getList']);
Route::get('product/getDetail/{id}',[productController::class,'getDetail']);

Route::post('borrow/borrow',[borrowController::class,'borrow']);
Route::post('borrow/return/{id}',[borrowController::class,'return']);
Route::post('borrow/update/{id}',[borrowController::class,'update']);
Route::post('borrow/delete/{id}',[borrowController::class,'delete']);
Route::get('borrow/getList',[borrowController::class,'getList']);
Route::get('borrow/getDetail/{id}',[borrowController::class,'getDetail']);
Route::get('borrow/getHistory',[borrowController::class,'getHistory']);
Route::get('borrow/dashboard',[borrowController::class,'dashboard']);

Route::group([
    'prefix' => 'auth',
    'middleware' => 'api',
], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('resetPassword', [AuthController::class, 'resetPassword']);
    Route::post('password/forgot', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.forgot');;
    Route::post('password/reset', [ForgotPasswordController::class, 'reset'])->name('password.reset');;
    Route::get('me', [AuthController::class, 'me']);
});