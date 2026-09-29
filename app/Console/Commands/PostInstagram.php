<?php
/**
 * インスタグラム投稿コマンド
 */

namespace App\Console\Commands;

use App\Models\Music;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostInstagram extends Command
{
    /**
     * コマンド名。artisanコマンドとして実行する際に使用。
     *
     * @var string
     */
    protected $signature = 'app:post-instagram';

    /**
     * コマンドの説明。artisan listで表示される。
     *
     * @var string
     */
    protected $description = '生成された動画(mp4)をInstagramリールとして投稿・公開する';

    /**
     * 実行内容
     */
    public function handle(): int
    {
        $this->info('Instagram投稿処理を開始します...');

        // 1. 前回以前の未公開コンテナ（処理待ち／中断されたもの）をチェック・公開
        $this->processPendingPublish();

        // 2. まだコンテナ作成されていないものをアップロード
        $this->processUploads();

        // 3. 今回新規にアップロードされたコンテナを待機・公開
        $this->processPendingPublish();

        $this->line("----------------------------------------");
        $this->info('Instagram投稿処理が完了しました。');

        return Command::SUCCESS;
    }

    /**
     * 未アップロード（コンテナ未作成）の動画をアップロード
     */
    protected function processUploads(): void
    {
        $postItemList = Music::whereNotNull('video_url')
            ->whereNull('ig_container_id')
            ->whereNull('ig_publish_id')
            ->get();

        $this->info("未アップロード対象: {$postItemList->count()} 件");

        foreach ($postItemList as $item) {
            $this->upload($item);
        }
    }

    /**
     * アップロード済み（コンテナ作成済み）で未公開のものを確認して公開
     */
    protected function processPendingPublish(): void
    {
        $publishItemList = Music::whereNotNull('ig_container_id')
            ->whereNull('ig_publish_id')
            ->get();

        if ($publishItemList->isEmpty()) {
            return;
        }

        $this->info("公開待ち対象: {$publishItemList->count()} 件");

        foreach ($publishItemList as $item) {
            $this->line("----------------------------------------");
            $this->info("Music ID: {$item->id} (Container ID: {$item->ig_container_id}) の状態確認開始");

            $finished = false;
            $maxAttempts = 20; // 30秒間隔 × 最大20回 (最大600秒待機)

            for ($i = 1; $i <= $maxAttempts; $i++) {
                $status = $this->checkStatus($item);

                if ($status === 'FINISHED') {
                    $this->info("コンテナの動画処理が完了しました (ステータス: FINISHED)");
                    $finished = true;
                    break;
                }

                if ($status === 'ERROR' || $status === 'EXPIRED') {
                    $this->error("コンテナ処理が失敗しました (ステータス: {$status})。コンテナIDをリセットし、再アップロード対象に戻します。");
                    Log::warning("Instagramコンテナ処理失敗のためリセット", [
                        'music_id' => $item->id,
                        'failed_container_id' => $item->ig_container_id,
                        'status' => $status,
                    ]);
                    // コンテナIDをNULLに戻して保存。次回または後続のアップロード対象にする
                    $item->ig_container_id = null;
                    $item->save();
                    break;
                }

                $this->line("処理中... ({$i}/{$maxAttempts}) ステータス: {$status}。30秒待機します。");
                sleep(30);
            }

            if ($finished) {
                $this->publish($item);
            } else {
                $this->warn("Music ID: {$item->id} は処理が完了しなかったため、今回の公開をスキップしました。");
            }
        }
    }

    /**
     * Instagramアップロード（メディアコンテナ作成）処理
     */
    public function upload(Music $item): void
    {
        $this->line("----------------------------------------");
        $this->info("Music ID: {$item->id} (route_id: {$item->route_id}) の動画コンテナ作成を開始");
        Log::info('Instagram動画コンテナ作成開始', ['music_id' => $item->id, 'video_url' => $item->video_url]);

        $baseUrl = rtrim(config('app.instagram.graph_api_url'), '/');
        $userId = config('app.instagram.user_id');
        $accessToken = config('app.instagram.access_token');

        if (!$userId || !$accessToken) {
            $msg = 'Instagram API設定（user_id または access_token）が設定されていません。';
            $this->error($msg);
            Log::error($msg);
            return;
        }

        $url = "{$baseUrl}/{$userId}/media";

        $caption = 'ふたりの道が、ひとつの音になる。

鯖江の街を歩いて、
西山公園で見つけた景色。
ふたりで過ごした時間。

そんな旅の一日から、
ふたりだけの音楽が生まれました。

西山公園で、ふたりの時間を音楽に。

#ふたり音 #鯖江 #西山公園 #福井 #ふたり旅 #旅と音楽 #旅の思い出
';

        $this->info("API URL: {$url}");
        $this->info("Video URL: {$item->video_url}");

        // Instagramから動画URLにアクセス可能か事前に疎通確認
        try {
            $check = Http::withOptions(['verify' => false])->timeout(10)->head($item->video_url);
            if ($check->failed()) {
                $msg = "動画URLにアクセスできません (HTTP {$check->status()}): {$item->video_url}";
                $this->warn($msg);
                Log::warning($msg, ['music_id' => $item->id]);
                return;
            }
        } catch (\Exception $e) {
            $this->warn("動画URLの疎通確認中にエラーが発生しました: {$e->getMessage()}");
        }

        try {
            $result = Http::withOptions(['verify' => false])
                ->timeout(60)
                ->post($url, [
                    'access_token' => $accessToken,
                    'media_type' => 'REELS',
                    'video_url' => $item->video_url,
                    'caption' => $caption,
                ]);

            $responseData = $result->json();
            Log::info('Instagramメディアコンテナ作成レスポンス', ['response' => $responseData]);

            if (isset($responseData['id'])) {
                $item->ig_container_id = $responseData['id'];
                $item->save();
                $this->info("コンテナID取得成功: {$item->ig_container_id}");
            } else {
                $errorMessage = $responseData['error']['message'] ?? json_encode($responseData);
                $this->error("コンテナID取得失敗: {$errorMessage}");
                Log::error("Instagramコンテナ作成失敗: {$errorMessage}", ['response' => $responseData]);
            }
        } catch (\Exception $e) {
            $this->error("APIリクエスト中に例外が発生しました: {$e->getMessage()}");
            Log::error("Instagram API例外: {$e->getMessage()}");
        }
    }

    /**
     * アップロードしたコンテナの状態確認
     *
     * @return string|false ステータスコード (FINISHED, IN_PROGRESS, ERROR 等) または false
     */
    public function checkStatus(Music $item)
    {
        $baseUrl = rtrim(config('app.instagram.graph_api_url'), '/');
        $url = "{$baseUrl}/{$item->ig_container_id}";
        $accessToken = config('app.instagram.access_token');

        try {
            $result = Http::withOptions(['verify' => false])
                ->timeout(30)
                ->get($url, [
                    'access_token' => $accessToken,
                    'fields' => 'status_code,status',
                ]);

            $responseData = $result->json();
            Log::info('Instagramコンテナステータス確認レスポンス', ['music_id' => $item->id, 'response' => $responseData]);

            if (isset($responseData['status_code'])) {
                return $responseData['status_code'];
            }

            Log::warning('ステータスコードが取得できませんでした', ['response' => $responseData]);
            return false;
        } catch (\Exception $e) {
            Log::error("ステータス確認例外: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Instagramメディア公開処理
     */
    public function publish(Music $item): void
    {
        $baseUrl = rtrim(config('app.instagram.graph_api_url'), '/');
        $userId = config('app.instagram.user_id');
        $accessToken = config('app.instagram.access_token');
        $url = "{$baseUrl}/{$userId}/media_publish";

        $this->info("Instagramへの公開リクエスト送信中 (Container ID: {$item->ig_container_id})...");
        Log::info('Instagramメディア公開リクエスト開始', ['music_id' => $item->id, 'container_id' => $item->ig_container_id]);

        try {
            $result = Http::withOptions(['verify' => false])
                ->timeout(60)
                ->post($url, [
                    'access_token' => $accessToken,
                    'creation_id' => $item->ig_container_id,
                ]);

            $responseData = $result->json();
            Log::info('Instagramメディア公開レスポンス', ['music_id' => $item->id, 'response' => $responseData]);

            if (isset($responseData['id'])) {
                $item->ig_publish_id = $responseData['id'];
                $item->save();
                $this->info("公開完了！ Publish ID: {$item->ig_publish_id}");
            } else {
                $errorMessage = $responseData['error']['message'] ?? json_encode($responseData);
                $this->error("公開に失敗しました: {$errorMessage}");
                Log::error("Instagram公開失敗: {$errorMessage}", ['response' => $responseData]);
            }
        } catch (\Exception $e) {
            $this->error("公開リクエスト中に例外が発生しました: {$e->getMessage()}");
            Log::error("Instagram公開例外: {$e->getMessage()}");
        }
    }
}