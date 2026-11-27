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
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RegisterSessionController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductFavoriteController;
use App\Http\Controllers\PosDashboardController;
use App\Http\Controllers\ElectronicInvoiceSettingController;
use App\Http\Controllers\PosResolutionSettingController;

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
    Route::get('{companyId}/electronic-invoice', [ElectronicInvoiceSettingController::class, 'show']);
    Route::put('{companyId}/electronic-invoice', [ElectronicInvoiceSettingController::class, 'upsert']);
    Route::post('{companyId}/electronic-invoice/test', [ElectronicInvoiceSettingController::class, 'test']);
    Route::get('{companyId}/electronic-invoice/numbering-ranges', [ElectronicInvoiceSettingController::class, 'numberingRanges']);
    Route::get('{companyId}/electronic-invoice/numbering-ranges/dian', [ElectronicInvoiceSettingController::class, 'dianNumberingRanges']);
    Route::post('{companyId}/electronic-invoice/numbering-ranges', [ElectronicInvoiceSettingController::class, 'createNumberingRange']);
    Route::patch('{companyId}/electronic-invoice/numbering-ranges/{rangeId}/current', [ElectronicInvoiceSettingController::class, 'updateNumberingRangeCurrent']);
    Route::patch('{companyId}/electronic-invoice/numbering-ranges/{rangeId}/toggle-status', [ElectronicInvoiceSettingController::class, 'toggleNumberingRangeStatus']);
    Route::get('{companyId}/pos-resolution', [PosResolutionSettingController::class, 'show']);
    Route::put('{companyId}/pos-resolution', [PosResolutionSettingController::class, 'upsert']);

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
    Route::get('{id}', [PurchaseController::class, 'show']);
    Route::post('/', [PurchaseController::class, 'store']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/suppliers'], function () {
    Route::get('place/{place}', [SupplierController::class, 'byPlace']);
    Route::get('{id}', [SupplierController::class, 'show']);
    Route::post('/', [SupplierController::class, 'store']);
    Route::put('{id}', [SupplierController::class, 'update']);
    Route::delete('{id}', [SupplierController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/customers'], function () {
    Route::get('/catalog', [CustomerController::class, 'catalog']);
    Route::get('place/{place}', [CustomerController::class, 'byPlace']);
    Route::get('{id}', [CustomerController::class, 'show']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::put('{id}', [CustomerController::class, 'update']);
    Route::delete('{id}', [CustomerController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/geo'], function () {
    Route::get('/departments', [GeoController::class, 'departments']);
    Route::get('/municipalities', [GeoController::class, 'municipalities']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/taxes'], function () {
    Route::get('/', [TaxController::class, 'index']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/product-categories'], function () {
    Route::get('place/{place}', [ProductCategoryController::class, 'byPlace']);
    Route::get('{id}', [ProductCategoryController::class, 'show']);
    Route::post('/', [ProductCategoryController::class, 'store']);
    Route::put('{id}', [ProductCategoryController::class, 'update']);
    Route::delete('{id}', [ProductCategoryController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/dining-tables'], function () {
    Route::get('place/{place}', [DiningTableController::class, 'byPlace']);
    Route::get('{id}', [DiningTableController::class, 'show']);
    Route::post('/', [DiningTableController::class, 'store']);
    Route::put('{id}', [DiningTableController::class, 'update']);
    Route::patch('{id}/status', [DiningTableController::class, 'updateStatus']);
    Route::delete('{id}', [DiningTableController::class, 'destroy']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/product-favorites'], function () {
    Route::get('place/{place}', [ProductFavoriteController::class, 'byPlace']);
    Route::post('/toggle', [ProductFavoriteController::class, 'toggle']);
    Route::put('/sync', [ProductFavoriteController::class, 'sync']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/pos-dashboard'], function () {
    Route::get('place/{place}', [PosDashboardController::class, 'byPlace']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/inventory'], function () {
    Route::get('place/{place}/lots', [InventoryController::class, 'lots']);
    Route::get('place/{place}/products', [InventoryController::class, 'products']);
    Route::get('place/{place}/summary', [InventoryController::class, 'summary']);
    Route::get('place/{place}/adjustments', [InventoryController::class, 'adjustments']);
    Route::post('/adjust', [InventoryController::class, 'adjust']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/sales'], function () {
    Route::get('/catalog', [SaleController::class, 'catalog']);
    Route::get('place/{place}', [SaleController::class, 'byPlace']);
    Route::get('{id}', [SaleController::class, 'show']);
    Route::post('/', [SaleController::class, 'store']);
    Route::post('{id}/cancel', [SaleController::class, 'cancel']);
    Route::post('{id}/retry-electronic-invoice', [SaleController::class, 'retryElectronicInvoice']);
    Route::post('{id}/retry-credit-note', [SaleController::class, 'retryCreditNote']);
    Route::post('{id}/returns', [SaleController::class, 'storeReturn']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => '/register-sessions'], function () {
    Route::get('/current', [RegisterSessionController::class, 'current']);
    Route::get('/summary', [RegisterSessionController::class, 'summary']);
    Route::get('place/{place}', [RegisterSessionController::class, 'byPlace']);
    Route::post('/open', [RegisterSessionController::class, 'open']);
    Route::post('/close', [RegisterSessionController::class, 'close']);
});
Route::group(['middleware' => ['auth:api'], 'prefix' => '/reports'], function () {
    Route::get('/', [ReportController::class, 'list']);
    Route::get('/{id}', [ReportController::class, 'execute']);
});
