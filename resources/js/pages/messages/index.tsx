import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { type MessageRow } from '@/types/whatsapp';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Send } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Messages', href: '/messages' }];

interface Props {
    messages: Paginated<MessageRow>;
    filters: { direction?: string; status?: string; q?: string };
    stats: { sent: number; delivered: number; failed: number; received: number };
}

export default function MessagesIndex({ messages, filters, stats }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const apply = (next: Partial<Props['filters']>) => router.get(route('messages.index'), { ...filters, ...next, q: q || undefined }, { preserveState: true, replace: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Messages" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Messages"
                    description="Outbound messages sent through the Cloud API and inbound messages received via webhooks. Statuses update from the messages webhook (sent → delivered → read / failed)."
                    actions={
                        <Button asChild>
                            <Link href={route('messages.create')}>
                                <Send /> Compose
                            </Link>
                        </Button>
                    }
                />
                <FlashMessages />

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    {[
                        { label: 'Sent', value: stats.sent },
                        { label: 'Delivered / read', value: stats.delivered },
                        { label: 'Failed', value: stats.failed },
                        { label: 'Received', value: stats.received },
                    ].map((s) => (
                        <Card key={s.label}>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground uppercase">{s.label}</p>
                                <p className="text-2xl font-semibold">{s.value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    apply({});
                                }}
                                className="flex flex-1 gap-2"
                            >
                                <Input placeholder="Search number or text…" value={q} onChange={(e) => setQ(e.target.value)} className="max-w-xs" />
                                <Button type="submit" variant="outline">
                                    Search
                                </Button>
                            </form>
                            <Select value={filters.direction ?? 'all'} onValueChange={(v) => apply({ direction: v === 'all' ? undefined : v })}>
                                <SelectTrigger className="w-36">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All directions</SelectItem>
                                    <SelectItem value="outbound">Outbound</SelectItem>
                                    <SelectItem value="inbound">Inbound</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select value={filters.status ?? 'all'} onValueChange={(v) => apply({ status: v === 'all' ? undefined : v })}>
                                <SelectTrigger className="w-36">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All statuses</SelectItem>
                                    {['accepted', 'sent', 'delivered', 'read', 'failed', 'received'].map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="py-2 pr-3"></th>
                                        <th className="py-2 pr-3">Contact</th>
                                        <th className="py-2 pr-3">Type</th>
                                        <th className="py-2 pr-3">Preview</th>
                                        <th className="py-2 pr-3">Status</th>
                                        <th className="py-2 pr-3">Sender</th>
                                        <th className="py-2">When</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {messages.data.length === 0 && (
                                        <tr>
                                            <td colSpan={7} className="py-10 text-center text-muted-foreground">
                                                No messages yet.
                                            </td>
                                        </tr>
                                    )}
                                    {messages.data.map((m) => (
                                        <tr key={m.id} className="cursor-pointer hover:bg-muted/40" onClick={() => router.visit(route('messages.show', m.id))}>
                                            <td className="py-2 pr-3">
                                                {m.direction === 'outbound' ? <ArrowUpRight className="size-4 text-brand-dark" /> : <ArrowDownLeft className="size-4 text-violet-600" />}
                                            </td>
                                            <td className="py-2 pr-3 whitespace-nowrap">
                                                <p className="font-medium">{m.contact?.name ?? (m.direction === 'outbound' ? m.to : m.from)}</p>
                                                <p className="font-mono text-xs text-muted-foreground">+{m.direction === 'outbound' ? m.to : m.from}</p>
                                            </td>
                                            <td className="py-2 pr-3 capitalize">{m.type}</td>
                                            <td className="max-w-md truncate py-2 pr-3 text-muted-foreground" title={m.preview ?? ''}>
                                                {m.preview}
                                                {m.status === 'failed' && m.error_message && <span className="block truncate text-xs text-red-600">{m.error_message}</span>}
                                            </td>
                                            <td className="py-2 pr-3">
                                                <StatusBadge status={m.status} />
                                            </td>
                                            <td className="py-2 pr-3 whitespace-nowrap text-muted-foreground">{m.phone?.display_phone_number ?? '—'}</td>
                                            <td className="py-2 whitespace-nowrap text-muted-foreground">{formatDate(m.created_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination paginator={messages} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
