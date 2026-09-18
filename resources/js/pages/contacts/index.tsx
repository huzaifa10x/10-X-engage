import FlashMessages from '@/components/flash-messages';
import WindowBadge from '@/components/inbox/window-badge';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatListTime } from '@/lib/format';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { type ContactRow } from '@/types/whatsapp';
import { Head, Link, router } from '@inertiajs/react';
import { MessageSquareText, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Contacts', href: '/contacts' }];

interface Props {
    contacts: Paginated<ContactRow>;
    filters: { q?: string; window?: string; tag?: string };
    tags: string[];
    stats: { total: number; open_windows: number; unread: number };
}

export default function ContactsIndex({ contacts, filters, tags, stats }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const apply = (next: Partial<Props['filters']>) => router.get(route('contacts.index'), { ...filters, ...next, q: q || undefined }, { preserveState: true, replace: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contacts" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Contacts"
                    description="Every WhatsApp number you talk to. Contacts are created here or automatically when someone messages your business number."
                    actions={
                        <Button asChild>
                            <Link href={route('contacts.create')}>
                                <Plus /> New contact
                            </Link>
                        </Button>
                    }
                />
                <FlashMessages />

                <div className="grid grid-cols-3 gap-3">
                    {[
                        { label: 'Contacts', value: stats.total },
                        { label: 'Open windows', value: stats.open_windows },
                        { label: 'Unread', value: stats.unread },
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
                                <Input placeholder="Search name, number, email, company…" value={q} onChange={(e) => setQ(e.target.value)} className="max-w-sm" />
                                <Button type="submit" variant="outline">
                                    Search
                                </Button>
                            </form>
                            <Select value={filters.window ?? 'all'} onValueChange={(v) => apply({ window: v === 'all' ? undefined : v })}>
                                <SelectTrigger className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Any window</SelectItem>
                                    <SelectItem value="open">Window open</SelectItem>
                                    <SelectItem value="closed">Window closed</SelectItem>
                                </SelectContent>
                            </Select>
                            {tags.length > 0 && (
                                <Select value={filters.tag ?? 'all'} onValueChange={(v) => apply({ tag: v === 'all' ? undefined : v })}>
                                    <SelectTrigger className="w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All tags</SelectItem>
                                        {tags.map((t) => (
                                            <SelectItem key={t} value={t}>
                                                {t}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </div>

                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="py-2 pr-3">Contact</th>
                                        <th className="py-2 pr-3">Tags</th>
                                        <th className="py-2 pr-3">Conversation window</th>
                                        <th className="py-2 pr-3">Last message</th>
                                        <th className="py-2 pr-3">Source</th>
                                        <th className="py-2 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {contacts.data.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="py-10 text-center text-muted-foreground">
                                                No contacts found.
                                            </td>
                                        </tr>
                                    )}
                                    {contacts.data.map((c) => (
                                        <tr key={c.id} className="hover:bg-muted/40">
                                            <td className="py-2.5 pr-3">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-xs font-semibold text-zinc-700">{c.initials}</div>
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">{c.display_name}</p>
                                                        <p className="truncate font-mono text-xs text-muted-foreground">
                                                            {c.phone}
                                                            {c.company ? ` · ${c.company}` : ''}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-2.5 pr-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {c.tags.map((t) => (
                                                        <span key={t} className="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px]">
                                                            {t}
                                                        </span>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="py-2.5 pr-3">
                                                <WindowBadge window={c.window} />
                                            </td>
                                            <td className="max-w-xs py-2.5 pr-3">
                                                <p className="truncate text-muted-foreground" title={c.last_message_preview ?? ''}>
                                                    {c.last_message_preview ?? '—'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatListTime(c.last_message_at)}
                                                    {c.unread_count > 0 && <span className="ml-2 rounded-full bg-brand px-1.5 text-[10px] font-bold text-[#14200a]">{c.unread_count} new</span>}
                                                </p>
                                            </td>
                                            <td className="py-2.5 pr-3 text-xs text-muted-foreground capitalize">{c.source}</td>
                                            <td className="py-2.5">
                                                <div className="flex justify-end gap-1">
                                                    <Button asChild size="sm" variant="ghost" title="Open chat">
                                                        <Link href={route('inbox.show', c.id)}>
                                                            <MessageSquareText />
                                                        </Link>
                                                    </Button>
                                                    <Button asChild size="sm" variant="ghost" title="Edit">
                                                        <Link href={route('contacts.edit', c.id)}>
                                                            <Pencil />
                                                        </Link>
                                                    </Button>
                                                    <Button size="sm" variant="ghost" title="Delete" onClick={() => confirm(`Delete ${c.display_name}? Messages are kept in the log.`) && router.delete(route('contacts.destroy', c.id), { preserveScroll: true })}>
                                                        <Trash2 className="text-red-500" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination paginator={contacts} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
