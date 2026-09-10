import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import WhatsAppPreview from '@/components/whatsapp-preview';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { type TemplateComponent, type TemplateRow } from '@/types/whatsapp';
import { Head, Link, router } from '@inertiajs/react';
import { RefreshCw, Send, Trash2 } from 'lucide-react';

type Detail = TemplateRow & {
    components: TemplateComponent[];
    last_response: unknown;
    rejected_reason: string | null;
    quality_score: string | null;
    previous_category: string | null;
    last_synced_at: string | null;
    last_status_update_at: string | null;
};

export default function TemplateShow({ template }: { template: Detail }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Templates', href: '/templates' },
        { title: template.name, href: route('templates.show', template.id) },
    ];
    const header = template.components.find((c) => c.type === 'HEADER');
    const body = template.components.find((c) => c.type === 'BODY');
    const footer = template.components.find((c) => c.type === 'FOOTER');
    const buttons = template.components.find((c) => c.type === 'BUTTONS')?.buttons ?? [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={template.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={template.name}
                    description={`${template.language} · ${template.category}${template.template_id ? ` · ID ${template.template_id}` : ''}`}
                    actions={
                        <>
                            <StatusBadge status={template.status} className="text-xs" />
                            <Button variant="outline" onClick={() => router.post(route('templates.refresh', template.id), {}, { preserveScroll: true })}>
                                <RefreshCw /> Refresh status
                            </Button>
                            {template.status === 'APPROVED' && (
                                <Button asChild>
                                    <Link href={route('messages.create')}>
                                        <Send /> Send
                                    </Link>
                                </Button>
                            )}
                            <Button variant="destructive" onClick={() => confirm('Delete this template on Meta and locally?') && router.delete(route('templates.destroy', template.id))}>
                                <Trash2 /> Delete
                            </Button>
                        </>
                    }
                />
                <FlashMessages />

                {template.status === 'REJECTED' && (
                    <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                        <p className="font-semibold">Rejected by Meta</p>
                        <p>{template.rejected_reason ?? 'No reason supplied. Check WhatsApp Manager for details.'}</p>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Preview</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <WhatsAppPreview
                                header={header ? { format: header.format ?? 'TEXT', text: header.text } : null}
                                body={template.category === 'AUTHENTICATION' ? '123456 is your verification code.' : body?.text}
                                footer={footer?.text}
                                buttons={buttons.map((b) => ({ type: b.type, text: b.text }))}
                            />
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                <Row k="Account" v={template.account?.name ?? template.account?.waba_id ?? '—'} />
                                <Row k="Quality score" v={template.quality_score ?? '—'} />
                                <Row k="Previous category" v={template.previous_category ?? '—'} />
                                <Row k="Status updated" v={formatDate(template.last_status_update_at)} />
                                <Row k="Last synced" v={formatDate(template.last_synced_at)} />
                                <Row k="Created" v={formatDate(template.created_at)} />
                            </dl>
                            <details className="rounded-md border" open>
                                <summary className="cursor-pointer px-3 py-2 text-sm font-medium">Components (as sent to / returned by Meta)</summary>
                                <pre className="overflow-auto border-t bg-muted/40 p-3 text-xs">{JSON.stringify(template.components, null, 2)}</pre>
                            </details>
                            {template.last_response != null && (
                                <details className="rounded-md border">
                                    <summary className="cursor-pointer px-3 py-2 text-sm font-medium">Create response</summary>
                                    <pre className="overflow-auto border-t bg-muted/40 p-3 text-xs">{JSON.stringify(template.last_response, null, 2)}</pre>
                                </details>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function Row({ k, v }: { k: string; v: React.ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-3 border-b pb-2">
            <dt className="text-muted-foreground">{k}</dt>
            <dd className="text-right font-medium">{v}</dd>
        </div>
    );
}
