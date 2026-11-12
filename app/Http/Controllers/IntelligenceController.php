<?php

namespace App\Http\Controllers;

use App\Models\AssistantConversation;
use App\Models\BusinessInsight;
use App\Services\InsightService;
use App\Services\YokaAssistantService;
use Illuminate\Http\Request;

class IntelligenceController extends Controller
{
    public function ask(Request $request, YokaAssistantService $assistant)
    {
        $data = $request->validate(['question' => 'required|string|max:500', 'conversation_id' => 'nullable|integer']);
        return response()->json(['success' => true] + $assistant->ask($request->user(), $data['question'], $data['conversation_id'] ?? null));
    }

    public function history(Request $request)
    {
        $conversation = AssistantConversation::where('business_id', $request->user()->business_id)
            ->where('user_id', $request->user()->id)->with(['messages' => fn ($q) => $q->latest()->limit(30)])
            ->latest('updated_at')->first();
        return response()->json(['conversation_id' => $conversation?->id, 'messages' => $conversation?->messages->reverse()->values() ?? []]);
    }

    public function refresh(Request $request, InsightService $insights)
    {
        $count = $insights->refresh($request->user()->business);
        return back()->with('success', "Yoka memperbarui {$count} insight bisnis.");
    }

    public function read(Request $request, BusinessInsight $insight)
    {
        abort_unless($insight->business_id === $request->user()->business_id, 403);
        $insight->update(['read_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function dismiss(Request $request, BusinessInsight $insight)
    {
        abort_unless($insight->business_id === $request->user()->business_id, 403);
        $insight->update(['dismissed_at' => now()]);
        return response()->json(['success' => true]);
    }
}
