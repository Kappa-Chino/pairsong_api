<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class VideoGeneratorService
{
    /**
     * MP3と静止画からInstagram用MP4動画を生成する
     *
     * @param string $audioPath MP3ファイルの絶対パス
     * @param string $outputPath 出力先MP4ファイルの絶対パス
     * @param string|null $imagePath 静止画ファイルの絶対パス（省略時はデフォルト画像）
     * @param int $duration 動画の秒数（デフォルト: 90秒）
     * @return bool
     */
    public function generateFromMp3(string $audioPath, string $outputPath, ?string $imagePath = null, int $duration = 90): bool
    {
        // 画像ファイルのパスが未指定の場合はデフォルト画像 (/resources/img/park_spring.jpg)
        $imagePath = $imagePath ?: resource_path('img/park_spring.jpg');

        // 画像の存在確認
        if (!file_exists($imagePath)) {
            Log::error("Image file not found: {$imagePath}");
            return false;
        }

        // 音声の存在確認
        if (!file_exists($audioPath)) {
            Log::error("Audio file not found: {$audioPath}");
            return false;
        }

        // 出力先ディレクトリの作成（存在しない場合）
        $outputDir = dirname($outputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // FFmpeg コマンド構築（Instagram推奨・互換フォーマット）
        $command = [
            'ffmpeg',
            '-y',                              // 既存ファイルがあれば上書き
            '-loop', '1',                      // 静止画をループ再生
            '-r', '30',                        // フレームレートを30fpsに固定
            '-i', $imagePath,                  // 入力画像
            '-i', $audioPath,                  // 入力音声(MP3)
            '-t', (string) $duration,          // 動画の長さを指定（例: 90秒）
            '-vf', 'scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920', // Instagram Reels向け9:16
            '-c:v', 'libx264',                 // H.264動画コーデック
            '-tune', 'stillimage',             // 静止画向けエンコード最適化
            '-preset', 'fast',                 // エンコード速度調整
            '-c:a', 'aac',                     // AAC音声コーデック
            '-b:a', '192k',                    // 音声ビットレート
            '-ar', '48000',                    // Instagram向けサンプリングレート
            '-af', 'apad',                     // 音声が指定秒数より短い場合に無音パディング
            '-pix_fmt', 'yuv420p',             // Instagram互換用カラーフォーマット
            '-movflags', '+faststart',         // Web/Instagram再生向けにmoov atomを先頭に配置
            $outputPath
        ];

        // 実行（長尺エンコードを考慮してタイムアウトを300秒に設定）
        $result = Process::timeout(300)->run($command);

        if ($result->failed()) {
            Log::error("FFmpeg conversion failed: " . $result->errorOutput());
            return false;
        }

        return true;
    }
}
