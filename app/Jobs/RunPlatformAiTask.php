<?php

namespace App\Jobs;

use App\Services\PlatformAiOperations;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunPlatformAiTask implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 150;

    public function __construct(public int $taskId, public string $idempotencyKey) {}

    public function handle(PlatformAiOperations $operations): void
    {
        $operations->execute($this->taskId, $this->idempotencyKey);
    }
}
