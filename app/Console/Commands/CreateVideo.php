<?php

namespace App\Console\Commands;

use App\Models\Music;
use App\Models\Route;
use App\Services\RouteMapImageService;
use App\Services\VideoGeneratorService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateVideo extends Command
{
    /**
     * コマンド名（Artisanコマンド実行時に使用）
     *
     * @var string
     */
    protected $signature = 'app:create-video';

    /**
     * コマンドの概要説明
     *
     * @var string
     */
    protected $description = 'musicsテーブルの未生成動画(mp4)をffmpegで生成し、video_urlを登録する';

    /**
     * コマンド実行処理
     */
    public function handle(VideoGeneratorService $videoGenerator, RouteMapImageService $routeMapImageService): int
    {
        $this->info('動画生成処理を開始します...');

        // musicsテーブルの中でvideo_urlがnullのものをすべて取得
        $targetMusics = Music::whereNull('video_url')->get();

        if ($targetMusics->isEmpty()) {
            $this->info('生成対象の動画（video_urlがnullのレコード）はありませんでした。');
            return Command::SUCCESS;
        }

        $this->info("対象件数: {$targetMusics->count()} 件");

        $successCount = 0;
        $failCount = 0;

        foreach ($targetMusics as $music) {
            $this->line("----------------------------------------");
            $this->info("Music ID: {$music->id} (route_id: {$music->route_id}) の処理を開始");

            // 音声ファイルのパス
            $audioPath = storage_path("app/public/musics/{$music->route_id}.mp3");
            if (!file_exists($audioPath)) {
                $warnMessage = "音声ファイルが存在しません: {$audioPath}";
                $this->warn($warnMessage);
                Log::warning($warnMessage, ['music_id' => $music->id, 'route_id' => $music->route_id]);
                $failCount++;
                continue;
            }

            // 旅ルート詳細マップと同じGPS点から一時的な地図画像を生成する
            $route = Route::find($music->route_id);
            $routeMapPath = storage_path('app/tmp/route-map-' . $music->route_id . '-' . bin2hex(random_bytes(6)) . '.png');
            $useTemporaryMap = $route && $routeMapImageService->render($route, $routeMapPath);

            if ($useTemporaryMap) {
                $imagePath = $routeMapPath;
                $this->info('映像ソース: 旅ルートのGoogleマップ');
            } else {
                // キー未設定や地図取得失敗時は既存の季節画像にフォールバック
                $imagePath = $this->getImagePathForMusic($music);
                $this->warn('ルートマップを取得できないため、季節画像を使用します。');
            }

            try {
                if (!file_exists($imagePath)) {
                    $errorMessage = "画像ファイルが見つかりません: {$imagePath}";
                    $this->error($errorMessage);
                    Log::error($errorMessage, ['music_id' => $music->id, 'route_id' => $music->route_id]);
                    $failCount++;
                    continue;
                }

                // 出力先MP4ファイルのパス
                $outputPath = storage_path("app/public/videos/{$music->route_id}.mp4");

                $this->info("動画生成中 (90秒)...");

                // Instagram Reels向けの90秒・縦型動画を生成
                $generated = $videoGenerator->generateFromMp3($audioPath, $outputPath, $imagePath, 90);

                if (!$generated || !file_exists($outputPath)) {
                    $errorMessage = "動画生成に失敗しました: {$outputPath}";
                    $this->error($errorMessage);
                    Log::error($errorMessage, ['music_id' => $music->id, 'route_id' => $music->route_id]);
                    $failCount++;
                    continue;
                }

                // video_url の登録
                $videoUrl = Storage::disk('public')->url("videos/{$music->route_id}.mp4");
                $music->video_url = $videoUrl;
                $music->save();

                $this->info("動画生成完了: {$outputPath}");
                $this->info("video_urlを登録しました: {$videoUrl}");
                Log::info("動画生成及びvideo_url登録完了", [
                    'music_id' => $music->id,
                    'route_id' => $music->route_id,
                    'video_url' => $videoUrl,
                ]);

                $successCount++;
            } finally {
                if (file_exists($routeMapPath)) {
                    unlink($routeMapPath);
                }
            }
        }

        $this->line("----------------------------------------");
        $this->info("処理完了 - 成功: {$successCount} 件, スキップ/失敗: {$failCount} 件");

        return Command::SUCCESS;
    }

    /**
     * created_atの日時に基づいて使用する画像パスを決定する
     *
     * 3月～5月：park_spring.jpg
     * 6月：park_rain.jpg
     * 7月～9月：park_summer.jpg
     * 10月～11月：park_autumn.jpg
     * 12月～2月：park_winter.jpg
     * ※該当ファイルが存在しなければ park_spring.jpg
     */
    protected function getImagePathForMusic(Music $music): string
    {
        $createdAt = $music->created_at;
        $month = $createdAt instanceof CarbonInterface ? (int) $createdAt->format('n') : (int) now()->format('n');

        $imageName = match (true) {
            in_array($month, [3, 4, 5], true) => 'park_spring.jpg',
            $month === 6 => 'park_rain.jpg',
            in_array($month, [7, 8, 9], true) => 'park_summer.jpg',
            in_array($month, [10, 11], true) => 'park_autumn.jpg',
            in_array($month, [12, 1, 2], true) => 'park_winter.jpg',
            default => 'park_spring.jpg',
        };

        $imagePath = resource_path("img/{$imageName}");

        // 該当ファイルが存在しなければ park_spring.jpg
        if (!file_exists($imagePath)) {
            $imagePath = resource_path('img/park_spring.jpg');
        }

        return $imagePath;
    }
}
