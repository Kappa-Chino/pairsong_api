<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $routes = Route::orderBy('created_at', 'desc')->paginate(10);
        return view('route.index', compact('routes'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $route = Route::with(['routePoints' => fn ($query) => $query->orderBy('date')])->findOrFail($id);
        $musicPath = "musics/{$route->id}.mp3";
        $musicUrl = Storage::disk('public')->exists($musicPath)
            ? asset("storage/musics/{$route->id}.mp3")
            : null;
        $visitedSpots = DB::table('visited_spots')
            ->join('spots', 'visited_spots.spot_id', '=', 'spots.id')
            ->where('visited_spots.route_id', $route->id)
            ->orderBy('visited_spots.enter_time')
            ->get([
                'visited_spots.spot_id',
                'spots.name',
                'spots.latitude',
                'spots.longitude',
            ])
            ->unique('spot_id')
            ->values();
        
        // Google Maps API Key
        $googleMapsApiKey = config('services.google_maps.key');
        // 既存コードではBladeに直書きされていたが、Controllerから渡すほうが良いかもしれない。
        // しかし、既存のIngredientControllerではBladeに直書きされていたので、それに合わせるか、
        // 今回はビューで見やすくするためにそのまま書くか。
        // 一旦ビューで書く形にする。

        return view('route.detail', compact('route', 'visitedSpots', 'googleMapsApiKey', 'musicUrl'));
    }
}
