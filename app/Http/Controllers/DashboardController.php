<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $workspace = $request->user()->workspace;
        $accountIds = $workspace->whatsappAccounts()->pluck('id');

        return Inertia::render('dashboard', [
            'stats' => [
                'accounts' => $workspace->whatsappAccounts()->count(),
                'active_accounts' => $workspace->whatsappAccounts()->where('status', 'active')->count(),
                'phone_numbers' => $workspace->phoneNumbers()->count(),
                'registered_numbers' => $workspace->phoneNumbers()->where('is_registered', true)->count(),
                'templates' => \App\Models\MessageTemplate::whereIn('whatsapp_account_id', $accountIds)->count(),
                'approved_templates' => \App\Models\MessageTemplate::whereIn('whatsapp_account_id', $accountIds)->where('status', 'APPROVED')->count(),
                'messages_sent' => $workspace->messages()->where('direction', 'outbound')->count(),
                'messages_failed' => $workspace->messages()->where('status', 'failed')->count(),
                'messages_received' => $workspace->messages()->where('direction', 'inbound')->count(),
            ],
            'recent_messages' => $workspace->messages()->with('contact:id,wa_id,name')->latest()->limit(8)->get()->map(fn ($m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'type' => $m->type,
                'status' => $m->status,
                'to' => $m->to,
                'from' => $m->from,
                'preview' => $m->preview,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
            'accounts' => $workspace->whatsappAccounts()->withCount('phoneNumbers')->latest()->limit(5)->get()->map(fn ($a) => [
                'id' => $a->id,
                'waba_id' => $a->waba_id,
                'name' => $a->name,
                'status' => $a->status,
                'onboarding_step' => $a->onboarding_step,
                'phone_numbers_count' => $a->phone_numbers_count,
            ]),
        ]);
    }
}
