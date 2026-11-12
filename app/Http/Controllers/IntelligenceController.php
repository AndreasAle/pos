<?php

namespace App\Http\Controllers;

use App\Models\AssistantConversation;
use App\Models\BusinessInsight;
use App\Services\InsightService;
use App\Services\YokaAssistantService;
use Illuminate\Http\Request;

class IntelligenceController extends Controller
{
    public function index(Request $request, InsightService $insights)
    {
        abort_unless(config('pos.features.intelligence'), 404);
        $user = $request->user();
        $conversations = AssistantConversation::where('business_id', $user->business_id)
            ->where('user_id', $user->id)->withCount('messages')->latest('updated_at')->limit(40)->get();
        $activeConversation = null;
        if ($request->filled('conversation')) {
            $activeConversation = AssistantConversation::where('business_id', $user->business_id)
                ->where('user_id', $user->id)->with('messages')->findOrFail($request->integer('conversation'));
        }
        $businessInsights = $insights->visible($user->business, 5);

        return view('intelligence.index', compact('conversations', 'activeConversation', 'businessInsights'));
    }

    public function ask(Request $request, YokaAssistantService $assistant)
    {
        abort_unless(config('pos.features.intelligence'), 404);
        $data = $request->validate(['question' => 'required|string|max:500', 'conversation_id' => 'nullable|integer']);

        return response()->json(['success' => true] + $assistant->ask($request->user(), $data['question'], $data['conversation_id'] ?? null));
    }

    public function history(Request $request)
    {
        $conversation = AssistantConversation::where('business_id', $request->user()->business_id)
            ->where('user_id', $request->user()->id)->with(['messages' => fn ($q) => $q->orderByDesc('id')->limit(30)])
            ->latest('updated_at')->first();

        return response()->json(['conversation_id' => $conversation?->id, 'messages' => $conversation?->messages->reverse()->values() ?? []]);
    }

    public function destroyConversation(Request $request, AssistantConversation $conversation)
    {
        abort_unless($conversation->business_id === $request->user()->business_id && $conversation->user_id === $request->user()->id, 403);
        $conversation->delete();

        return response()->json(['success' => true]);
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
