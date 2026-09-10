import Field from '@/components/field';
import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import WhatsAppPreview from '@/components/whatsapp-preview';
import AppLayout from '@/layouts/app-layout';
import { countVariables, fillVariables } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Loader2, Plus, Send, Trash2, Upload } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Props {
    accounts: { id: number; waba_id: string; name: string | null }[];
    options: { categories: string[]; languages: Record<string, string>; header_formats: string[] };
}

type ButtonForm = {
    type: 'QUICK_REPLY' | 'URL' | 'PHONE_NUMBER' | 'COPY_CODE';
    text: string;
    url: string;
    example: string;
    phone_number: string;
};

type FormData = {
    whatsapp_account_id: string;
    name: string;
    language: string;
    category: string;
    header: { format: string; text: string; example: string; handle: string; file_name: string };
    body: { text: string; examples: string[]; add_security_recommendation: boolean };
    footer: { text: string; code_expiration_minutes: string };
    buttons: ButtonForm[];
    otp: { otp_type: 'COPY_CODE' | 'ONE_TAP'; text: string; autofill_text: string; package_name: string; signature_hash: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Templates', href: '/templates' },
    { title: 'Create', href: '/templates/create' },
];

export default function TemplateCreate({ accounts, options }: Props) {
    const form = useForm<FormData>({
        whatsapp_account_id: accounts[0] ? String(accounts[0].id) : '',
        name: '',
        language: 'en_US',
        category: 'MARKETING',
        header: { format: 'NONE', text: '', example: '', handle: '', file_name: '' },
        body: { text: '', examples: [], add_security_recommendation: true },
        footer: { text: '', code_expiration_minutes: '10' },
        buttons: [],
        otp: { otp_type: 'COPY_CODE', text: 'Copy Code', autofill_text: 'Autofill', package_name: '', signature_hash: '' },
    });
    const { data, setData, errors, processing } = form;
    const isAuth = data.category === 'AUTHENTICATION';
    const bodyVars = countVariables(data.body.text);
    const headerVars = data.header.format === 'TEXT' ? countVariables(data.header.text) : 0;

    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);

    const uploadHeader = async (file: File) => {
        setUploading(true);
        setUploadError(null);
        const fd = new FormData();
        fd.append('whatsapp_account_id', data.whatsapp_account_id);
        fd.append('format', data.header.format);
        fd.append('file', file);
        try {
            const res = await fetch(route('templates.media'), {
                method: 'POST',
                body: fd,
                headers: { 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''), Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const json = await res.json();
            if (!res.ok) throw new Error(json.message ?? 'Upload failed');
            setData('header', { ...data.header, handle: json.handle, file_name: json.file_name });
        } catch (e) {
            setUploadError((e as Error).message);
        } finally {
            setUploading(false);
        }
    };

    const addButton = (type: ButtonForm['type']) => setData('buttons', [...data.buttons, { type, text: '', url: '', example: '', phone_number: '' }]);
    const updateButton = (i: number, patch: Partial<ButtonForm>) => setData('buttons', data.buttons.map((b, j) => (j === i ? { ...b, ...patch } : b)));
    const removeButton = (i: number) => setData('buttons', data.buttons.filter((_, j) => j !== i));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            buttons: isAuth ? [{ type: 'OTP', ...d.otp }] : d.buttons,
            body: { ...d.body, examples: d.body.examples.slice(0, bodyVars) },
        }));
        form.post(route('templates.store'));
    };

    const previewButtons = isAuth ? [{ type: 'COPY_CODE', text: data.otp.text || 'Copy Code' }] : data.buttons.map((b) => ({ type: b.type, text: b.text || (b.type === 'COPY_CODE' ? 'Copy offer code' : '') }));

    const counts = { quick: data.buttons.filter((b) => b.type === 'QUICK_REPLY').length, url: data.buttons.filter((b) => b.type === 'URL').length, phone: data.buttons.filter((b) => b.type === 'PHONE_NUMBER').length, copy: data.buttons.filter((b) => b.type === 'COPY_CODE').length };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create template" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Create message template" description="Submits POST /{WABA-ID}/message_templates. Templates are reviewed by Meta — the status (PENDING → APPROVED / REJECTED) arrives via the message_template_status_update webhook." />
                <FlashMessages />

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-5">
                    <div className="space-y-6 lg:col-span-3">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Basics</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Field label="WhatsApp Business Account" error={errors.whatsapp_account_id} required>
                                    <Select value={data.whatsapp_account_id} onValueChange={(v) => setData('whatsapp_account_id', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select account" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {accounts.map((a) => (
                                                <SelectItem key={a.id} value={String(a.id)}>
                                                    {a.name ?? a.waba_id}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field label="Template name" error={errors.name} hint="Lowercase letters, numbers and underscores only." required>
                                    <Input value={data.name} onChange={(e) => setData('name', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))} placeholder="order_confirmation" />
                                </Field>
                                <Field label="Category" error={errors.category} required>
                                    <Select value={data.category} onValueChange={(v) => setData('category', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.categories.map((c) => (
                                                <SelectItem key={c} value={c}>
                                                    {c}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field label="Language" error={errors.language} required>
                                    <Select value={data.language} onValueChange={(v) => setData('language', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(options.languages).map(([code, label]) => (
                                                <SelectItem key={code} value={code}>
                                                    {label} ({code})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                            </CardContent>
                        </Card>

                        {isAuth ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Authentication template</CardTitle>
                                    <CardDescription>Body and footer text are generated by Meta. Choose the OTP button type.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox checked={data.body.add_security_recommendation} onCheckedChange={(v) => setData('body', { ...data.body, add_security_recommendation: Boolean(v) })} />
                                        Add security recommendation (“For your security, do not share this code.”)
                                    </label>
                                    <Field label="Code expiration (minutes)" hint="1–90. Adds the expiry footer.">
                                        <Input type="number" min={1} max={90} className="w-32" value={data.footer.code_expiration_minutes} onChange={(e) => setData('footer', { ...data.footer, code_expiration_minutes: e.target.value })} />
                                    </Field>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="OTP button type">
                                            <Select value={data.otp.otp_type} onValueChange={(v) => setData('otp', { ...data.otp, otp_type: v as 'COPY_CODE' | 'ONE_TAP' })}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="COPY_CODE">Copy code</SelectItem>
                                                    <SelectItem value="ONE_TAP">One-tap autofill (Android)</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        <Field label="Button text">
                                            <Input maxLength={25} value={data.otp.text} onChange={(e) => setData('otp', { ...data.otp, text: e.target.value })} />
                                        </Field>
                                        {data.otp.otp_type === 'ONE_TAP' && (
                                            <>
                                                <Field label="Autofill text">
                                                    <Input maxLength={25} value={data.otp.autofill_text} onChange={(e) => setData('otp', { ...data.otp, autofill_text: e.target.value })} />
                                                </Field>
                                                <Field label="Android package name" required>
                                                    <Input value={data.otp.package_name} onChange={(e) => setData('otp', { ...data.otp, package_name: e.target.value })} placeholder="com.example.app" />
                                                </Field>
                                                <Field label="App signature hash" required>
                                                    <Input value={data.otp.signature_hash} onChange={(e) => setData('otp', { ...data.otp, signature_hash: e.target.value })} />
                                                </Field>
                                            </>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ) : (
                            <>
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Header (optional)</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex flex-wrap gap-2">
                                            {['NONE', ...options.header_formats].map((f) => (
                                                <button
                                                    type="button"
                                                    key={f}
                                                    onClick={() => setData('header', { format: f, text: '', example: '', handle: '', file_name: '' })}
                                                    className={cn('rounded-md border px-3 py-1.5 text-sm', data.header.format === f ? 'border-brand bg-brand-soft text-[#2b4a08]' : 'hover:bg-muted')}
                                                >
                                                    {f === 'NONE' ? 'None' : f.charAt(0) + f.slice(1).toLowerCase()}
                                                </button>
                                            ))}
                                        </div>
                                        {data.header.format === 'TEXT' && (
                                            <>
                                                <Field label="Header text" error={errors['header.text' as keyof typeof errors]} hint="Max 60 characters, one {{1}} variable allowed." required>
                                                    <Input maxLength={60} value={data.header.text} onChange={(e) => setData('header', { ...data.header, text: e.target.value })} />
                                                </Field>
                                                {headerVars > 0 && (
                                                    <Field label="Example for {{1}}" required>
                                                        <Input value={data.header.example} onChange={(e) => setData('header', { ...data.header, example: e.target.value })} />
                                                    </Field>
                                                )}
                                            </>
                                        )}
                                        {['IMAGE', 'VIDEO', 'DOCUMENT'].includes(data.header.format) && (
                                            <Field label="Sample file" error={errors['header.handle' as keyof typeof errors]} hint="Uploaded through the Resumable Upload API; the returned handle is sent as example.header_handle." required>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <label className={cn('inline-flex h-10 cursor-pointer items-center gap-2 rounded-md border px-3 text-sm hover:bg-muted', uploading && 'opacity-60')}>
                                                        {uploading ? <Loader2 className="size-4 animate-spin" /> : <Upload className="size-4" />} Choose file
                                                        <input
                                                            type="file"
                                                            className="hidden"
                                                            accept={data.header.format === 'IMAGE' ? 'image/jpeg,image/png' : data.header.format === 'VIDEO' ? 'video/mp4' : 'application/pdf'}
                                                            disabled={uploading || !data.whatsapp_account_id}
                                                            onChange={(e) => e.target.files?.[0] && uploadHeader(e.target.files[0])}
                                                        />
                                                    </label>
                                                    {data.header.handle && <span className="text-sm text-brand-dark">✓ {data.header.file_name}</span>}
                                                </div>
                                                {uploadError && <p className="text-sm text-red-600">{uploadError}</p>}
                                            </Field>
                                        )}
                                        {data.header.format === 'LOCATION' && <p className="text-sm text-muted-foreground">A location header is filled in when the template is sent (latitude, longitude, name, address).</p>}
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Body</CardTitle>
                                        <CardDescription>Use {'{{1}}'}, {'{{2}}'} … for variables. Each needs an example value for Meta’s review.</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <Field label="Body text" error={errors['body.text' as keyof typeof errors]} hint={`${data.body.text.length}/1024`} required>
                                            <Textarea rows={6} maxLength={1024} value={data.body.text} onChange={(e) => setData('body', { ...data.body, text: e.target.value })} placeholder="Hi {{1}}, your order {{2}} has shipped!" />
                                        </Field>
                                        <div className="flex gap-2">
                                            <Button type="button" size="sm" variant="outline" onClick={() => setData('body', { ...data.body, text: `${data.body.text}{{${bodyVars + 1}}}` })}>
                                                <Plus /> Add variable
                                            </Button>
                                        </div>
                                        {bodyVars > 0 && (
                                            <div className="space-y-2">
                                                <p className="text-sm font-medium">Example values</p>
                                                {Array.from({ length: bodyVars }).map((_, i) => (
                                                    <div key={i} className="flex items-center gap-2">
                                                        <span className="w-12 shrink-0 font-mono text-xs text-muted-foreground">{`{{${i + 1}}}`}</span>
                                                        <Input
                                                            value={data.body.examples[i] ?? ''}
                                                            onChange={(e) => {
                                                                const next = [...data.body.examples];
                                                                next[i] = e.target.value;
                                                                setData('body', { ...data.body, examples: next });
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
                                        <CardTitle className="text-base">Footer (optional)</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <Field label="Footer text" error={errors['footer.text' as keyof typeof errors]} hint="Max 60 characters, no variables.">
                                            <Input maxLength={60} value={data.footer.text} onChange={(e) => setData('footer', { ...data.footer, text: e.target.value })} />
                                        </Field>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Buttons (optional)</CardTitle>
                                        <CardDescription>Up to 10 buttons; max 2 URL, 1 phone number, 1 copy code.</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex flex-wrap gap-2">
                                            <Button type="button" size="sm" variant="outline" disabled={data.buttons.length >= 10} onClick={() => addButton('QUICK_REPLY')}>
                                                <Plus /> Quick reply
                                            </Button>
                                            <Button type="button" size="sm" variant="outline" disabled={counts.url >= 2 || data.buttons.length >= 10} onClick={() => addButton('URL')}>
                                                <Plus /> Website URL
                                            </Button>
                                            <Button type="button" size="sm" variant="outline" disabled={counts.phone >= 1 || data.buttons.length >= 10} onClick={() => addButton('PHONE_NUMBER')}>
                                                <Plus /> Call phone
                                            </Button>
                                            <Button type="button" size="sm" variant="outline" disabled={counts.copy >= 1 || data.buttons.length >= 10} onClick={() => addButton('COPY_CODE')}>
                                                <Plus /> Copy offer code
                                            </Button>
                                        </div>
                                        {data.buttons.map((b, i) => (
                                            <div key={i} className="grid gap-2 rounded-md border p-3 sm:grid-cols-[110px_1fr_1fr_auto]">
                                                <span className="self-center text-xs font-semibold text-muted-foreground uppercase">{b.type.replace('_', ' ')}</span>
                                                {b.type !== 'COPY_CODE' && <Input placeholder="Button text (25 max)" maxLength={25} value={b.text} onChange={(e) => updateButton(i, { text: e.target.value })} />}
                                                {b.type === 'URL' && <Input placeholder="https://example.com/{{1}}" value={b.url} onChange={(e) => updateButton(i, { url: e.target.value })} />}
                                                {b.type === 'URL' && b.url.includes('{{1}}') && <Input className="sm:col-start-2" placeholder="Example URL suffix" value={b.example} onChange={(e) => updateButton(i, { example: e.target.value })} />}
                                                {b.type === 'PHONE_NUMBER' && <Input placeholder="+97150…" value={b.phone_number} onChange={(e) => updateButton(i, { phone_number: e.target.value })} />}
                                                {b.type === 'COPY_CODE' && <Input className="sm:col-span-2" placeholder="Example code (e.g. SAVE20)" value={b.example} onChange={(e) => updateButton(i, { example: e.target.value })} />}
                                                {b.type === 'QUICK_REPLY' && <span />}
                                                <Button type="button" variant="ghost" size="icon" className="sm:col-start-4" onClick={() => removeButton(i)}>
                                                    <Trash2 className="text-red-500" />
                                                </Button>
                                            </div>
                                        ))}
                                    </CardContent>
                                </Card>
                            </>
                        )}

                        <div className="flex justify-end">
                            <Button type="submit" size="lg" disabled={processing || !data.whatsapp_account_id} className="font-semibold">
                                {processing ? <Loader2 className="animate-spin" /> : <Send />} Submit for review
                            </Button>
                        </div>
                    </div>

                    <div className="lg:col-span-2">
                        <Card className="sticky top-4">
                            <CardHeader>
                                <CardTitle className="text-base">Preview</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <WhatsAppPreview
                                    header={isAuth ? null : data.header.format === 'NONE' ? null : { format: data.header.format, text: fillVariables(data.header.text, [data.header.example]) }}
                                    body={isAuth ? `123456 is your verification code.${data.body.add_security_recommendation ? ' For your security, do not share this code.' : ''}` : fillVariables(data.body.text, data.body.examples)}
                                    footer={isAuth ? (data.footer.code_expiration_minutes ? `This code expires in ${data.footer.code_expiration_minutes} minutes.` : undefined) : data.footer.text}
                                    buttons={previewButtons}
                                />
                                <p className="mt-3 text-xs text-muted-foreground">
                                    {data.name || 'template_name'} · {data.language} · {data.category}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
