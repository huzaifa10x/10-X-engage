import Field from '@/components/field';
import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import WhatsAppPreview from '@/components/whatsapp-preview';
import AppLayout from '@/layouts/app-layout';
import { countVariables, fillVariables } from '@/lib/format';
import { http } from '@/lib/http';
import { type BreadcrumbItem } from '@/types';
import { type BroadcastRow, type InboxPhone, type InboxTemplate } from '@/types/whatsapp';
import { Head, useForm } from '@inertiajs/react';
import { CalendarClock, Loader2, Save, Send } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface Props {
    broadcast: (BroadcastRow & { template_params: TemplateParams | null; scheduled_at: string | null }) | null;
    phones: InboxPhone[];
    templates: InboxTemplate[];
    segments: { id: number; name: string }[];
    total_contacts: number;
    tokens: string[];
    default_require_opt_in: boolean;
}

type TemplateParams = { header: Record<string, string>; body: string[]; buttons: { index: number; sub_type: string; payload?: string; text?: string; coupon_code?: string }[] };

type FormData = {
    name: string;
    phone_number_id: string;
    message_template_id: string;
    segment_id: string;
    require_opt_in: boolean;
    template_params: TemplateParams;
    scheduled_at: string;
    action: 'draft' | 'send' | 'schedule';
};

export default function BroadcastForm({ broadcast, phones, templates, segments, total_contacts, tokens, default_require_opt_in }: Props) {
    const editing = !!broadcast;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Broadcasts', href: '/broadcasts' },
        { title: editing ? broadcast.name : 'New broadcast', href: '#' },
    ];
    const preselectSegment = new URLSearchParams(window.location.search).get('segment') ?? '';

    const form = useForm<FormData>({
        name: broadcast?.name ?? '',
        phone_number_id: broadcast ? String(broadcast.phone_number_id) : String(phones.find((p) => p.is_default)?.id ?? phones[0]?.id ?? ''),
        message_template_id: broadcast?.message_template_id ? String(broadcast.message_template_id) : '',
        segment_id: broadcast?.segment_id ? String(broadcast.segment_id) : preselectSegment,
        require_opt_in: broadcast?.require_opt_in ?? default_require_opt_in,
        template_params: broadcast?.template_params ?? { header: {}, body: [], buttons: [] },
        scheduled_at: broadcast?.scheduled_at ? broadcast.scheduled_at.slice(0, 16) : '',
        action: 'draft',
    });
    const { data, setData, errors, processing } = form;

    const phone = phones.find((p) => String(p.id) === data.phone_number_id);
    const accountTemplates = useMemo(() => templates.filter((t) => !phone || t.account_id === phone.account_id), [templates, phone]);
    const template = accountTemplates.find((t) => String(t.id) === data.message_template_id);
    const header = template?.components.find((c) => c.type === 'HEADER');
    const body = template?.components.find((c) => c.type === 'BODY');
    const footer = template?.components.find((c) => c.type === 'FOOTER');
    const buttons = template?.components.find((c) => c.type === 'BUTTONS')?.buttons ?? [];
    const bodyVars = countVariables(body?.text);
    const headerVars = header?.format === 'TEXT' ? countVariables(header.text) : 0;

    const [count, setCount] = useState<{ match: number; eligible: number } | null>(null);
    useEffect(() => {
        const id = window.setTimeout(async () => {
            const c = await http<{ match: number; eligible: number }>(route('segments.count'), { method: 'POST', json: { segment_id: data.segment_id || null, require_opt_in: data.require_opt_in } }).catch(() => null);
            setCount(c);
        }, 200);
        return () => window.clearTimeout(id);
    }, [data.segment_id, data.require_opt_in]);

    const setParam = (patch: Partial<TemplateParams>) => setData('template_params', { ...data.template_params, ...patch });
    const submit = (action: FormData['action']) => {
        form.transform((d) => ({ ...d, action, segment_id: d.segment_id || null, template_params: { ...d.template_params, body: d.template_params.body.slice(0, bodyVars) } }));
        if (editing) form.put(route('broadcasts.update', broadcast.id));
        else form.post(route('broadcasts.store'));
    };

    const sample = { name: 'Sara Ahmed', first_name: 'Sara', phone: '+971501234567', email: 'sara@example.com', company: 'Acme' };
    const renderTokens = (v: string) => v.replace(/\{\{\s*(?:contact\.)?([a-z_]+)\s*\}\}/gi, (_, k) => (sample as Record<string, string>)[k] ?? '');
    const ready = data.name.trim() && template && (bodyVars === 0 || data.template_params.body.slice(0, bodyVars).every((v) => v && v.trim())) && (headerVars === 0 || !!data.template_params.header.text) && (!header || ['TEXT', 'LOCATION'].includes(header.format ?? 'TEXT') || !!data.template_params.header.link);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? broadcast.name : 'New broadcast'} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title={editing ? `Edit: ${broadcast.name}` : 'New broadcast'} description="Choose a template, personalise its variables, pick the audience and send now or schedule." />
                <FlashMessages />
                {Object.keys(errors).length > 0 && (
                    <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        {Object.entries(errors).map(([k, v]) => (
                            <p key={k}>{v as string}</p>
                        ))}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-5">
                    <div className="space-y-6 lg:col-span-3">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">1. Message</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Field label="Broadcast name" error={errors.name} required>
                                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="October promo" />
                                </Field>
                                <Field label="Send from" error={errors.phone_number_id} required>
                                    <Select value={data.phone_number_id} onValueChange={(v) => form.setData({ ...data, phone_number_id: v, message_template_id: '' })}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Number" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {phones.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.display_phone_number} · {p.verified_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <div className="sm:col-span-2">
                                    <Field label="Approved template" error={errors.message_template_id} hint={accountTemplates.length === 0 ? 'No APPROVED templates for this number yet.' : undefined} required>
                                        <Select value={data.message_template_id} onValueChange={(v) => form.setData({ ...data, message_template_id: v, template_params: { header: {}, body: [], buttons: [] } })}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Choose a template" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {accountTemplates.map((t) => (
                                                    <SelectItem key={t.id} value={String(t.id)}>
                                                        {t.name} · {t.language} · {t.category}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                </div>
                                {template && (headerVars > 0 || bodyVars > 0 || (header && !['TEXT', 'LOCATION'].includes(header.format ?? 'TEXT'))) && (
                                    <div className="space-y-3 sm:col-span-2">
                                        <p className="text-sm font-medium">Variables</p>
                                        <p className="text-xs text-muted-foreground">
                                            Type a value or personalise with{' '}
                                            {tokens.map((t) => (
                                                <code key={t} className="mr-1 rounded bg-muted px-1">{`{{${t}}}`}</code>
                                            ))}
                                        </p>
                                        {header && !['TEXT', 'LOCATION'].includes(header.format ?? 'TEXT') && (
                                            <Field label={`${header.format} header URL`} required>
                                                <Input placeholder="https://…" value={data.template_params.header.link ?? ''} onChange={(e) => setParam({ header: { ...data.template_params.header, link: e.target.value } })} />
                                            </Field>
                                        )}
                                        {headerVars > 0 && (
                                            <Field label="Header {{1}}" required>
                                                <Input value={data.template_params.header.text ?? ''} onChange={(e) => setParam({ header: { ...data.template_params.header, text: e.target.value } })} />
                                            </Field>
                                        )}
                                        {Array.from({ length: bodyVars }).map((_, i) => (
                                            <div key={i} className="flex items-center gap-2">
                                                <span className="w-12 shrink-0 font-mono text-xs text-muted-foreground">{`{{${i + 1}}}`}</span>
                                                <Input
                                                    value={data.template_params.body[i] ?? ''}
                                                    placeholder={i === 0 ? '{{contact.first_name}}' : ''}
                                                    onChange={(e) => {
                                                        const next = [...data.template_params.body];
                                                        next[i] = e.target.value;
                                                        setParam({ body: next });
                                                    }}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">2. Audience</CardTitle>
                                <CardDescription>The segment decides who fits; consent decides who is allowed. Opted-out and suppressed contacts are always excluded.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Field label="Segment" error={errors.segment_id}>
                                    <Select value={data.segment_id || 'all'} onValueChange={(v) => setData('segment_id', v === 'all' ? '' : v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All contacts ({total_contacts})</SelectItem>
                                            {segments.map((s) => (
                                                <SelectItem key={s.id} value={String(s.id)}>
                                                    {s.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <label className="flex items-start gap-2 pt-6 text-sm">
                                    <Checkbox checked={data.require_opt_in} onCheckedChange={(v) => setData('require_opt_in', Boolean(v))} className="mt-0.5" />
                                    <span>
                                        Only contacts marked <b>opted in</b>
                                        <span className="block text-xs text-muted-foreground">Recommended for marketing. Unchecked: everyone not opted out.</span>
                                    </span>
                                </label>
                                <div className="rounded-md bg-muted/50 p-3 text-sm sm:col-span-2">
                                    {count ? (
                                        <>
                                            <b>{count.match}</b> match · <b className="text-[#2b4a08]">{count.eligible}</b> eligible
                                            {count.match - count.eligible > 0 && <span className="text-muted-foreground"> · {count.match - count.eligible} excluded by consent</span>}
                                        </>
                                    ) : (
                                        'Counting…'
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">3. Send</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-end gap-3">
                                <Field label="Schedule for (optional)" error={errors.scheduled_at}>
                                    <Input type="datetime-local" value={data.scheduled_at} onChange={(e) => setData('scheduled_at', e.target.value)} />
                                </Field>
                                <div className="ml-auto flex gap-2">
                                    <Button type="button" variant="outline" disabled={processing || !data.name.trim()} onClick={() => submit('draft')}>
                                        <Save /> Save draft
                                    </Button>
                                    {data.scheduled_at ? (
                                        <Button type="button" disabled={processing || !ready} onClick={() => submit('schedule')}>
                                            {processing ? <Loader2 className="animate-spin" /> : <CalendarClock />} Schedule
                                        </Button>
                                    ) : (
                                        <Button type="button" disabled={processing || !ready || !count?.eligible} onClick={() => confirm(`Send to ${count?.eligible ?? 0} contact(s) now?`) && submit('send')}>
                                            {processing ? <Loader2 className="animate-spin" /> : <Send />} Send now
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="lg:col-span-2">
                        <Card className="sticky top-4">
                            <CardHeader>
                                <CardTitle className="text-base">Preview (sample contact)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <WhatsAppPreview
                                    header={header ? { format: header.format ?? 'TEXT', text: fillVariables(header.text ?? '', [renderTokens(data.template_params.header.text ?? '')]) } : null}
                                    body={template ? fillVariables(body?.text ?? '', data.template_params.body.map(renderTokens)) : 'Choose a template'}
                                    footer={footer?.text}
                                    buttons={buttons}
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
