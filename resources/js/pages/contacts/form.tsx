import Field from '@/components/field';
import FlashMessages from '@/components/flash-messages';
import WindowBadge from '@/components/inbox/window-badge';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type ContactRow } from '@/types/whatsapp';
import { Head, Link, router, useForm } from '@inertiajs/react';
import OptInBadge from '@/components/opt-in-badge';
import { Checkbox } from '@/components/ui/checkbox';
import { formatDate } from '@/lib/format';
import { Loader2, MessageSquareText, Save, X } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface ConsentEvent {
    id: number;
    action: 'opt_in' | 'opt_out';
    source: string;
    scope: string[];
    keyword: string | null;
    consent_text: string | null;
    user: string | null;
    created_at: string | null;
}

interface Props {
    contact: (ContactRow & { notes: string | null; phone_number_id: number | null; is_suppressed?: boolean }) | null;
    phones: { id: number; label: string }[];
    consent_events?: ConsentEvent[];
}

type FormData = { name: string; phone: string; email: string; company: string; tags: string[]; notes: string; phone_number_id: string };

export default function ContactForm({ contact, phones, consent_events = [] }: Props) {
    const editing = !!contact;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Contacts', href: '/contacts' },
        { title: editing ? contact.display_name : 'New contact', href: editing ? route('contacts.edit', contact.id) : route('contacts.create') },
    ];

    const form = useForm<FormData>({
        name: contact?.name ?? '',
        phone: contact?.phone ?? '+971',
        email: contact?.email ?? '',
        company: contact?.company ?? '',
        tags: contact?.tags ?? [],
        notes: contact?.notes ?? '',
        phone_number_id: contact?.phone_number_id ? String(contact.phone_number_id) : '',
    });
    const { data, setData, errors, processing } = form;
    const [tagInput, setTagInput] = useState('');

    const addTag = () => {
        const t = tagInput.trim();
        if (t && !data.tags.includes(t)) setData('tags', [...data.tags, t]);
        setTagInput('');
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, phone_number_id: d.phone_number_id || null }));
        if (editing) form.put(route('contacts.update', contact.id));
        else form.post(route('contacts.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? `Edit ${contact.display_name}` : 'New contact'} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={editing ? contact.display_name : 'New contact'}
                    description={editing ? 'Update the contact details. The WhatsApp number identifies the conversation.' : 'A contact must exist before you can message a number. The first message must be an approved template.'}
                    actions={
                        editing ? (
                            <>
                                <WindowBadge window={contact.window} />
                                <Button asChild variant="outline">
                                    <Link href={route('inbox.show', contact.id)}>
                                        <MessageSquareText /> Open chat
                                    </Link>
                                </Button>
                            </>
                        ) : undefined
                    }
                />
                <FlashMessages />

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Field label="Name" error={errors.name} required>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus />
                            </Field>
                            <Field label="WhatsApp number" error={errors.phone ?? (errors as Record<string, string>).wa_id} hint="International format with country code, e.g. +971501234567" required>
                                <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+9715XXXXXXXX" />
                            </Field>
                            <Field label="Email" error={errors.email}>
                                <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            </Field>
                            <Field label="Company" error={errors.company}>
                                <Input value={data.company} onChange={(e) => setData('company', e.target.value)} />
                            </Field>
                            <Field label="Tags" error={errors.tags} hint="Press Enter to add">
                                <div className="flex flex-wrap items-center gap-1.5 rounded-md border px-2 py-1.5">
                                    {data.tags.map((t) => (
                                        <span key={t} className="inline-flex items-center gap-1 rounded-full bg-brand-soft px-2 py-0.5 text-xs text-[#2b4a08]">
                                            {t}
                                            <button type="button" onClick={() => setData('tags', data.tags.filter((x) => x !== t))}>
                                                <X className="size-3" />
                                            </button>
                                        </span>
                                    ))}
                                    <input
                                        value={tagInput}
                                        onChange={(e) => setTagInput(e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter' || e.key === ',') {
                                                e.preventDefault();
                                                addTag();
                                            }
                                        }}
                                        onBlur={addTag}
                                        className="min-w-24 flex-1 bg-transparent text-sm outline-none"
                                        placeholder={data.tags.length ? '' : 'lead, vip, support…'}
                                    />
                                </div>
                            </Field>
                            {phones.length > 0 && (
                                <Field label="Preferred sender" error={errors.phone_number_id} hint="Business number used for this conversation by default.">
                                    <Select value={data.phone_number_id || 'none'} onValueChange={(v) => setData('phone_number_id', v === 'none' ? '' : v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Default number</SelectItem>
                                            {phones.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                            )}
                            <div className="sm:col-span-2">
                                <Field label="Notes" error={errors.notes}>
                                    <Textarea rows={4} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                                </Field>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        {editing && <ConsentCard contact={contact} events={consent_events} />}
                        <Card>
                            <CardContent className="space-y-3 p-4 text-sm text-muted-foreground">
                                <p className="font-medium text-foreground">How messaging works</p>
                                <p>New contacts can only receive an approved template first. A sent template — or any message from the customer — opens a 24-hour window for free-form replies.</p>
                                <p>When the window closes, the chat shows “24-hour window has been closed” and you send another template to reopen it.</p>
                            </CardContent>
                        </Card>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={processing} className="flex-1">
                                {processing ? <Loader2 className="animate-spin" /> : <Save />} {editing ? 'Save changes' : 'Create contact'}
                            </Button>
                            <Button asChild type="button" variant="outline">
                                <Link href={route('contacts.index')}>Cancel</Link>
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function ConsentCard({ contact, events }: { contact: NonNullable<Props['contact']>; events: ConsentEvent[] }) {
    const [status, setStatus] = useState<'opted_in' | 'opted_out' | 'unknown'>(contact.opt_in_status);
    const [scope, setScope] = useState<string[]>(contact.opt_in_scope.length ? contact.opt_in_scope : ['marketing', 'transactional']);
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);
    const dirty = status !== contact.opt_in_status || (status === 'opted_in' && scope.join() !== contact.opt_in_scope.join());

    const save = () =>
        router.put(route('contacts.consent', contact.id), { status, scope, consent_text: text }, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center justify-between text-base">
                    Consent <OptInBadge status={contact.opt_in_status} />
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
                <Select value={status} onValueChange={(v) => setStatus(v as typeof status)}>
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="opted_in">Opted in</SelectItem>
                        <SelectItem value="opted_out">Opted out (blocks broadcasts & templates)</SelectItem>
                        <SelectItem value="unknown">Unknown / not asked</SelectItem>
                    </SelectContent>
                </Select>
                {status === 'opted_in' && (
                    <div className="flex gap-4">
                        {['marketing', 'transactional'].map((s) => (
                            <label key={s} className="flex items-center gap-2 capitalize">
                                <Checkbox checked={scope.includes(s)} onCheckedChange={(v) => setScope(v ? [...scope, s] : scope.filter((x) => x !== s))} /> {s}
                            </label>
                        ))}
                    </div>
                )}
                {status === 'opted_in' && dirty && <Textarea rows={2} placeholder="Proof of consent (where / when they agreed)" value={text} onChange={(e) => setText(e.target.value)} />}
                {dirty && (
                    <Button size="sm" onClick={save} disabled={busy}>
                        {busy ? <Loader2 className="animate-spin" /> : null} Save consent
                    </Button>
                )}
                {contact.is_suppressed && <p className="text-xs text-red-600">On the suppression list — no business-initiated messages will be sent.</p>}
                {events.length > 0 && (
                    <div className="border-t pt-2">
                        <p className="mb-1 text-xs font-medium text-muted-foreground uppercase">Ledger</p>
                        <ul className="space-y-1 text-xs text-muted-foreground">
                            {events.map((e) => (
                                <li key={e.id}>
                                    <span className={e.action === 'opt_in' ? 'text-[#2b4a08]' : 'text-red-600'}>{e.action === 'opt_in' ? 'Opt-in' : 'Opt-out'}</span> via {e.source}
                                    {e.keyword ? ` (“${e.keyword}”)` : ''}
                                    {e.user ? ` by ${e.user}` : ''} · {formatDate(e.created_at)}
                                    {e.consent_text ? <span className="block italic">{e.consent_text}</span> : null}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
