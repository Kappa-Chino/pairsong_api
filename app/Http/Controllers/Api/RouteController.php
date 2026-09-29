<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateRouteMusic;
use App\Models\Route;
use App\Models\RoutePoint;
use Carbon\Carbon;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    /**
     * ルート一覧取得
     * DBに登録されているルートの一覧を取得する
     */
    public function getRoutes(int $device_id)
    {
        $routes = Route::where('device_id', $device_id)->orderBy('created_at', 'asc')->get();
        $res = $routes->map(function ($route) {
            return [
                'id' => $route->id,
                'date' => $route->updated_at
            ];
        });
        return response($res, 200);
    }

    /**
     * ルート詳細取得
     * 1件分のルート詳細を取得する
     */
    public function getRouteDetail(int $device_id, int $route_id)
    {
        $route_points = RoutePoint::where('route_id', $route_id)->get();
        $route = Route::where('id', $route_id)->first();
        $visited_spots = DB::table('visited_spots')
            ->where('route_id', $route_id)
            ->orderBy('enter_time', 'asc')
            ->get();
        // $res['id'] = $route->id;
        // $res['name'] = $route->name;
        // $res['routes'] = $route_points->map(function($route_point) {
        //     return [
        //         'date' => $route_point->created_at,
        //         'lat' => $route_point->latitude,
        //         'lng' => $route_point->longitude,
        //     ];
        // });
        $res['visited_spots'] = $visited_spots;
        return response($res, 200);
    }

/**
 * 最新ルート詳細取得
 * 日付は指定しない
 */
public function getLatestRouteDetail(int $device_id, Request $request)
{
    $route = Route::where('device_id', $device_id)
        ->orderBy('created_at', 'desc')
        ->first();

    if (!$route) {
        return response()->json([
            'status' => 404,
            'message' => 'Not Found'
        ], 404);
    }

    $route_points_query = RoutePoint::where('route_id', $route->id);
    $route_points = $route_points_query
        ->orderBy('date', 'asc')
        ->get();

    if ($route_points->isEmpty()) {
        return response()->json([
            'status' => 404,
            'message' => 'Not Found'
        ], 404);
    }

    // 訪問スポット取得
    $visited_spots = DB::table('visited_spots')
        ->join('spots', 'visited_spots.spot_id', '=', 'spots.id')
        ->where('visited_spots.route_id', $route->id)
        ->orderBy('visited_spots.enter_time', 'asc')
        ->get([
            'visited_spots.id',
            'visited_spots.spot_id',
            'spots.name',
            'visited_spots.enter_time',
            'visited_spots.leave_time',
        ]);

    return response()->json([
        'id' => $route->id,
        'name' => $route->name,

        'routes' => $route_points->map(function ($route_point) {
            return [
                'date' => $route_point->date,
                'lat' => $route_point->latitude,
                'lng' => $route_point->longitude,
            ];
        }),

        'visited_spots' => $visited_spots->map(function ($visited_spot) {
            return [
                'id' => $visited_spot->id,
                'spot_id' => $visited_spot->spot_id,
                'name' => $visited_spot->name,
                'lat' => DB::table('spots')->where('id', $visited_spot->spot_id)->value('latitude'),
                'lng' => DB::table('spots')->where('id', $visited_spot->spot_id)->value('longitude'),
                'enter_time' => $visited_spot->enter_time,
                'leave_time' => $visited_spot->leave_time,
            ];
        }),
    ], 200);
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, int $device_id)
    {
        $req = $request->json()->all();

        // 鯖江：住所は常に「福井県鯖江市」なので逆ジオコーディング不要
        // 他の県でもやるなら復活
        $prefecture = '福井県';
        $city = '鯖江市';

        // // 逆ジオコーディングAPIのエンドポイント
        // $url = 'https://maps.googleapis.com/maps/api/geocode/json';
        // // リクエストの実行
        // $response = Http::get($url, [
        //     'latlng' => "{$req[0]['lat']},{$req[0]['lng']}", // 緯度と経度をコンマで結合
        //     'key' => $apiKey,
        //     'language' => 'ja', // 日本語の結果を要求
        // ]);
        // $data = $response->json();
        // // return response()->json($data); // デバッグ用にAPI結果全体を返す

        // if ($data['status'] !== 'OK') {
        //     return response()->json(['error' => 'Geocoding API error.', 'status' => $data['status']], 500);
        // }

        // $prefecture = '';
        // $city = '';
        // // 最初の結果（最も正確な住所）を使用
        // $addressComponents = $data['results'][0]['address_components'] ?? [];
        // foreach ($addressComponents as $component) {
        //     $types = $component['types'];
        //     $longName = $component['long_name'];
        //     // 1. 都道府県名 (administrative_area_level_1) の取得
        //     if (in_array('administrative_area_level_1', $types)) {
        //         $prefecture = $longName;
        //     }
        //     // 2. 市区町村名 (locality または sublocality) の取得
        //     // locality: 市町村、sublocality: より細かい地域名
        //     if (in_array('locality', $types)) {
        //         // 既に locality が取得されていなければ、ここで設定
        //         if (empty($city)) {
        //            $city = $longName;
        //         }
        //     }

        // }
        // return response()->json([
        //     'status' => 'success',
        //     'prefecture' => $prefecture,
        //     'city' => $city,
        //     'full_address' => $data['results'][0]['formatted_address'] ?? '住所不明'
        // ]);

        // return $prefecture.$city;

        $route = Route::create([
            'device_id' => $device_id,
            'name' => $prefecture . $city,
        ]);

        $insert = [];

        foreach ($req as $point) {
            $dateTime = Carbon::createFromTimestamp(
                $point['time'],
                'Asia/Tokyo'
            );

            $insert[] = [
                'route_id' => $route->id,
                'date' => $dateTime,
                'latitude' => $point['lat'],
                'longitude' => $point['lng'],
            ];
        }

        if (!empty($insert)) {
            RoutePoint::insert($insert);
        }

        // スポットから50m以内に連続して10分以上いた区間を訪問として登録する
        // スポットから50m以内に連続して10分以上いた区間を訪問として登録する
        if (!empty($insert)) {
            $spots = DB::table('spots')->get();
            $visited_spots = [];
            $sorted_points = collect($insert)->sortBy('date')->values();

            foreach ($spots as $spot) {
                $enter_time = null;
                $last_inside_time = null;

                foreach ($sorted_points as $point) {
                    $distance = $this->haversineGreatCircleDistance(
                        $point['latitude'],
                        $point['longitude'],
                        $spot->latitude,
                        $spot->longitude
                    );

                    $is_inside = $distance <= 50;

                    if ($is_inside) {
                        $enter_time ??= $point['date'];
                        $last_inside_time = $point['date'];
                        continue;
                    }

                    if (
                        $enter_time !== null &&
                        $enter_time->diffInSeconds($last_inside_time) >= 600
                    ) {
                        $visited_spots[] = [
                            'route_id' => $route->id,
                            'spot_id' => $spot->id,
                            'enter_time' => $enter_time,
                            'leave_time' => $last_inside_time,
                        ];
                    }

                    $enter_time = null;
                    $last_inside_time = null;
                }

                // ルートの最後までスポット内だった場合
                if (
                    $enter_time !== null &&
                    $enter_time->diffInSeconds($last_inside_time) >= 600
                ) {
                    $visited_spots[] = [
                        'route_id' => $route->id,
                        'spot_id' => $spot->id,
                        'enter_time' => $enter_time,
                        'leave_time' => $last_inside_time,
                    ];
                }
            }

            if (!empty($visited_spots)) {
                DB::table('visited_spots')->insert($visited_spots);
            }
        }

        // 訪問データの登録後、非同期でルートの音楽を生成する（キューワーカーが処理する）
        GenerateRouteMusic::dispatch($route->id);

        return response([
            'status' => 'success',
            'route_id' => $route->id,
            'music_url' => 'https://event.jec.ac.jp/pairsong_api/public/storage/musics/' . $route->id . '.mp3', // 音楽のURLを返す。生成されたらアクセスできるようになる
        ], 200);
    }


    private function haversineGreatCircleDistance(
        $latitudeFrom,
        $longitudeFrom,
        $latitudeTo,
        $longitudeTo,
        $earthRadius = 6371000
    ) {
        // 緯度経度をラジアンに変換
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }
}
