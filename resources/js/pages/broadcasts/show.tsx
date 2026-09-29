import BroadcastStatus from '@/components/broadcast-status';
import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import WhatsAppPreview from '@/components/whatsapp-preview';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { http } from '@/lib/http';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { type BroadcastRow } from '@/types/whatsapp';
import { Head, Link, router } from '@inertiajs/react';
import { Ban, Pencil, Play, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Recipient {
    id: number;
    contact: { id: number; name: string | null; phone: string } | null;
    status: string;
    skip_reason: string | null;
    error: string | null;
    updated_at: string | null;
}

interface Props {
    broadcast: BroadcastRow & { preview: { header: string | null; body: string; footer: string | null; buttons: { type: string; text: string | null }[] } | null; error: string | null };
    recipients: Paginated<Recipient>;
    filters: { status?: string };
}

export default function BroadcastShow({ broadcast: initial, recipients, filters }: Props) {
    const [b, setB] = useState(initial);
    useEffect(() => setB(initial), [initial]);

    const running = ['queued', 'sending'].includes(b.status);
    useEffect(() => {
        if (!running) return;
        const id = window.setInterval(async () => {
            const fresh = await http<BroadcastRow>(route('broadcasts.progress', b.id)).catch(() => null);
            if (fresh) setB((prev) => ({ ...prev, ...fresh }));
            if (fresh && !['queued', 'sending'].includes(fresh.status)) router.reload({ only: ['recipients', 'broadcast'] });
        }, 3000);
        return () => window.clearInterval(id);
    }, [running, b.id]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Broadcasts', href: '/broadcasts' },
        { title: b.name, href: '#' },
    ];
    const c = b.counts;
    const done = c.sent + c.failed + c.skipped;
    const pct = c.total ? Math.round((done / c.total) * 100) : 0;
    const rate = (n: number, d: number) => (d ? `${Math.round((n / d) * 100)}%` : '—');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={b.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={b.name}
                    description={`${b.sender ?? ''} · ${b.template?.name ?? 'no template'} · ${b.segment ?? 'All contacts'}${b.scheduled_at && b.status === 'scheduled' ? ` · scheduled ${formatDate(b.scheduled_at)}` : ''}`}
                    actions={
                        <>
                            <BroadcastStatus status={b.status} className="text-xs" />
                            {['draft', 'scheduled'].includes(b.status) && (
                                <>
                                    <Button asChild variant="outline">
                                        <Link href={route('broadcasts.edit', b.id)}>
                                            <Pencil /> Edit
                                        </Link>
                                    </Button>
                                    <Button onClick={() => confirm('Send this broadcast now?') && router.post(route('broadcasts.launch', b.id))}>
                                        <Play /> Send now
                                    </Button>
                                </>
                            )}
                            {['scheduled', 'queued', 'sending'].includes(b.status) && (
                                <Button variant="destructive" onClick={() => confirm('Cancel? Messages already handed to Meta will still be delivered.') && router.post(route('broadcasts.cancel', b.id))}>
                                    <Ban /> Cancel
                                </Button>
                            )}
                            {['draft', 'scheduled', 'cancelled'].includes(b.status) && (
                                <Button variant="ghost" onClick={() => confirm('Delete this broadcast?') && router.delete(route('broadcasts.destroy', b.id))}>
                                    <Trash2 className="text-red-500" />
                                </Button>
                            )}
                        </>
                    }
                />
                <FlashMessages />
                {b.error && <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{b.error}</div>}

                <div className="grid gap-3 md:grid-cols-6">
                    {[
                        { label: 'Audience', value: c.total },
                        { label: 'Sent', value: c.sent, sub: rate(c.sent, c.total) },
                        { label: 'Delivered', value: c.delivered, sub: rate(c.delivered, c.sent) },
                        { label: 'Read', value: c.read, sub: rate(c.read, c.delivered) },
                        { label: 'Failed', value: c.failed, cls: c.failed ? 'text-red-600' : '' },
                        { label: 'Skipped', value: c.skipped, sub: 'consent / cancelled' },
                    ].map((s) => (
                        <Card key={s.label}>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground uppercase">{s.label}</p>
                                <p className={`text-2xl font-semibold ${s.cls ?? ''}`}>{s.value}</p>
                                {s.sub && <p className="text-xs text-muted-foreground">{s.sub}</p>}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {(running || b.status === 'completed') && (
                    <div>
                        <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                            <span>{running ? `Sending… ${c.queued} queued` : 'Completed'}</span>
                            <span>{pct}%</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div className="h-full bg-brand transition-all" style={{ width: `${pct}%` }} />
                        </div>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center justify-between text-base">
                                Recipients
                                <Select value={filters.status ?? 'all'} onValueChange={(v) => router.get(route('broadcasts.show', b.id), { status: v === 'all' ? undefined : v }, { preserveState: true, replace: true, only: ['recipients', 'filters'] })}>
                                    <SelectTrigger className="h-8 w-36 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {['all', 'queued', 'sent', 'delivered', 'read', 'failed', 'skipped'].map((s) => (
                                            <SelectItem key={s} value={s}>
                                                {s}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="py-2 pr-3">Contact</th>
                                        <th className="py-2 pr-3">Status</th>
                                        <th className="py-2 pr-3">Detail</th>
                                        <th className="py-2">Updated</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {recipients.data.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="py-8 text-center text-muted-foreground">
                                                {b.status === 'draft' ? 'The audience is snapshotted when the broadcast starts.' : 'No recipients.'}
                                            </td>
                                        </tr>
                                    )}
                                    {recipients.data.map((r) => (
                                        <tr key={r.id}>
                                            <td className="py-2 pr-3">
                                                {r.contact ? (
                                                    <Link href={route('inbox.show', r.contact.id)} className="hover:underline">
                                                        {r.contact.name ?? r.contact.phone} <span className="text-xs text-muted-foreground">{r.contact.name ? r.contact.phone : ''}</span>
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="py-2 pr-3 capitalize">{r.status}</td>
                                            <td className="py-2 pr-3 text-xs text-muted-foreground">{r.error ?? r.skip_reason?.replace(/_/g, ' ') ?? ''}</td>
                                            <td className="py-2 text-xs text-muted-foreground">{formatDate(r.updated_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <Pagination paginator={recipients} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Message</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {b.preview ? <WhatsAppPreview header={b.preview.header ? { format: 'TEXT', text: b.preview.header } : null} body={b.preview.body} footer={b.preview.footer ?? undefined} buttons={b.preview.buttons.map((x) => ({ type: x.type, text: x.text ?? undefined }))} /> : <p className="text-sm text-muted-foreground">Template no longer available.</p>}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
