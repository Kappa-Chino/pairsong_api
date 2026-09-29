<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Spot;
use Illuminate\Http\Request;

class SpotController extends Controller
{
    public function index()
    {
        $spots = Spot::orderBy('id')->paginate(10);

        return view('spot.index', compact('spots'));
    }

    public function create()
    {
        return view('spot.form');
    }

    public function store(Request $request)
    {
        Spot::create($this->validatedSpot($request));

        return redirect()->route('spot.index')->with('success', 'スポットを登録しました。');
    }

    public function edit(Spot $spot)
    {
        return view('spot.form', compact('spot'));
    }

    public function update(Request $request, Spot $spot)
    {
        $spot->update($this->validatedSpot($request));

        return redirect()->route('spot.index')->with('success', 'スポットを更新しました。');
    }

    public function destroy(Spot $spot)
    {
        $spot->delete();

        return redirect()->route('spot.index')->with('success', 'スポットを削除しました。');
    }

    public function mapview()
    {
        $spots = Spot::orderBy('id')->get();
        $googleMapsApiKey = config('services.google_maps.key');

        return view('spot.mapview', compact('spots', 'googleMapsApiKey'));
    }

    private function validatedSpot(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
    }
}
