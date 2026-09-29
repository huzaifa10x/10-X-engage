import Field from '@/components/field';
import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { http } from '@/lib/http';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FileUp, Loader2, Upload } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contacts', href: '/contacts' },
    { title: 'Import', href: '/contacts/import' },
];

type Preview = { headers: string[]; rows: string[][]; mapping: Record<string, string | null>; total: number };

export default function ContactImport({ fields }: { fields: string[] }) {
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [mapping, setMapping] = useState<Record<string, string>>({});
    const [defaultTags, setDefaultTags] = useState('');
    const [markOptedIn, setMarkOptedIn] = useState(false);
    const [consentText, setConsentText] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const load = async (f: File) => {
        setFile(f);
        setBusy(true);
        setError(null);
        try {
            const fd = new FormData();
            fd.append('file', f);
            const p = await http<Preview>(route('contacts.import.preview'), { method: 'POST', body: fd });
            setPreview(p);
            setMapping(Object.fromEntries(Object.entries(p.mapping).map(([k, v]) => [k, v ?? ''])));
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setBusy(false);
        }
    };

    const submit = () => {
        if (!file) return;
        const fd = new FormData();
        fd.append('file', file);
        Object.entries(mapping).forEach(([k, v]) => fd.append(`mapping[${k}]`, v));
        fd.append('default_tags', defaultTags);
        fd.append('mark_opted_in', markOptedIn ? '1' : '0');
        fd.append('consent_text', consentText);
        router.post(route('contacts.import.store'), fd, { forceFormData: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onError: (errs) => setError(Object.values(errs).flat().join(' ')) });
    };

    const hasPhone = Object.values(mapping).includes('phone');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Import contacts" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Import contacts from CSV" description="Rows are matched on WhatsApp number — existing contacts are updated, never duplicated. Tags are merged." />
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">1. Choose a file</CardTitle>
                        <CardDescription>CSV with a header row. Recognised columns: name, phone/whatsapp, email, company, tags (comma separated), opt_in (yes/no), notes. Numbers need a country code, e.g. +971501234567 or 971501234567.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <label className="flex cursor-pointer items-center gap-3 rounded-md border border-dashed p-4 hover:bg-muted/40">
                            <FileUp className="size-5 text-muted-foreground" />
                            <span className="text-sm">{file ? `${file.name} (${preview ? `${preview.total} rows` : '…'})` : 'Click to select a .csv file'}</span>
                            <input type="file" accept=".csv,text/csv" className="hidden" onChange={(e) => e.target.files?.[0] && load(e.target.files[0])} />
                        </label>
                        {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
                    </CardContent>
                </Card>

                {preview && (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">2. Map columns</CardTitle>
                                <CardDescription>We guessed from the headers; adjust if needed. The WhatsApp number column is required.</CardDescription>
                            </CardHeader>
                            <CardContent className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr>
                                            {preview.headers.map((h, i) => (
                                                <th key={i} className="min-w-40 p-2 text-left align-top">
                                                    <p className="mb-1 truncate text-xs text-muted-foreground">{h || `Column ${i + 1}`}</p>
                                                    <Select value={mapping[i] || 'skip'} onValueChange={(v) => setMapping({ ...mapping, [i]: v === 'skip' ? '' : v })}>
                                                        <SelectTrigger className="h-8">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="skip">— skip —</SelectItem>
                                                            {fields.map((f) => (
                                                                <SelectItem key={f} value={f}>
                                                                    {f === 'phone' ? 'WhatsApp number' : f.replace('_', ' ')}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {preview.rows.map((r, i) => (
                                            <tr key={i} className="text-muted-foreground">
                                                {preview.headers.map((_, j) => (
                                                    <td key={j} className="max-w-48 truncate p-2">
                                                        {r[j]}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {!hasPhone && <p className="mt-2 text-sm text-red-600">Map one column to the WhatsApp number.</p>}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">3. Options</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <Field label="Add tags to every imported contact" hint="Comma separated, e.g. newsletter, sep-2026">
                                    <Input value={defaultTags} onChange={(e) => setDefaultTags(e.target.value)} />
                                </Field>
                                <div className="space-y-3">
                                    <label className="flex items-start gap-2 text-sm">
                                        <Checkbox checked={markOptedIn} onCheckedChange={(v) => setMarkOptedIn(Boolean(v))} className="mt-0.5" />
                                        <span>
                                            Mark all imported contacts as <b>opted in</b> (marketing + transactional)
                                            <span className="block text-xs text-muted-foreground">Only do this if you hold their consent to receive WhatsApp messages from your business. The consent text below is stored with each contact as proof.</span>
                                        </span>
                                    </label>
                                    {markOptedIn && <Textarea rows={2} placeholder="e.g. Signed up on website form on 12 Sep 2026 and agreed to WhatsApp updates" value={consentText} onChange={(e) => setConsentText(e.target.value)} />}
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex justify-end">
                            <Button size="lg" onClick={submit} disabled={busy || !hasPhone}>
                                {busy ? <Loader2 className="animate-spin" /> : <Upload />} Import {preview.total} row{preview.total === 1 ? '' : 's'}
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
