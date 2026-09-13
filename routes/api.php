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
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\TurnController;
use App\Http\Controllers\TurnPriorityPreloadController;
use App\Http\Controllers\DisplayAlertController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RegisterSessionController;
use App\Http\Controllers\SaleController;

Route::get('/health', function (Request $request) {
    return 'Health ... active';
});

Route::get('/public/turns/track/{qrToken}', [TurnController::class, 'trackPublic']);
Route::post('/public/turns/track/{qrToken}/rate', [TurnController::class, 'ratePublic']);

// Rutas publicas
Route::post('auth/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::group(['middleware' => ['auth:api'], 'prefix' => '/auth'], function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/context', [AuthController::class, 'context']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/services'], function () {
    Route::get('/', [ServiceController::class, 'index']);
    Route::get('status/{status}', [ServiceController::class, 'byStatus']);
    Route::get('place/{place}', [ServiceController::class, 'byPlace']);
    Route::get('{id}', [ServiceController::class, 'show']);
    Route::post('/', [ServiceController::class, 'store']);
    Route::put('{id}', [ServiceController::class, 'update']);
    Route::delete('{id}', [ServiceController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/stations'], function () {
    Route::get('/', [StationController::class, 'index']);
    Route::get('status/{status}', [StationController::class, 'byStatus']);
    Route::get('place/{place}', [StationController::class, 'byPlace']);
    Route::put('{id}/services', [StationController::class, 'syncServices']);
    Route::get('{id}', [StationController::class, 'show']);
    Route::post('/', [StationController::class, 'store']);
    Route::put('{id}', [StationController::class, 'update']);
    Route::delete('{id}', [StationController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/turns'], function () {
    Route::get('/queue', [TurnController::class, 'queue']);
    Route::get('/current', [TurnController::class, 'current']);
    Route::get('/display', [TurnController::class, 'display']);
    Route::get('/no-shows', [TurnController::class, 'noShowsToday']);
    Route::get('/tts', [TurnController::class, 'tts']);
    Route::get('/history', [TurnController::class, 'history']);
    Route::get('/today', [TurnController::class, 'today']);
    Route::get('/patient-lookup', [TurnController::class, 'lookupPatient']);
    Route::post('/call-next', [TurnController::class, 'callNext']);
    Route::post('/call-by-number', [TurnController::class, 'callByNumber']);
    Route::post('/', [TurnController::class, 'store']);
    Route::post('{id}/recall', [TurnController::class, 'recall']);
    Route::post('{id}/start', [TurnController::class, 'start']);
    Route::post('{id}/complete', [TurnController::class, 'complete']);
    Route::post('{id}/no-show', [TurnController::class, 'noShow']);
    Route::post('{id}/cancel', [TurnController::class, 'cancel']);
    Route::get('{id}', [TurnController::class, 'show']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/turn-priority-preloads'], function () {
    Route::get('/', [TurnPriorityPreloadController::class, 'index']);
    Route::get('/lookup', [TurnPriorityPreloadController::class, 'lookup']);
    Route::post('/bulk', [TurnPriorityPreloadController::class, 'bulk']);
    Route::post('/', [TurnPriorityPreloadController::class, 'store']);
    Route::put('{id}', [TurnPriorityPreloadController::class, 'update']);
    Route::delete('{id}', [TurnPriorityPreloadController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/display-alerts'], function () {
    Route::get('/', [DisplayAlertController::class, 'index']);
    Route::get('/targets', [DisplayAlertController::class, 'targets']);
    Route::post('/', [DisplayAlertController::class, 'store']);
    Route::post('{id}/play', [DisplayAlertController::class, 'play']);
    Route::put('{id}', [DisplayAlertController::class, 'update']);
    Route::delete('{id}', [DisplayAlertController::class, 'destroy']);
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
