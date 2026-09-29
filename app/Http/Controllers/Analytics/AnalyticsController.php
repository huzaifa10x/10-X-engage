<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Sent / delivered / read / failed / received per day, per number and per template, from our own message data. */
class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaceId = $request->user()->workspace_id;
        $days = (int) in_array($request->query('range'), ['7', '30', '90']) ? (int) $request->query('range') : 30;
        $from = now()->subDays($days - 1)->startOfDay();

        $base = Message::where('workspace_id', $workspaceId)->where('created_at', '>=', $from);

        $daily = (clone $base)
            ->selectRaw("DATE(created_at) as day,
                SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN direction = 'outbound' AND status IN ('delivered','read') THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN direction = 'outbound' AND status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN direction = 'outbound' AND status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as received")
            ->groupBy('day')->orderBy('day')->get()->keyBy('day');

        $series = [];
        for ($d = $from->copy(); $d <= now(); $d->addDay()) {
            $key = $d->toDateString();
            $row = $daily[$key] ?? null;
            $series[] = ['day' => $key, 'sent' => (int) ($row->sent ?? 0), 'delivered' => (int) ($row->delivered ?? 0), 'read' => (int) ($row->read_count ?? 0), 'failed' => (int) ($row->failed ?? 0), 'received' => (int) ($row->received ?? 0)];
        }

        $totals = ['sent' => 0, 'delivered' => 0, 'read' => 0, 'failed' => 0, 'received' => 0];
        foreach ($series as $s) {
            foreach ($totals as $k => $v) {
                $totals[$k] += $s[$k];
            }
        }

        $perNumber = (clone $base)->where('direction', 'outbound')
            ->join('phone_numbers', 'phone_numbers.id', '=', 'messages.phone_number_id')
            ->selectRaw("phone_numbers.display_phone_number as number, COUNT(*) as sent,
                SUM(CASE WHEN messages.status IN ('delivered','read') THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN messages.status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN messages.status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->groupBy('phone_numbers.display_phone_number')->orderByDesc('sent')->get();

        $topTemplates = (clone $base)->where('direction', 'outbound')->whereNotNull('message_template_id')
            ->join('message_templates', 'message_templates.id', '=', 'messages.message_template_id')
            ->selectRaw("message_templates.name, COUNT(*) as sent,
                SUM(CASE WHEN messages.status IN ('delivered','read') THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN messages.status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN messages.status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->groupBy('message_templates.name')->orderByDesc('sent')->limit(10)->get();

        $topErrors = (clone $base)->where('direction', 'outbound')->where('status', 'failed')
            ->selectRaw('error_code, MAX(error_message) as message, COUNT(*) as n')->groupBy('error_code')->orderByDesc('n')->limit(8)->get();

        $contacts = DB::table('contacts')->where('workspace_id', $workspaceId)
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN opt_in_status = 'opted_in' THEN 1 ELSE 0 END) as opted_in,
                SUM(CASE WHEN opt_in_status = 'opted_out' THEN 1 ELSE 0 END) as opted_out,
                SUM(CASE WHEN window_expires_at > ? THEN 1 ELSE 0 END) as open_windows,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_contacts", [now(), $from])
            ->first();

        return Inertia::render('analytics/index', [
            'range' => $days,
            'series' => $series,
            'totals' => $totals + ['delivery_rate' => $totals['sent'] ? round($totals['delivered'] / $totals['sent'] * 100, 1) : 0, 'read_rate' => $totals['delivered'] ? round($totals['read'] / $totals['delivered'] * 100, 1) : 0],
            'per_number' => $perNumber,
            'top_templates' => $topTemplates,
            'top_errors' => $topErrors,
            'contacts' => $contacts,
        ]);
    }
}
