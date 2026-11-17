<?php

namespace App\Services;

use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiDiscussion;

class PlatformAiDiscussions
{
    public function run(PlatformAiDiscussion $discussion, PlatformAdmin $admin, array $agentIds): PlatformAiDiscussion
    {
        $agents = PlatformAiAgent::whereIn('id', array_unique($agentIds))->where('status', 'active')->limit(4)->get();
        if ($agents->count() < 2) {
            $discussion->update(['status' => 'failed', 'error' => 'Pilih minimal dua agen aktif.']);

            return $discussion;
        }
        $discussion->update(['status' => 'running']);
        foreach ($agents as $agent) {
            $result = app(PlatformAiOperations::class)->reply(
                $agent,
                "Diskusi tim. Berikan pandangan independen sesuai bidangmu untuk tujuan: {$discussion->objective}. Jangan membuat task dan jangan mengklaim sudah berkonsultasi dengan agen lain.",
                [], [], $admin, 'discussion'
            );
            $discussion->contributions()->create([
                'platform_ai_agent_id' => $agent->id, 'round' => 1, 'content' => $result['content'],
                'context' => ['mode' => $result['mode']],
            ]);
        }
        $parts = $discussion->contributions()->with('agent:id,name')->get()
            ->map(fn ($item) => $item->agent->name.":\n".$item->content)->join("\n\n");
        $discussion->update(['status' => 'completed', 'synthesis' => "Pandangan tim yang benar-benar dijalankan:\n\n".$parts]);

        return $discussion->fresh('contributions.agent');
    }
}
