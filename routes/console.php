<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 未生成のInstagram投稿用動画を生成する（1日1回 深夜3:00に実行）
Schedule::command('app:create-video')->dailyAt('03:00');
