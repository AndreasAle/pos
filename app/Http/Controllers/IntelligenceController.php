<?php

namespace App\Http\Controllers;

use App\Models\AssistantConversation;
use App\Models\BusinessInsight;
use App\Models\IntelligenceActionProposal;
use App\Models\IntelligenceAgent;
use App\Services\InsightService;
use App\Services\Intelligence\ActionService;
use App\Services\Intelligence\AgentService;
use App\Services\YokaAssistantService;
use Illuminate\Http\Request;

class IntelligenceController extends Controller
{
    public function index(Request $request, InsightService $insights, AgentService $agents)
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
        $intelligenceAgents = collect();
        if (config('pos.features.intelligence_agents')) {
            $agents->ensureDefaults($user->business);
            $intelligenceAgents = IntelligenceAgent::where('business_id', $user->business_id)->orderBy('agent_key')->get();
        }

        return view('intelligence.index', compact('conversations', 'activeConversation', 'businessInsights', 'intelligenceAgents'));
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

    public function acknowledge(Request $request, BusinessInsight $insight)
    {
        abort_unless($insight->business_id === $request->user()->business_id, 403);
        $insight->update(['acknowledged_at' => now(), 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function snooze(Request $request, BusinessInsight $insight)
    {
        abort_unless($insight->business_id === $request->user()->business_id, 403);
        $data = $request->validate(['hours' => 'nullable|integer|min:1|max:168']);
        $insight->update(['snoozed_until' => now()->addHours($data['hours'] ?? 24)]);

        return response()->json(['success' => true]);
    }

    public function approveAction(Request $request, IntelligenceActionProposal $proposal, ActionService $actions)
    {
        $data = $request->validate(['confirmation' => 'required|string|max:20']);
        $proposal = $actions->approve($request->user(), $proposal, $data['confirmation']);

        return response()->json(['success' => true, 'proposal' => $proposal]);
    }

    public function executeAction(Request $request, IntelligenceActionProposal $proposal, ActionService $actions)
    {
        $proposal = $actions->execute($request->user(), $proposal);

        return response()->json(['success' => true, 'proposal' => $proposal]);
    }

    public function toggleAgent(Request $request, IntelligenceAgent $agent)
    {
        abort_unless(config('pos.features.intelligence_agents') && $agent->business_id === $request->user()->business_id, 403);
        $agent->update(['enabled' => ! $agent->enabled]);

        return response()->json(['success' => true, 'enabled' => $agent->enabled]);
    }
}
