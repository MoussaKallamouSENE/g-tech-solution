<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\AdminMaintenanceController;
use App\Http\Controllers\Api\MaintenanceRequestController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminCustomerController;
use App\Http\Controllers\Api\AdminZoneController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/orders/{id}/invoice', [OrderController::class, 'invoice']);
});

Route::middleware(['auth:sanctum', EnsureAdmin::class])->prefix('admin')->group(function () {
    Route::get('/stats', [AdminOrderController::class, 'stats']);
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);

    Route::get('/products', [AdminProductController::class, 'index']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::get('/products/{id}', [AdminProductController::class, 'show']);
    Route::put('/products/{id}', [AdminProductController::class, 'update']);
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);
    Route::get('/customers', [AdminCustomerController::class, 'index']);
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
    Route::get('/delivery-zones', [AdminZoneController::class, 'index']);
    Route::post('/delivery-zones', [AdminZoneController::class, 'store']);
    Route::put('/delivery-zones/{id}', [AdminZoneController::class, 'update']);
    Route::patch('/products/{id}', [AdminProductController::class, 'patch']);
    Route::post('/products/{id}/image', [AdminProductController::class, 'uploadImage']);
    Route::get('/maintenance-requests', [AdminMaintenanceController::class, 'index']);
    Route::patch('/maintenance-requests/{id}', [AdminMaintenanceController::class, 'update']);
    Route::delete('/products/{id}/image', [AdminProductController::class, 'deleteImage']);
});

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/brands', [BrandController::class, 'index']);
Route::get('/delivery-zones', [DeliveryZoneController::class, 'index']);
Route::post('/maintenance-requests', [MaintenanceRequestController::class, 'store'])->middleware('throttle:10,1');

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);