<?php

use App\Http\Controllers\Api\SpotController;
use App\Http\Controllers\Api\RouteController as ApiRouteController;
use Illuminate\Support\Facades\Route;

// ルート一覧取得 DBに登録されているルートの一覧を取得する
Route::get('/route/{device_id}', [ApiRouteController::class, 'getRoutes'])->whereNumber('device_id');

// 最新ルート詳細取得 指定日の中で最後に登録されたルート詳細を取得する
Route::get('/route/{device_id}/latest', [ApiRouteController::class, 'getLatestRouteDetail'])
	->whereNumber(['device_id']);

// ルート詳細取得 1件分のルート詳細を取得する
Route::get('/route/{device_id}/{route_id}', [ApiRouteController::class, 'getRouteDetail'])->whereNumber(['device_id', 'route_id']);

// ルート登録 GPSで取得した位置情報をまとめて登録する
Route::post('/route/{device_id}/', [ApiRouteController::class, 'store'])->whereNumber('device_id');

// スポット一覧取得
Route::get('/spot', [SpotController::class, 'index']);