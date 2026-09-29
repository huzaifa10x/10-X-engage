import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useMemo } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Analytics', href: '/analytics' }];

interface Day { day: string; sent: number; delivered: number; read: number; failed: number; received: number }
interface Row { number?: string; name?: string; sent: number; delivered: number; read_count: number; failed: number }

interface Props {
    range: number;
    series: Day[];
    totals: { sent: number; delivered: number; read: number; failed: number; received: number; delivery_rate: number; read_rate: number };
    per_number: Row[];
    top_templates: Row[];
    top_errors: { error_code: number | null; message: string | null; n: number }[];
    contacts: { total: number; opted_in: number; opted_out: number; open_windows: number; new_contacts: number };
}

export default function Analytics({ range, series, totals, per_number, top_templates, top_errors, contacts }: Props) {
    const max = useMemo(() => Math.max(1, ...series.map((d) => Math.max(d.sent, d.received))), [series]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Analytics" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Analytics"
                    description="Outbound delivery funnel, inbound volume, per-number and per-template performance."
                    actions={
                        <div className="flex gap-1 rounded-md border p-0.5">
                            {[7, 30, 90].map((r) => (
                                <Button key={r} size="sm" variant={range === r ? 'default' : 'ghost'} onClick={() => router.get(route('analytics.index'), { range: r }, { preserveState: true })}>
                                    {r}d
                                </Button>
                            ))}
                        </div>
                    }
                />

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
                    {[
                        { label: 'Sent', value: totals.sent },
                        { label: 'Delivered', value: totals.delivered, sub: `${totals.delivery_rate}%` },
                        { label: 'Read', value: totals.read, sub: `${totals.read_rate}% of delivered` },
                        { label: 'Failed', value: totals.failed, cls: totals.failed ? 'text-red-600' : '' },
                        { label: 'Received', value: totals.received },
                        { label: 'New contacts', value: contacts.new_contacts },
                        { label: 'Open windows', value: contacts.open_windows },
                    ].map((s) => (
                        <Card key={s.label}>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground uppercase">{s.label}</p>
                                <p className={cn('text-2xl font-semibold', s.cls)}>{s.value}</p>
                                {s.sub && <p className="text-xs text-muted-foreground">{s.sub}</p>}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Messages per day</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex h-48 items-end gap-[2px]">
                            {series.map((d) => (
                                <div key={d.day} className="group relative flex h-full flex-1 items-end justify-center gap-[1px]" title={`${d.day}: ${d.sent} sent (${d.delivered} delivered, ${d.read} read, ${d.failed} failed) · ${d.received} received`}>
                                    <div className="w-full rounded-t bg-brand" style={{ height: `${(d.sent / max) * 100}%` }} />
                                    <div className="w-full rounded-t bg-violet-400" style={{ height: `${(d.received / max) * 100}%` }} />
                                    <div className="pointer-events-none absolute -top-6 hidden rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] whitespace-nowrap text-white group-hover:block">
                                        {d.sent}↑ {d.received}↓
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="mt-2 flex justify-between text-[11px] text-muted-foreground">
                            <span>{series[0]?.day}</span>
                            <span className="flex gap-3">
                                <span className="inline-flex items-center gap-1"><i className="inline-block size-2 rounded-sm bg-brand" /> sent</span>
                                <span className="inline-flex items-center gap-1"><i className="inline-block size-2 rounded-sm bg-violet-400" /> received</span>
                            </span>
                            <span>{series[series.length - 1]?.day}</span>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Table title="By number" rows={per_number} label="number" />
                    <Table title="Top templates" rows={top_templates} label="name" />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Failure reasons</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {top_errors.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No failed messages in this period.</p>
                            ) : (
                                <ul className="divide-y text-sm">
                                    {top_errors.map((e) => (
                                        <li key={String(e.error_code)} className="flex items-start justify-between gap-3 py-2">
                                            <span>
                                                <span className="font-mono text-xs text-muted-foreground">#{e.error_code ?? '—'}</span> {e.message}
                                            </span>
                                            <span className="font-semibold">{e.n}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Contacts</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                {[
                                    ['Total', contacts.total],
                                    ['Opted in', contacts.opted_in],
                                    ['Opted out', contacts.opted_out],
                                    ['Open 24h windows', contacts.open_windows],
                                ].map(([k, v]) => (
                                    <div key={String(k)} className="rounded-md bg-muted/50 p-3">
                                        <dt className="text-xs text-muted-foreground">{k}</dt>
                                        <dd className="text-xl font-semibold">{v}</dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function Table({ title, rows, label }: { title: string; rows: Row[]; label: 'number' | 'name' }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <table className="w-full text-sm">
                    <thead className="text-left text-xs text-muted-foreground uppercase">
                        <tr>
                            <th className="py-1.5 pr-2">{label}</th>
                            <th className="py-1.5 pr-2 text-right">Sent</th>
                            <th className="py-1.5 pr-2 text-right">Delivered</th>
                            <th className="py-1.5 pr-2 text-right">Read</th>
                            <th className="py-1.5 text-right">Failed</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={5} className="py-6 text-center text-muted-foreground">No data yet.</td>
                            </tr>
                        )}
                        {rows.map((r) => (
                            <tr key={r[label]}>
                                <td className="py-1.5 pr-2 font-medium">{r[label]}</td>
                                <td className="py-1.5 pr-2 text-right">{r.sent}</td>
                                <td className="py-1.5 pr-2 text-right">{r.delivered} <span className="text-xs text-muted-foreground">{r.sent ? Math.round((Number(r.delivered) / Number(r.sent)) * 100) : 0}%</span></td>
                                <td className="py-1.5 pr-2 text-right">{r.read_count}</td>
                                <td className="py-1.5 text-right text-red-600">{Number(r.failed) || ''}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}
