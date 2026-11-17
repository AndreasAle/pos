<?php

namespace App\Jobs;

use App\Models\PlatformAiTelegramUpdate;
use App\Services\PlatformTelegramConversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPlatformTelegramUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [10, 60, 180];

    public function __construct(public int $updateId) {}

    public function handle(PlatformTelegramConversation $service): void
    {
        $update = PlatformAiTelegramUpdate::find($this->updateId);
        if ($update && in_array($update->status, ['received', 'failed'], true)) {
            $service->process($update);
        }
    }
}
