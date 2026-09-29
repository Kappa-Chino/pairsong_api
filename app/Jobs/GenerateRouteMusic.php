<?php

namespace App\Jobs;

use App\Models\Music;
use App\Services\MusicService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateRouteMusic implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $routeId;

    public function __construct(int $routeId)
    {
        $this->routeId = $routeId;
    }

    public function handle(MusicService $musicService): void
    {
        try {
            $musicService->generateMusic($this->routeId);

            Music::create([
                'route_id' => $this->routeId,
            ]);
        } catch (\Exception $e) {
            Log::error('音楽生成エラー', [
                'route_id' => $this->routeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
