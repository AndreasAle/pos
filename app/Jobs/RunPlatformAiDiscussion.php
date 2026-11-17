<?php

namespace App\Jobs;

use App\Models\PlatformAdmin;
use App\Models\PlatformAiDiscussion;
use App\Services\PlatformAiDiscussions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunPlatformAiDiscussion implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $discussionId, public int $adminId, public array $agentIds) {}

    public function handle(PlatformAiDiscussions $service): void
    {
        $discussion = PlatformAiDiscussion::find($this->discussionId);
        $admin = PlatformAdmin::find($this->adminId);
        if ($discussion && $admin && $discussion->status === 'pending') {
            $service->run($discussion, $admin, $this->agentIds);
        }
    }
}
