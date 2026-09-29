<?php

namespace App\Services;

use App\Models\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MusicService
{
    /**
     * ComfyUI上でACE-Step 1.5のワークフローを実行し、ルートの旅の雰囲気に合わせた音楽を生成する。
     *
     * @param int $routeId
     * @return string 生成された音楽ファイルの相対URL（public/storage経由）
     */
    public function generateMusic(int $routeId): string
    {
        $route = Route::findOrFail($routeId);

        $visitedSpots = $this->getVisitedSpots($route->id);

        $acsParams = $this->generateAceStepParams($visitedSpots);

        $outputDir = storage_path('app/public/musics');
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $outputPath = "{$outputDir}/{$route->id}.mp3";

        $this->runComfyUiWorkflow($acsParams, $route->id, $outputPath);

        Log::info('音楽生成成功', ['route_id' => $route->id, 'path' => $outputPath]);

        return "storage/musics/{$route->id}.mp3";
    }

    /**
     * ルートで訪れたスポットとその滞在時間を取得する
     */
    private function getVisitedSpots(int $routeId)
    {
        return DB::table('visited_spots')
            ->join('spots', 'visited_spots.spot_id', '=', 'spots.id')
            ->where('visited_spots.route_id', $routeId)
            ->orderBy('visited_spots.enter_time')
            ->select('spots.*', 'visited_spots.enter_time', 'visited_spots.leave_time')
            ->get();
    }

    /**
     * Gemini APIを使ってACE-Step 1.5用の生成パラメータを作成する
     */
    private function generateAceStepParams($visitedSpots): array
    {
        $defaults = [
            'tags' => 'cinematic instrumental travel soundtrack, warm and gentle atmosphere, piano, acoustic guitar, light percussion, no vocals, no singing',
            'lyrics' => '',
            'bpm' => 100,
            'duration' => 90,
            'timesignature' => '4',
            'keyscale' => 'C major',
            'language' => 'en',
        ];

        $apiKey = config('services.gemini.api_key');
        $baseUrl = config('services.gemini.base_url');
        $model = config('services.gemini.model');

        $prompt = "以下は恋人との特別な旅で訪れた場所とその滞在時間帯（enter_time, leave_time）のデータです。\n"
            . "これらの日時から訪問当時の季節・天気・気温・時間帯（朝/昼/夕方/夜）の雰囲気を推測してください。\n"
            . "その上で、AIによる音楽生成モデル「ACE-Step 1.5」に渡す生成パラメータを、以下のJSON形式のみで出力してください。\n"
            . "説明文やマークダウンのコードブロック記号は一切不要です。\n\n"
            . "【出力JSON形式】\n"
            . "{\n"
            . "  \"tags\": \"カンマ区切りの英語タグ。曲の雰囲気・使用楽器・場面転換・旅の情景などを具体的に表現する。歌詞なしのインストゥルメンタル前提（no vocals, no singingを含める）\",\n"
            . "  \"bpm\": 60から160程度の数値,\n"
            . "  \"duration\": 60から120程度の秒数,\n"
            . "  \"timesignature\": \"拍子。例: 4\",\n"
            . "  \"keyscale\": \"調。例: C major\",\n"
            . "  \"language\": \"en\",\n"
            . "  \"lyrics\": \"\"\n"
            . "}\n\n"
            . "【訪問データ】\n"
            . json_encode($visitedSpots, JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
                ->withOptions([
                    'verify' => false,
                    'timeout' => 120,
                ])
                ->post("{$baseUrl}/models/{$model}:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 2048,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('Gemini APIリクエストエラー', ['status' => $response->status(), 'body' => $response->body()]);
                return $defaults;
            }

            $responseData = $response->json();
            $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$text) {
                Log::warning('Geminiからパラメータが生成されませんでした。デフォルト値を使用します。');
                return $defaults;
            }

            $json = $this->cleanJsonResponse($text);
            $params = json_decode($json, true);

            if (!is_array($params)) {
                Log::warning('Geminiのレスポンスが不正なJSONです。デフォルト値を使用します。', ['text' => $text]);
                return $defaults;
            }

            Log::info('ACE-Stepパラメータ生成成功', ['params' => $params]);

            return array_merge($defaults, array_filter($params, fn($value) => $value !== null && $value !== ''));
        } catch (\Exception $e) {
            Log::error('ACE-Stepパラメータ生成中に例外が発生しました', ['message' => $e->getMessage()]);
            return $defaults;
        }
    }

    /**
     * Geminiのレスポンスからマークダウンのコードブロック記号を除去する
     */
    private function cleanJsonResponse(string $text): string
    {
        $text = preg_replace('/^```(?:json)?\s*\n/m', '', $text);
        $text = preg_replace('/\n?\s*```\s*$/m', '', $text);

        return trim($text);
    }

    /**
     * ComfyUI上でACE-Step 1.5のワークフローを実行し、生成された音楽を保存する
     */
    private function runComfyUiWorkflow(array $params, int $routeId, string $outputPath): void
    {
        $workflow = json_decode(file_get_contents(resource_path('comfyui/ace_step_workflow.json')), true);

        // 音楽生成のシード値をランダムに設定（ノード109: Int(Seed) はノード3(KSampler)とノード94の共通シード元）
        $seed = random_int(0, 999999999);
        $workflow['109']['inputs']['value'] = $seed;

        $workflow['94']['inputs']['tags'] = $params['tags'];
        $workflow['94']['inputs']['lyrics'] = $params['lyrics'];
        $workflow['94']['inputs']['bpm'] = (int) $params['bpm'];
        $workflow['94']['inputs']['duration'] = (int) $params['duration'];
        $workflow['94']['inputs']['timesignature'] = (string) $params['timesignature'];
        $workflow['94']['inputs']['keyscale'] = $params['keyscale'];
        $workflow['94']['inputs']['language'] = $params['language'];
        $workflow['98']['inputs']['seconds'] = (int) $params['duration'];
        $workflow['109']['inputs']['value'] = random_int(0, 999999999);
        $workflow['111']['inputs']['filename_prefix'] = "musics/{$routeId}";

        $baseUrl = rtrim(config('services.comfyui.base_url'), '/');
        $clientId = (string) Str::uuid();

        Log::info('ComfyUIへ音楽生成リクエストを送信', ['route_id' => $routeId, 'params' => $params]);

        $response = Http::withOptions(['verify' => false, 'timeout' => 30])
            ->post("{$baseUrl}/prompt", [
                'prompt' => $workflow,
                'client_id' => $clientId,
            ]);

        if ($response->failed()) {
            Log::error('ComfyUIへのリクエストに失敗しました', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('ComfyUIへのリクエストに失敗しました: ' . $response->body());
        }

        $promptId = $response->json('prompt_id');
        if (!$promptId) {
            throw new \Exception('ComfyUIのprompt_idを取得できませんでした');
        }

        $historyEntry = $this->waitForCompletion($baseUrl, $promptId);
        $audioInfo = $this->extractAudioInfo($historyEntry, $promptId);
        $this->downloadAudio($baseUrl, $audioInfo, $outputPath);
    }

    /**
     * ComfyUIの生成完了をポーリングで待つ
     */
    private function waitForCompletion(string $baseUrl, string $promptId, int $maxAttempts = 120, int $intervalSeconds = 5): array
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = Http::withOptions(['verify' => false])->get("{$baseUrl}/history/{$promptId}");

            if ($response->ok()) {
                $data = $response->json();
                if (!empty($data[$promptId]['outputs'])) {
                    return $data[$promptId];
                }
            }

            sleep($intervalSeconds);
        }

        throw new \Exception('ComfyUIでの音楽生成がタイムアウトしました');
    }

    /**
     * ComfyUIの実行結果から生成された音声ファイルの情報を取り出す
     */
    private function extractAudioInfo(array $historyEntry, string $promptId): array
    {
        foreach ($historyEntry['outputs'] ?? [] as $nodeOutput) {
            if (!empty($nodeOutput['audio'])) {
                return $nodeOutput['audio'][0];
            }
        }

        throw new \Exception("ComfyUIの出力から音声ファイルが見つかりませんでした（prompt_id: {$promptId}）");
    }

    /**
     * ComfyUIから生成された音声ファイルをダウンロードして保存する
     */
    private function downloadAudio(string $baseUrl, array $audioInfo, string $outputPath): void
    {
        $response = Http::withOptions(['verify' => false, 'timeout' => 60])
            ->get("{$baseUrl}/view", [
                'filename' => $audioInfo['filename'],
                'subfolder' => $audioInfo['subfolder'] ?? '',
                'type' => $audioInfo['type'] ?? 'output',
            ]);

        if ($response->failed()) {
            throw new \Exception('生成された音楽ファイルのダウンロードに失敗しました');
        }

        file_put_contents($outputPath, $response->body());
    }
}

