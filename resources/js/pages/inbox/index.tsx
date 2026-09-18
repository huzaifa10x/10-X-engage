import Composer from '@/components/inbox/composer';
import ConversationList from '@/components/inbox/conversation-list';
import MessageBubble from '@/components/inbox/message-bubble';
import TemplateDialog, { type TemplateInput } from '@/components/inbox/template-dialog';
import WindowBadge from '@/components/inbox/window-badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatDayLabel } from '@/lib/format';
import { http, HttpError } from '@/lib/http';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type ChatMessage, type ContactRow, type InboxPhone, type InboxTemplate } from '@/types/whatsapp';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronUp, Loader2, MessageSquareText, Pencil, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface Props {
    conversations: ContactRow[];
    phones: InboxPhone[];
    templates: InboxTemplate[];
    selected: { contact: ContactRow; messages: ChatMessage[]; has_more: boolean } | null;
    filters: { q?: string };
    poll_interval_ms: number;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inbox', href: '/inbox' }];

type SendBody = { type: string; phone_number_id?: number; reply_to?: string | null; text?: { body: string }; template?: TemplateInput; media?: { source: 'id'; id: string; caption?: string; filename?: string } };

export default function Inbox({ conversations: initialConversations, phones, templates, selected, filters, poll_interval_ms }: Props) {
    const [conversations, setConversations] = useState<ContactRow[]>(initialConversations);
    const [query, setQuery] = useState(filters.q ?? '');
    const [contact, setContact] = useState<ContactRow | null>(selected?.contact ?? null);
    const [messages, setMessages] = useState<ChatMessage[]>(selected?.messages ?? []);
    const [hasMore, setHasMore] = useState(selected?.has_more ?? false);
    const [loadingOlder, setLoadingOlder] = useState(false);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [replyTo, setReplyTo] = useState<ChatMessage | null>(null);
    const [templateOpen, setTemplateOpen] = useState(false);
    const [phoneId, setPhoneId] = useState<number | null>(phones.find((p) => p.is_default)?.id ?? phones[0]?.id ?? null);
    const [now, setNow] = useState(() => Date.now());

    const scrollRef = useRef<HTMLDivElement>(null);
    const lastServerTime = useRef<string | null>(null);
    const stickToBottom = useRef(true);

    /* Reset chat state whenever the selected contact changes (Inertia partial reload) */
    useEffect(() => {
        setContact(selected?.contact ?? null);
        setMessages(selected?.messages ?? []);
        setHasMore(selected?.has_more ?? false);
        setReplyTo(null);
        setError(null);
        lastServerTime.current = null;
        stickToBottom.current = true;
        if (selected?.contact) {
            http(route('inbox.read', selected.contact.id), { method: 'POST' }).catch(() => undefined);
            setConversations((list) => list.map((c) => (c.id === selected.contact.id ? { ...c, unread_count: 0 } : c)));
        }
    }, [selected]);

    /* Auto-scroll */
    useEffect(() => {
        if (stickToBottom.current && scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, [messages, contact?.id]);

    const onScroll = () => {
        const el = scrollRef.current;
        if (!el) return;
        stickToBottom.current = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
    };

    /* Merge helper: new ids appended, updated rows replaced, optimistic rows dropped when confirmed */
    const merge = useCallback((incoming: ChatMessage[]) => {
        if (incoming.length === 0) return;
        setMessages((prev) => {
            const byId = new Map(prev.map((m) => [m.id, m]));
            for (const m of incoming) byId.set(m.id, m);
            return Array.from(byId.values())
                .filter((m) => !(m.pending && incoming.some((i) => i.wamid && i.direction === 'outbound' && i.preview === m.preview)))
                .sort((a, b) => a.id - b.id);
        });
    }, []);

    /* Polling: messages of the open chat + the conversation list */
    useEffect(() => {
        let cancelled = false;
        const tick = async () => {
            if (document.hidden || cancelled) return;
            try {
                if (contact) {
                    const lastId = messages.filter((m) => !m.pending).reduce((max, m) => Math.max(max, m.id), 0);
                    const params = new URLSearchParams({ after: String(lastId) });
                    if (lastServerTime.current) params.set('since', lastServerTime.current);
                    const res = await http<{ messages: ChatMessage[]; contact: ContactRow; server_time: string }>(`${route('inbox.messages', contact.id)}?${params}`);
                    if (cancelled) return;
                    lastServerTime.current = res.server_time;
                    merge(res.messages);
                    setContact(res.contact);
                    if (res.messages.some((m) => m.direction === 'inbound') && !document.hidden) {
                        http(route('inbox.read', contact.id), { method: 'POST' }).catch(() => undefined);
                    }
                }
                const list = await http<{ conversations: ContactRow[] }>(`${route('inbox.conversations')}?q=${encodeURIComponent(query)}`);
                if (!cancelled) setConversations(list.conversations.map((c) => (contact && c.id === contact.id ? { ...c, unread_count: 0 } : c)));
            } catch {
                /* transient network error – try again next tick */
            }
        };
        const id = window.setInterval(tick, poll_interval_ms);
        return () => {
            cancelled = true;
            window.clearInterval(id);
        };
    }, [contact, messages, query, poll_interval_ms, merge]);

    /* Countdown re-render every 30s */
    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), 30_000);
        return () => window.clearInterval(id);
    }, []);

    const secondsLeft = useMemo(() => {
        if (!contact?.window.expires_at) return 0;
        return Math.max(0, Math.floor((new Date(contact.window.expires_at).getTime() - now) / 1000));
    }, [contact, now]);

    /* Select a conversation without reloading the whole page */
    const select = (c: ContactRow) => {
        if (c.id === contact?.id) return;
        router.visit(route('inbox.show', c.id), { only: ['selected'], preserveState: true, preserveScroll: true });
    };

    /* Search (server-side, debounced) */
    useEffect(() => {
        const id = window.setTimeout(async () => {
            const list = await http<{ conversations: ContactRow[] }>(`${route('inbox.conversations')}?q=${encodeURIComponent(query)}`).catch(() => null);
            if (list) setConversations(list.conversations);
        }, 250);
        return () => window.clearTimeout(id);
    }, [query]);

    /* Sending ------------------------------------------------------------- */
    const send = async (body: SendBody, optimistic?: Partial<ChatMessage>) => {
        if (!contact) return;
        setSending(true);
        setError(null);
        const tempId = -Date.now();
        if (optimistic) {
            setMessages((prev) => [...prev, { id: tempId, wamid: null, direction: 'outbound', type: body.type, status: 'pending', preview: null, body: {}, context_wamid: replyTo?.wamid ?? null, template_id: null, error_code: null, error_message: null, sent_at: null, delivered_at: null, read_at: null, failed_at: null, timestamp: new Date().toISOString(), updated_at: null, pending: true, ...optimistic }]);
            stickToBottom.current = true;
        }
        try {
            const res = await http<{ message: ChatMessage; contact: ContactRow }>(route('inbox.send', contact.id), {
                method: 'POST',
                json: { ...body, phone_number_id: phoneId ?? undefined, reply_to: replyTo?.wamid ?? null },
            });
            setMessages((prev) => [...prev.filter((m) => m.id !== tempId), res.message].sort((a, b) => a.id - b.id));
            setContact(res.contact);
            setConversations((list) => {
                const rest = list.filter((c) => c.id !== res.contact.id);
                return [{ ...res.contact, unread_count: 0 }, ...rest];
            });
            setReplyTo(null);
            setTemplateOpen(false);
        } catch (e) {
            const err = e as HttpError;
            const data = (err.data ?? {}) as { message?: ChatMessage | null; contact?: ContactRow; error?: string; errors?: Record<string, string[]> };
            const text = data.error ?? Object.values(data.errors ?? {}).flat()[0] ?? err.message;
            setError(text);
            if (data.message) {
                setMessages((prev) => [...prev.filter((m) => m.id !== tempId), data.message as ChatMessage].sort((a, b) => a.id - b.id));
            } else {
                setMessages((prev) => prev.filter((m) => m.id !== tempId));
            }
            if (data.contact) setContact(data.contact);
        } finally {
            setSending(false);
        }
    };

    const sendText = (text: string) => send({ type: 'text', text: { body: text } }, { type: 'text', preview: text, body: { text } });

    const sendTemplate = (input: TemplateInput) => {
        const t = templates.find((x) => String(x.id) === input.id);
        return send({ type: 'template', template: input }, { type: 'template', preview: `Template ${t?.name ?? ''}`, body: { template: { name: t?.name ?? null, text: t?.components.find((c) => c.type === 'BODY')?.text ?? null } } });
    };

    const sendFile = async (file: File) => {
        if (!contact || !phoneId) return;
        const kind = file.type.startsWith('image/') ? 'image' : file.type.startsWith('video/') ? 'video' : file.type.startsWith('audio/') ? 'audio' : 'document';
        setSending(true);
        setError(null);
        try {
            const fd = new FormData();
            fd.append('phone_number_id', String(phoneId));
            fd.append('media_type', kind);
            fd.append('file', file);
            const up = await http<{ media_id: string; file_name: string }>(route('media.store'), { method: 'POST', body: fd });
            await send({ type: kind, media: { source: 'id', id: up.media_id, filename: kind === 'document' ? up.file_name : undefined } }, { type: kind, preview: `${kind}: ${file.name}`, body: { media: { type: kind, id: up.media_id, link: null, caption: null, filename: file.name, mime_type: file.type } } });
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setSending(false);
        }
    };

    const loadOlder = async () => {
        if (!contact || messages.length === 0) return;
        setLoadingOlder(true);
        try {
            const res = await http<{ messages: ChatMessage[]; has_more: boolean }>(`${route('inbox.messages', contact.id)}?before=${messages[0].id}`);
            stickToBottom.current = false;
            setMessages((prev) => [...res.messages, ...prev]);
            setHasMore(res.has_more);
        } finally {
            setLoadingOlder(false);
        }
    };

    /* Group messages by day */
    const grouped = useMemo(() => {
        const groups: { label: string; items: ChatMessage[] }[] = [];
        for (const m of messages) {
            const label = m.timestamp ? formatDayLabel(m.timestamp) : '';
            const last = groups[groups.length - 1];
            if (last && last.label === label) last.items.push(m);
            else groups.push({ label, items: [m] });
        }
        return groups;
    }, [messages]);

    const accountTemplates = useMemo(() => {
        const phone = phones.find((p) => p.id === phoneId);
        return templates.filter((t) => !phone || t.account_id === phone.account_id);
    }, [templates, phones, phoneId]);

    const totalUnread = conversations.reduce((n, c) => n + c.unread_count, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={contact ? `${contact.display_name} · Inbox` : 'Inbox'} />
            <div className="flex h-[calc(100dvh-4.5rem)] overflow-hidden rounded-xl border bg-white md:m-2">
                {/* Left: conversations */}
                <aside className={cn('w-full shrink-0 border-r md:w-80 lg:w-96', contact && 'hidden md:block')}>
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h1 className="text-base font-semibold">
                            Inbox {totalUnread > 0 && <span className="ml-1 rounded-full bg-brand px-2 py-0.5 text-xs font-bold text-[#14200a]">{totalUnread}</span>}
                        </h1>
                        <Link href={route('contacts.index')} className="text-xs text-brand-dark underline-offset-2 hover:underline">
                            Manage contacts
                        </Link>
                    </div>
                    <div className="h-[calc(100%-49px)]">
                        <ConversationList conversations={conversations} selectedId={contact?.id ?? null} query={query} onQuery={setQuery} onSelect={select} />
                    </div>
                </aside>

                {/* Right: chat */}
                <section className={cn('flex min-w-0 flex-1 flex-col', !contact && 'hidden md:flex')}>
                    {!contact ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-3 bg-zinc-50 text-center">
                            <div className="flex size-16 items-center justify-center rounded-full bg-brand-soft text-brand-dark">
                                <MessageSquareText className="size-7" />
                            </div>
                            <p className="font-medium">Select a conversation</p>
                            <p className="max-w-sm text-sm text-muted-foreground">Incoming and outgoing WhatsApp messages appear here in real time. Pick a contact on the left or create a new one.</p>
                        </div>
                    ) : (
                        <>
                            {/* Header */}
                            <header className="flex items-center gap-3 border-b bg-white px-3 py-2.5">
                                <button type="button" className="rounded-md p-1.5 hover:bg-muted md:hidden" onClick={() => router.visit(route('inbox.index'))}>
                                    <ArrowLeft className="size-4" />
                                </button>
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-semibold text-[#14200a]">{contact.initials}</div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold">{contact.display_name}</p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {contact.phone}
                                        {contact.company ? ` · ${contact.company}` : ''}
                                    </p>
                                </div>
                                <WindowBadge window={contact.window} secondsLeft={secondsLeft} className="hidden sm:inline-flex" />
                                {phones.length > 1 && (
                                    <Select value={phoneId ? String(phoneId) : ''} onValueChange={(v) => setPhoneId(Number(v))}>
                                        <SelectTrigger className="hidden h-8 w-44 text-xs lg:flex">
                                            <SelectValue placeholder="Sender" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {phones.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.display_phone_number}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                                <Button asChild variant="ghost" size="icon" title="Edit contact">
                                    <Link href={route('contacts.edit', contact.id)}>
                                        <Pencil />
                                    </Link>
                                </Button>
                            </header>

                            {/* Messages */}
                            <div ref={scrollRef} onScroll={onScroll} className="flex-1 overflow-y-auto bg-[#efeae2] px-3 py-4 md:px-8" style={{ backgroundImage: 'radial-gradient(rgba(0,0,0,0.035) 1px, transparent 1px)', backgroundSize: '18px 18px' }}>
                                {hasMore && (
                                    <div className="mb-3 flex justify-center">
                                        <Button variant="outline" size="sm" onClick={loadOlder} disabled={loadingOlder}>
                                            {loadingOlder ? <Loader2 className="animate-spin" /> : <ChevronUp />} Load earlier messages
                                        </Button>
                                    </div>
                                )}
                                {messages.length === 0 && (
                                    <div className="mx-auto max-w-sm rounded-lg bg-white/80 p-4 text-center text-sm text-muted-foreground">
                                        No messages with {contact.display_name} yet. Start with an approved template — free-form messages unlock once the customer replies.
                                    </div>
                                )}
                                {grouped.map((g) => (
                                    <div key={g.label} className="space-y-1.5">
                                        <div className="sticky top-0 z-10 my-3 flex justify-center">
                                            <span className="rounded-md bg-white/90 px-2.5 py-1 text-[11px] font-medium text-zinc-600 shadow-sm">{g.label}</span>
                                        </div>
                                        {g.items.map((m) => (
                                            <MessageBubble key={m.id} m={m} onReply={(x) => x.wamid && setReplyTo(x)} />
                                        ))}
                                    </div>
                                ))}
                            </div>

                            {error && (
                                <div className="flex items-center justify-between gap-3 border-t border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                    <span>{error}</span>
                                    <button type="button" className="text-xs underline" onClick={() => setError(null)}>
                                        dismiss
                                    </button>
                                </div>
                            )}

                            <Composer window={contact.window} secondsLeft={secondsLeft} sending={sending} replyTo={replyTo} onCancelReply={() => setReplyTo(null)} onSendText={sendText} onSendFile={sendFile} onOpenTemplates={() => setTemplateOpen(true)} hasTemplates={accountTemplates.length > 0} />

                            <TemplateDialog open={templateOpen} onOpenChange={setTemplateOpen} templates={accountTemplates} contactName={contact.display_name} sending={sending} error={templateOpen ? error : null} onSend={sendTemplate} />
                        </>
                    )}
                </section>
            </div>
            {phones.length === 0 && (
                <div className="mx-2 mb-2 flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    <RefreshCw className="size-4" /> No registered phone number yet — finish onboarding before sending messages.
                </div>
            )}
        </AppLayout>
    );
}
