<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RegisterSessionController;
use App\Http\Controllers\SaleController;

Route::get('/health', function (Request $request) {
    return 'Health ... active';
});

// Rutas publicas
Route::post('auth/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::group(['middleware' => ['auth:api'], 'prefix' => '/auth'], function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/context', [AuthController::class, 'context']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/stations'], function () {
    Route::get('/', [StationController::class, 'index']);
    Route::get('status/{status}', [StationController::class, 'byStatus']);
    Route::get('place/{place}', [StationController::class, 'byPlace']);
    Route::get('{id}', [StationController::class, 'show']);
    Route::post('/', [StationController::class, 'store']);
    Route::put('{id}', [StationController::class, 'update']);
    Route::delete('{id}', [StationController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/companies'], function () {
    Route::get('/', [CompanyController::class, 'index']);
    Route::get('status/{status}', [CompanyController::class, 'byStatus']);
    Route::get('{id}', [CompanyController::class, 'show']);
    Route::post('/', [CompanyController::class, 'store']);
    Route::put('{id}', [CompanyController::class, 'update']);
    Route::delete('{id}', [CompanyController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/places'], function () {
    Route::get('/', [PlaceController::class, 'index']);
    Route::get('status/{status}', [PlaceController::class, 'byStatus']);
    Route::get('company/{company}', [PlaceController::class, 'byCompany']);
    Route::get('{id}', [PlaceController::class, 'show']);
    Route::post('/', [PlaceController::class, 'store']);
    Route::put('{id}', [PlaceController::class, 'update']);
    Route::delete('{id}', [PlaceController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/groups'], function () {
    Route::get('/', [GroupController::class, 'index']);
    Route::get('status/{status}', [GroupController::class, 'byStatus']);
    Route::get('{id}', [GroupController::class, 'show']);
    Route::post('/', [GroupController::class, 'store']);
    Route::put('{id}', [GroupController::class, 'update']);
    Route::delete('{id}', [GroupController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/permissions'], function () {
    Route::get('/', [PermissionController::class, 'index']);
    Route::get('status/{status}', [PermissionController::class, 'byStatus']);
    Route::get('{id}', [PermissionController::class, 'show']);
    Route::post('/', [PermissionController::class, 'store']);
    Route::put('{id}', [PermissionController::class, 'update']);
    Route::delete('{id}', [PermissionController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/roles'], function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('status/{status}', [RoleController::class, 'byStatus']);
    Route::get('{id}', [RoleController::class, 'show']);
    Route::post('/', [RoleController::class, 'store']);
    Route::put('{id}', [RoleController::class, 'update']);
    Route::delete('{id}', [RoleController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/users'], function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('status/{status}', [UserController::class, 'byStatus']);
    Route::get('place/{place}', [UserController::class, 'byPlace']);
    Route::get('company/{company}', [UserController::class, 'byCompany']);
    Route::get('role-name/{role}', [UserController::class, 'byRoleName']);
    Route::get('{id}', [UserController::class, 'show']);
    Route::post('/', [UserController::class, 'store']);
    Route::put('{id}', [UserController::class, 'update']);
    Route::delete('{id}', [UserController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], "prefix" => "/files"], function () {
    Route::post('/', [FileController::class, 'create']);
    Route::delete('{id}', [FileController::class, 'delete']);
    Route::post('/get', [FileController::class, 'get']);
    Route::put('{id}', [FileController::class, 'update']);
    Route::get('/download-image-from-url', [FileController::class, 'downloadImageFromUrl']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/products'], function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('place/{place}', [ProductController::class, 'byPlace']);
    Route::get('{id}', [ProductController::class, 'show']);
    Route::post('/', [ProductController::class, 'store']);
    Route::put('{id}', [ProductController::class, 'update']);
    Route::delete('{id}', [ProductController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/purchases'], function () {
    Route::get('place/{place}', [PurchaseController::class, 'byPlace']);
    Route::post('/', [PurchaseController::class, 'store']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/sales'], function () {
    Route::get('/catalog', [SaleController::class, 'catalog']);
    Route::get('place/{place}', [SaleController::class, 'byPlace']);
    Route::post('/', [SaleController::class, 'store']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/register-sessions'], function () {
    Route::get('/current', [RegisterSessionController::class, 'current']);
    Route::post('/open', [RegisterSessionController::class, 'open']);
    Route::post('/close', [RegisterSessionController::class, 'close']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/reports'], function () {
    Route::get('/', [ReportController::class, 'list']);
    Route::get('/{id}', [ReportController::class, 'execute']);
});
