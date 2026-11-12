<?php

namespace App\Jobs;

use App\Models\IntelligenceAgent;
use App\Services\Intelligence\AgentService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunIntelligenceAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $agentId, public string $scheduledFor) {}

    public function handle(AgentService $service): void
    {
        $agent = IntelligenceAgent::findOrFail($this->agentId);
        $service->run($agent, CarbonImmutable::parse($this->scheduledFor, $agent->timezone));
    }
}
