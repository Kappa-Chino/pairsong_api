<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\SpotController;
use App\Http\Controllers\Admin\RouteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->group(function () {
    // 管理者画面トップ
    Route::get('/', [AuthController::class, 'index'])->name('admin.top');
    
    // スポット管理
    Route::prefix('spot')->name('spot.')->group(function () {
        Route::get('/', [SpotController::class, 'index'])->name('index');
        Route::get('/create', [SpotController::class, 'create'])->name('create');
        Route::post('/', [SpotController::class, 'store'])->name('store');
        Route::get('/mapview', [SpotController::class, 'mapview'])->name('mapview');
        Route::get('/{spot}/edit', [SpotController::class, 'edit'])->name('edit');
        Route::put('/{spot}', [SpotController::class, 'update'])->name('update');
        Route::delete('/{spot}', [SpotController::class, 'destroy'])->name('destroy');
    });

    // 旅ルート管理 // ================================ //
    Route::prefix('tripRoute')->name('tripRoute.')->group(function () {
        Route::get('/', [RouteController::class, 'index'])->name('index');
        Route::get('/{route}', [RouteController::class, 'show'])->name('show');
    });
});
