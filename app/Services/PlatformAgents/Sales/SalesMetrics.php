<?php

namespace App\Services\PlatformAgents\Sales;

use App\Models\PlatformAiCommercialProposal;
use App\Models\PlatformAiProspect;
use App\Models\PlatformAiProspectEvent;

/** Metrik dari data yang benar-benar tercatat. Tanpa data historis karangan. */
final class SalesMetrics
{
    public function summary(): array
    {
        $stages = PlatformAiProspect::query()->selectRaw('sales_stage, COUNT(*) n')->groupBy('sales_stage')->pluck('n', 'sales_stage')->all();
        $total = array_sum($stages);
        $engaged = PlatformAiProspect::whereNotNull('last_reply_at')->count();
        $qualified = PlatformAiProspect::whereNotNull('qualified_at')->count();
        $trialEver = PlatformAiProspectEvent::where('type', 'transition')->where('to_stage', 'trial_active')->distinct('platform_ai_prospect_id')->count('platform_ai_prospect_id');
        $won = $stages['won'] ?? 0;
        $proposals = PlatformAiCommercialProposal::query()->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all();
        $objections = [];
        foreach (PlatformAiProspectEvent::where('type', 'objection')->latest('id')->limit(500)->pluck('data') as $d) {
            foreach ((array) ($d['objections'] ?? []) as $o) {
                $objections[$o['type']] = ($objections[$o['type']] ?? 0) + 1;
            }
        }
        arsort($objections);
        $quality = [];
        foreach (PlatformAiProspectEvent::where('type', 'quality')->latest('id')->limit(500)->pluck('data') as $d) {
            foreach ((array) ($d['issues'] ?? []) as $i) {
                $quality[$i] = ($quality[$i] ?? 0) + 1;
            }
        }
        $replies = PlatformAiProspectEvent::where('type', 'quality')->count();
        $pct = fn ($a, $b) => $b > 0 ? round($a / $b * 100, 1) : null;

        return [
            'total' => $total, 'by_stage' => $stages, 'engaged' => $engaged, 'qualified' => $qualified, 'trial' => $trialEver, 'won' => $won,
            'opted_out' => $stages['opted_out'] ?? 0, 'handoffs' => PlatformAiProspectEvent::where('type', 'transition')->where('to_stage', 'human_handoff')->count(),
            'proposals' => $proposals, 'policy_denials' => PlatformAiProspectEvent::where('type', 'policy_denial')->count(), 'objections' => array_slice($objections, 0, 6, true),
            'hardware_interest' => PlatformAiProspectEvent::where('type', 'hardware')->distinct('platform_ai_prospect_id')->count('platform_ai_prospect_id'),
            'quality' => ['replies_checked' => $replies, 'issues' => $quality],
            'rates' => ['reply' => $pct($engaged, PlatformAiProspect::where('contact_count', '>', 0)->count()), 'qualified' => $pct($qualified, $engaged),
                'trial_to_paid' => $pct($won, $trialEver), 'opt_out' => $pct($stages['opted_out'] ?? 0, $total)],
        ];
    }
}
