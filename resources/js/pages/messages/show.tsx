import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { type MessageRow } from '@/types/whatsapp';
import { Head, Link } from '@inertiajs/react';
import { Reply } from 'lucide-react';

type Detail = MessageRow & {
    payload: unknown;
    response: unknown;
    error_data: unknown;
    conversation_id: string | null;
    conversation_origin: string | null;
    pricing_category: string | null;
    billable: boolean | null;
    template: { id: number; name: string; language: string } | null;
};

export default function MessageShow({ message }: { message: Detail }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Messages', href: '/messages' },
        { title: `#${message.id}`, href: route('messages.show', message.id) },
    ];

    const timeline = [
        { label: 'Accepted by Meta', at: message.created_at, show: message.direction === 'outbound' },
        { label: 'Sent', at: message.sent_at, show: true },
        { label: 'Delivered', at: message.delivered_at, show: true },
        { label: 'Read', at: message.read_at, show: true },
        { label: 'Failed', at: message.failed_at, show: true },
        { label: 'Received', at: message.received_at, show: message.direction === 'inbound' },
    ].filter((t) => t.show && t.at);

    const counterpart = message.direction === 'outbound' ? message.to : message.from;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Message #${message.id}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${message.direction === 'outbound' ? 'To' : 'From'} +${counterpart}`}
                    description={message.wamid ?? 'No WhatsApp message ID'}
                    actions={
                        <>
                            <StatusBadge status={message.status} className="text-xs" />
                            {message.wamid && (
                                <Button asChild variant="outline">
                                    <Link href={route('messages.create', { to: `+${counterpart}`, reply_to: message.wamid })}>
                                        <Reply /> Reply
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />
                <FlashMessages />

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Content</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="rounded-xl bg-[#e5ddd5] p-4">
                                <div className={`max-w-md rounded-lg px-3 py-2 text-sm whitespace-pre-wrap shadow-sm ${message.direction === 'outbound' ? 'ml-auto bg-[#dcf8c6]' : 'bg-white'}`}>
                                    {message.preview || <span className="text-muted-foreground">({message.type})</span>}
                                </div>
                            </div>
                            {message.status === 'failed' && (
                                <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                                    <p className="font-semibold">
                                        Error {message.error_code} {message.error_title ? `· ${message.error_title}` : ''}
                                    </p>
                                    <p>{message.error_message}</p>
                                </div>
                            )}
                            <details className="rounded-md border">
                                <summary className="cursor-pointer px-3 py-2 text-sm font-medium">Request payload</summary>
                                <pre className="overflow-auto border-t bg-muted/40 p-3 text-xs">{JSON.stringify(message.payload, null, 2)}</pre>
                            </details>
                            <details className="rounded-md border">
                                <summary className="cursor-pointer px-3 py-2 text-sm font-medium">API response</summary>
                                <pre className="overflow-auto border-t bg-muted/40 p-3 text-xs">{JSON.stringify(message.response ?? message.error_data, null, 2)}</pre>
                            </details>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Delivery timeline</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ol className="space-y-3">
                                    {timeline.map((t) => (
                                        <li key={t.label} className="flex items-start gap-3 text-sm">
                                            <span className="mt-1.5 size-2 shrink-0 rounded-full bg-brand" />
                                            <div>
                                                <p className="font-medium">{t.label}</p>
                                                <p className="text-xs text-muted-foreground">{formatDate(t.at)}</p>
                                            </div>
                                        </li>
                                    ))}
                                    {timeline.length === 0 && <p className="text-sm text-muted-foreground">Waiting for status webhooks…</p>}
                                </ol>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="space-y-2 text-sm">
                                    <Row k="Type" v={message.type} />
                                    <Row k="Sender" v={message.phone?.display_phone_number ?? '—'} />
                                    <Row k="Contact" v={message.contact?.name ?? '—'} />
                                    {message.template && <Row k="Template" v={<Link className="text-brand-dark underline" href={route('templates.show', message.template.id)}>{message.template.name}</Link>} />}
                                    <Row k="Conversation" v={message.conversation_id ? <span className="font-mono text-xs break-all">{message.conversation_id}</span> : '—'} />
                                    <Row k="Origin" v={message.conversation_origin ?? '—'} />
                                    <Row k="Pricing" v={message.pricing_category ? `${message.pricing_category}${message.billable ? ' · billable' : ''}` : '—'} />
                                </dl>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Row({ k, v }: { k: string; v: React.ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-3">
            <dt className="text-muted-foreground">{k}</dt>
            <dd className="text-right font-medium">{v}</dd>
        </div>
    );
}
