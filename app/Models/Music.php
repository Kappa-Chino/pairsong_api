<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *  音楽データに関するモデル。DB上に音楽データのファイル名は存在しないが、
 * /storage/app/public/musics/{route_id}.mp3とする。
 */
class Music extends Model
{
    protected $table = 'musics';

    protected $fillable = [
        'route_id',
        'ig_container_id',
        'ig_publish_id',
        'image_url',
        'video_url',
    ];
}
