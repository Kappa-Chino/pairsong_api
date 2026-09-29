<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    /**
     * ルートのポイント
     */
    public function routePoints(): HasMany
    {
        return $this->hasMany(RoutePoint::class, 'route_id');
    }

    /**
     * ルートのポイント (Legacy support or misnamed)
     */
    public function comments(): HasMany
    {
        return $this->hasMany(RoutePoint::class, 'route_id');
    }
    /**
     * ルートに関連する楽譜
     */
    public function music()
    {
        return $this->hasOne(Music::class, 'route_id');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['device_id', 'name'];
}
