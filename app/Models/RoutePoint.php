<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RoutePoint extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['route_id', 'date', 'latitude', 'longitude'];

    function getCreatedAtAttribute($value)
    {
        $carbon = new Carbon($value);
        return $carbon->format('Y-m-d H:i:s');
    }
}
