import Field from '@/components/field';
import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import WhatsAppPreview from '@/components/whatsapp-preview';
import AppLayout from '@/layouts/app-layout';
import { countVariables, fillVariables } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type TemplateComponent } from '@/types/whatsapp';
import { Head, useForm } from '@inertiajs/react';
import { FileText, Image as ImageIcon, LayoutTemplate, ListChecks, Loader2, MapPin, Mic, Send, Type, Upload, Video } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

interface Phone {
    id: number;
    phone_number_id: string;
    display_phone_number: string | null;
    verified_name: string | null;
    is_registered: boolean;
    is_default: boolean;
    account_id: number;
    waba_id: string;
    account_name: string | null;
}

interface Template {
    id: number;
    account_id: number;
    name: string;
    language: string;
    category: string;
    components: TemplateComponent[];
}

interface Props {
    phones: Phone[];
    templates: Template[];
    reply_to?: string | null;
    to?: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Messages', href: '/messages' },
    { title: 'Compose', href: '/messages/compose' },
];

const types = [
    { value: 'text', label: 'Text', icon: Type },
    { value: 'template', label: 'Template', icon: LayoutTemplate },
    { value: 'image', label: 'Image', icon: ImageIcon },
    { value: 'video', label: 'Video', icon: Video },
    { value: 'document', label: 'Document', icon: FileText },
    { value: 'audio', label: 'Audio', icon: Mic },
    { value: 'location', label: 'Location', icon: MapPin },
    { value: 'interactive', label: 'Buttons', icon: ListChecks },
];

type FormData = {
    phone_number_id: string;
    to: string;
    type: string;
    reply_to: string;
    text: { body: string; preview_url: boolean };
    media: { source: 'link' | 'id'; link: string; id: string; caption: string; filename: string };
    location: { latitude: string; longitude: string; name: string; address: string };
    template: { id: string; header: Record<string, string>; body: string[]; buttons: { index: number; sub_type: string; payload?: string; text?: string; coupon_code?: string }[] };
    interactive: { kind: 'button'; header: string; body: string; footer: string; buttons: { id: string; title: string }[] };
};

export default function MessageCreate({ phones, templates, reply_to, to }: Props) {
    const defaultPhone = phones.find((p) => p.is_default && p.is_registered) ?? phones.find((p) => p.is_registered) ?? phones[0];

    const form = useForm<FormData>({
        phone_number_id: defaultPhone ? String(defaultPhone.id) : '',
        to: to ?? '',
        type: 'text',
        reply_to: reply_to ?? '',
        text: { body: '', preview_url: false },
        media: { source: 'link', link: '', id: '', caption: '', filename: '' },
        location: { latitude: '', longitude: '', name: '', address: '' },
        template: { id: '', header: {}, body: [], buttons: [] },
        interactive: { kind: 'button', header: '', body: '', footer: '', buttons: [{ id: 'btn_1', title: '' }] },
    });

    const { data, setData, errors, processing } = form;
    const phone = phones.find((p) => String(p.id) === data.phone_number_id);
    const accountTemplates = useMemo(() => templates.filter((t) => !phone || t.account_id === phone.account_id), [templates, phone]);
    const template = accountTemplates.find((t) => String(t.id) === data.template.id);

    const header = template?.components.find((c) => c.type === 'HEADER');
    const body = template?.components.find((c) => c.type === 'BODY');
    const footer = template?.components.find((c) => c.type === 'FOOTER');
    const buttons = template?.components.find((c) => c.type === 'BUTTONS')?.buttons ?? [];
    const bodyVars = template?.category === 'AUTHENTICATION' ? 1 : countVariables(body?.text);
    const headerVars = header?.format === 'TEXT' ? countVariables(header.text) : 0;

    const selectTemplate = (id: string) => {
        const t = accountTemplates.find((x) => String(x.id) === id);
        const btns = (t?.components.find((c) => c.type === 'BUTTONS')?.buttons ?? [])
            .map((b, index) => ({ index, sub_type: b.type.toLowerCase() as string, type: b.type, url: b.url }))
            .filter((b) => b.type === 'QUICK_REPLY' || (b.type === 'URL' && (b.url ?? '').includes('{{1}}')) || b.type === 'COPY_CODE')
            .map((b) => ({ index: b.index, sub_type: b.sub_type, payload: '', text: '', coupon_code: '' }));
        setData('template', { id, header: {}, body: [], buttons: btns });
    };

    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);

    const uploadMedia = async (file: File) => {
        if (!phone) return;
        setUploading(true);
        setUploadError(null);
        const fd = new FormData();
        fd.append('phone_number_id', String(phone.id));
        fd.append('media_type', data.type);
        fd.append('file', file);
        try {
            const res = await fetch(route('media.store'), {
                method: 'POST',
                body: fd,
                headers: { 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''), Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const json = await res.json();
            if (!res.ok) throw new Error(json.message ?? 'Upload failed');
            setData('media', { ...data.media, source: 'id', id: json.media_id, filename: data.media.filename || json.file_name });
        } catch (e) {
            setUploadError((e as Error).message);
        } finally {
            setUploading(false);
        }
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('messages.store'));
    };

    const preview = (() => {
        switch (data.type) {
            case 'text':
                return { body: data.text.body };
            case 'template':
                return template
                    ? {
                          header: header ? { format: header.format ?? 'TEXT', text: fillVariables(header.text ?? '', [data.template.header.text ?? '']) } : null,
                          body: template.category === 'AUTHENTICATION' ? `${data.template.body[0] || '{{1}}'} is your verification code.` : fillVariables(body?.text ?? '', data.template.body),
                          footer: footer?.text,
                          buttons,
                      }
                    : { body: 'Select a template' };
            case 'location':
                return { header: { format: 'LOCATION' }, body: data.location.name || `${data.location.latitude}, ${data.location.longitude}` };
            case 'interactive':
                return { header: data.interactive.header ? { format: 'TEXT', text: data.interactive.header } : null, body: data.interactive.body, footer: data.interactive.footer, buttons: data.interactive.buttons.map((b) => ({ type: 'QUICK_REPLY', text: b.title })) };
            default:
                return { header: { format: data.type.toUpperCase() }, body: data.media.caption };
        }
    })();

    const isMedia = ['image', 'video', 'document', 'audio', 'sticker'].includes(data.type);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Send message" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Send a message" description="Builds the exact Messages Object and posts it to POST /{Phone-Number-ID}/messages. Free-form messages only reach users inside a 24-hour customer service window; use a template otherwise." />
                <FlashMessages />

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-5">
                    <div className="space-y-6 lg:col-span-3">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Sender & recipient</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Field label="From (business number)" error={errors.phone_number_id} required>
                                    <Select value={data.phone_number_id} onValueChange={(v) => setData('phone_number_id', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose a registered number" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {phones.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)} disabled={!p.is_registered}>
                                                    {p.display_phone_number} · {p.verified_name ?? p.account_name ?? p.waba_id}
                                                    {!p.is_registered ? ' (not registered)' : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field label="To (WhatsApp number)" error={errors.to} hint="International format, e.g. +971501234567" required>
                                    <Input value={data.to} onChange={(e) => setData('to', e.target.value)} placeholder="+9715XXXXXXXX" />
                                </Field>
                                <Field label="Reply to message ID" error={errors.reply_to} hint="Optional wamid — adds a context object so WhatsApp shows the reply quote.">
                                    <Input value={data.reply_to} onChange={(e) => setData('reply_to', e.target.value)} placeholder="wamid.HBg…" />
                                </Field>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Message type</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="grid grid-cols-4 gap-2 sm:grid-cols-8">
                                    {types.map((t) => (
                                        <button
                                            type="button"
                                            key={t.value}
                                            onClick={() => setData('type', t.value)}
                                            className={cn(
                                                'flex flex-col items-center gap-1 rounded-md border px-2 py-2.5 text-xs font-medium transition-colors',
                                                data.type === t.value ? 'border-brand bg-brand-soft text-[#2b4a08]' : 'hover:bg-muted',
                                            )}
                                        >
                                            <t.icon className="size-4" />
                                            {t.label}
                                        </button>
                                    ))}
                                </div>
                                {errors.type && <p className="text-sm text-red-600">{errors.type}</p>}

                                {data.type === 'text' && (
                                    <div className="space-y-3">
                                        <Field label="Body" error={errors['text.body' as keyof typeof errors]} hint={`${data.text.body.length}/4096 characters. Supports *bold*, _italic_, ~strikethrough~ and URLs.`} required>
                                            <Textarea rows={5} value={data.text.body} onChange={(e) => setData('text', { ...data.text, body: e.target.value })} />
                                        </Field>
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox checked={data.text.preview_url} onCheckedChange={(v) => setData('text', { ...data.text, preview_url: Boolean(v) })} />
                                            Show URL preview (<code className="text-xs">preview_url: true</code>)
                                        </label>
                                    </div>
                                )}

                                {isMedia && (
                                    <div className="space-y-4">
                                        <div className="flex gap-2">
                                            {(['link', 'id'] as const).map((s) => (
                                                <button
                                                    type="button"
                                                    key={s}
                                                    onClick={() => setData('media', { ...data.media, source: s })}
                                                    className={cn('rounded-md border px-3 py-1.5 text-sm', data.media.source === s ? 'border-brand bg-brand-soft' : 'hover:bg-muted')}
                                                >
                                                    {s === 'link' ? 'Public URL (link)' : 'Uploaded media (id)'}
                                                </button>
                                            ))}
                                        </div>
                                        {data.media.source === 'link' ? (
                                            <Field label="Media URL" error={errors['media.link' as keyof typeof errors]} hint="Must be a publicly reachable http(s) URL." required>
                                                <Input value={data.media.link} onChange={(e) => setData('media', { ...data.media, link: e.target.value })} placeholder="https://…" />
                                            </Field>
                                        ) : (
                                            <Field label="Media ID" error={errors['media.id' as keyof typeof errors]} hint="Upload a file to POST /{Phone-Number-ID}/media or paste an existing media ID." required>
                                                <div className="flex gap-2">
                                                    <Input value={data.media.id} onChange={(e) => setData('media', { ...data.media, id: e.target.value })} placeholder="Media ID" />
                                                    <label className={cn('inline-flex h-10 cursor-pointer items-center gap-2 rounded-md border px-3 text-sm whitespace-nowrap hover:bg-muted', uploading && 'opacity-60')}>
                                                        {uploading ? <Loader2 className="size-4 animate-spin" /> : <Upload className="size-4" />} Upload
                                                        <input type="file" className="hidden" disabled={uploading || !phone} onChange={(e) => e.target.files?.[0] && uploadMedia(e.target.files[0])} />
                                                    </label>
                                                </div>
                                                {uploadError && <p className="text-sm text-red-600">{uploadError}</p>}
                                            </Field>
                                        )}
                                        {data.type !== 'audio' && data.type !== 'sticker' && (
                                            <Field label="Caption" error={errors['media.caption' as keyof typeof errors]}>
                                                <Input value={data.media.caption} onChange={(e) => setData('media', { ...data.media, caption: e.target.value })} />
                                            </Field>
                                        )}
                                        {data.type === 'document' && (
                                            <Field label="File name" hint="Shown to the recipient, e.g. invoice.pdf">
                                                <Input value={data.media.filename} onChange={(e) => setData('media', { ...data.media, filename: e.target.value })} />
                                            </Field>
                                        )}
                                    </div>
                                )}

                                {data.type === 'location' && (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Latitude" error={errors['location.latitude' as keyof typeof errors]} required>
                                            <Input value={data.location.latitude} onChange={(e) => setData('location', { ...data.location, latitude: e.target.value })} placeholder="25.0785" />
                                        </Field>
                                        <Field label="Longitude" error={errors['location.longitude' as keyof typeof errors]} required>
                                            <Input value={data.location.longitude} onChange={(e) => setData('location', { ...data.location, longitude: e.target.value })} placeholder="55.1319" />
                                        </Field>
                                        <Field label="Name">
                                            <Input value={data.location.name} onChange={(e) => setData('location', { ...data.location, name: e.target.value })} />
                                        </Field>
                                        <Field label="Address" hint="Only shown when a name is set.">
                                            <Input value={data.location.address} onChange={(e) => setData('location', { ...data.location, address: e.target.value })} />
                                        </Field>
                                    </div>
                                )}

                                {data.type === 'interactive' && (
                                    <div className="space-y-4">
                                        <Field label="Header (optional)" hint="Max 60 characters">
                                            <Input maxLength={60} value={data.interactive.header} onChange={(e) => setData('interactive', { ...data.interactive, header: e.target.value })} />
                                        </Field>
                                        <Field label="Body" error={errors['interactive.body' as keyof typeof errors]} required>
                                            <Textarea rows={3} maxLength={1024} value={data.interactive.body} onChange={(e) => setData('interactive', { ...data.interactive, body: e.target.value })} />
                                        </Field>
                                        <Field label="Footer (optional)" hint="Max 60 characters">
                                            <Input maxLength={60} value={data.interactive.footer} onChange={(e) => setData('interactive', { ...data.interactive, footer: e.target.value })} />
                                        </Field>
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">Reply buttons (max 3, 20 chars each)</p>
                                            {data.interactive.buttons.map((b, i) => (
                                                <div key={i} className="flex gap-2">
                                                    <Input className="w-40" placeholder="id" value={b.id} onChange={(e) => updateArr(setData, data, 'interactive', 'buttons', i, { ...b, id: e.target.value })} />
                                                    <Input placeholder="Title" maxLength={20} value={b.title} onChange={(e) => updateArr(setData, data, 'interactive', 'buttons', i, { ...b, title: e.target.value })} />
                                                    <Button type="button" variant="ghost" onClick={() => setData('interactive', { ...data.interactive, buttons: data.interactive.buttons.filter((_, j) => j !== i) })}>
                                                        ✕
                                                    </Button>
                                                </div>
                                            ))}
                                            {data.interactive.buttons.length < 3 && (
                                                <Button type="button" variant="outline" size="sm" onClick={() => setData('interactive', { ...data.interactive, buttons: [...data.interactive.buttons, { id: `btn_${data.interactive.buttons.length + 1}`, title: '' }] })}>
                                                    Add button
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {data.type === 'template' && (
                                    <div className="space-y-4">
                                        <Field label="Approved template" error={errors['template.id' as keyof typeof errors]} hint={accountTemplates.length === 0 ? 'No APPROVED templates for this account. Create one or sync from Meta.' : undefined} required>
                                            <Select value={data.template.id} onValueChange={selectTemplate}>
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

                                        {template && header && header.format !== 'TEXT' && header.format !== 'LOCATION' && (
                                            <Field label={`${header.format} header`} hint="Public URL or an uploaded media ID for the header media." required>
                                                <div className="grid gap-2 sm:grid-cols-2">
                                                    <Input placeholder="https://… (link)" value={data.template.header.link ?? ''} onChange={(e) => setData('template', { ...data.template, header: { ...data.template.header, link: e.target.value } })} />
                                                    <Input placeholder="or media ID" value={data.template.header.id ?? ''} onChange={(e) => setData('template', { ...data.template, header: { ...data.template.header, id: e.target.value } })} />
                                                </div>
                                            </Field>
                                        )}
                                        {template && header?.format === 'LOCATION' && (
                                            <div className="grid gap-2 sm:grid-cols-4">
                                                {['latitude', 'longitude', 'name', 'address'].map((k) => (
                                                    <Input key={k} placeholder={k} value={data.template.header[k] ?? ''} onChange={(e) => setData('template', { ...data.template, header: { ...data.template.header, [k]: e.target.value } })} />
                                                ))}
                                            </div>
                                        )}
                                        {template && headerVars > 0 && (
                                            <Field label="Header variable {{1}}" required>
                                                <Input value={data.template.header.text ?? ''} onChange={(e) => setData('template', { ...data.template, header: { ...data.template.header, text: e.target.value } })} />
                                            </Field>
                                        )}
                                        {template && bodyVars > 0 && (
                                            <div className="space-y-2">
                                                <p className="text-sm font-medium">{template.category === 'AUTHENTICATION' ? 'One-time password' : 'Body variables'}</p>
                                                {Array.from({ length: bodyVars }).map((_, i) => (
                                                    <div key={i} className="flex items-center gap-2">
                                                        <span className="w-12 shrink-0 font-mono text-xs text-muted-foreground">{`{{${i + 1}}}`}</span>
                                                        <Input
                                                            value={data.template.body[i] ?? ''}
                                                            onChange={(e) => {
                                                                const next = [...data.template.body];
                                                                next[i] = e.target.value;
                                                                setData('template', { ...data.template, body: next });
                                                            }}
                                                        />
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                        {template && data.template.buttons.length > 0 && template.category !== 'AUTHENTICATION' && (
                                            <div className="space-y-2">
                                                <p className="text-sm font-medium">Button parameters</p>
                                                {data.template.buttons.map((b, i) => (
                                                    <div key={i} className="flex items-center gap-2">
                                                        <span className="w-28 shrink-0 text-xs text-muted-foreground">
                                                            #{b.index} {b.sub_type}
                                                        </span>
                                                        <Input
                                                            placeholder={b.sub_type === 'quick_reply' ? 'payload' : b.sub_type === 'url' ? 'URL suffix for {{1}}' : 'coupon code'}
                                                            value={b.sub_type === 'quick_reply' ? (b.payload ?? '') : b.sub_type === 'url' ? (b.text ?? '') : (b.coupon_code ?? '')}
                                                            onChange={(e) => {
                                                                const next = [...data.template.buttons];
                                                                const key = b.sub_type === 'quick_reply' ? 'payload' : b.sub_type === 'url' ? 'text' : 'coupon_code';
                                                                next[i] = { ...b, [key]: e.target.value };
                                                                setData('template', { ...data.template, buttons: next });
                                                            }}
                                                        />
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <div className="flex justify-end">
                            <Button type="submit" size="lg" disabled={processing || !phone?.is_registered} className="font-semibold">
                                {processing ? <Loader2 className="animate-spin" /> : <Send />} Send message
                            </Button>
                        </div>
                    </div>

                    <div className="lg:col-span-2">
                        <Card className="sticky top-4">
                            <CardHeader>
                                <CardTitle className="text-base">Preview</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <WhatsAppPreview {...preview} />
                                <p className="mt-3 text-xs text-muted-foreground">
                                    From {phone?.display_phone_number ?? '—'} → {data.to || '—'}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function updateArr(setData: (k: 'interactive', v: FormData['interactive']) => void, data: FormData, _key: 'interactive', _arrKey: 'buttons', index: number, value: { id: string; title: string }) {
    const next = [...data.interactive.buttons];
    next[index] = value;
    setData('interactive', { ...data.interactive, buttons: next });
}
