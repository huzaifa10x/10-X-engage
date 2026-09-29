import BroadcastStatus from '@/components/broadcast-status';
import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { type BroadcastRow } from '@/types/whatsapp';
import { Head, Link, router } from '@inertiajs/react';
import { Layers, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Broadcasts', href: '/broadcasts' }];

export default function BroadcastsIndex({ broadcasts }: { broadcasts: Paginated<BroadcastRow> }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Broadcasts" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Broadcasts"
                    description="Send an approved template to a segment. Sends are queued and rate-limited; delivery and read counts update live from Meta's status webhooks."
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={route('segments.index')}>
                                    <Layers /> Segments
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={route('broadcasts.create')}>
                                    <Plus /> New broadcast
                                </Link>
                            </Button>
                        </>
                    }
                />
                <FlashMessages />
                <Card>
                    <CardContent className="p-4">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="py-2 pr-3">Name</th>
                                        <th className="py-2 pr-3">Status</th>
                                        <th className="py-2 pr-3">Audience</th>
                                        <th className="py-2 pr-3">Template</th>
                                        <th className="py-2 pr-3 text-right">Sent</th>
                                        <th className="py-2 pr-3 text-right">Delivered</th>
                                        <th className="py-2 pr-3 text-right">Read</th>
                                        <th className="py-2 pr-3 text-right">Failed</th>
                                        <th className="py-2">When</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {broadcasts.data.length === 0 && (
                                        <tr>
                                            <td colSpan={9} className="py-10 text-center text-muted-foreground">
                                                No broadcasts yet.
                                            </td>
                                        </tr>
                                    )}
                                    {broadcasts.data.map((b) => (
                                        <tr key={b.id} className="cursor-pointer hover:bg-muted/40" onClick={() => router.visit(route('broadcasts.show', b.id))}>
                                            <td className="py-2.5 pr-3 font-medium">{b.name}</td>
                                            <td className="py-2.5 pr-3">
                                                <BroadcastStatus status={b.status} />
                                            </td>
                                            <td className="py-2.5 pr-3 text-muted-foreground">
                                                {b.segment ?? 'All contacts'} · {b.counts.total || '—'}
                                            </td>
                                            <td className="py-2.5 pr-3 text-muted-foreground">{b.template?.name ?? '—'}</td>
                                            <td className="py-2.5 pr-3 text-right">{b.counts.sent}</td>
                                            <td className="py-2.5 pr-3 text-right">{b.counts.delivered}</td>
                                            <td className="py-2.5 pr-3 text-right">{b.counts.read}</td>
                                            <td className="py-2.5 pr-3 text-right text-red-600">{b.counts.failed || ''}</td>
                                            <td className="py-2.5 whitespace-nowrap text-muted-foreground">{formatDate(b.scheduled_at ?? b.started_at ?? b.created_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination paginator={broadcasts} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
