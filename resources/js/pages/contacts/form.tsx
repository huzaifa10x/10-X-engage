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
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2, MessageSquareText, Save, X } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Props {
    contact: (ContactRow & { notes: string | null; phone_number_id: number | null }) | null;
    phones: { id: number; label: string }[];
}

type FormData = { name: string; phone: string; email: string; company: string; tags: string[]; notes: string; phone_number_id: string };

export default function ContactForm({ contact, phones }: Props) {
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
