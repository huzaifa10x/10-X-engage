import WindowBadge from '@/components/inbox/window-badge';
import { Input } from '@/components/ui/input';
import { formatListTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type ContactRow } from '@/types/whatsapp';
import { Link } from '@inertiajs/react';
import { Check, CheckCheck, Plus, Search } from 'lucide-react';

interface Props {
    conversations: ContactRow[];
    selectedId: number | null;
    query: string;
    onQuery: (q: string) => void;
    onSelect: (contact: ContactRow) => void;
}

export default function ConversationList({ conversations, selectedId, query, onQuery, onSelect }: Props) {
    return (
        <div className="flex h-full flex-col">
            <div className="flex items-center gap-2 border-b p-3">
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input value={query} onChange={(e) => onQuery(e.target.value)} placeholder="Search name or number" className="h-9 pl-8" />
                </div>
                <Link href={route('contacts.create')} className="inline-flex size-9 items-center justify-center rounded-md bg-brand text-[#14200a] hover:bg-brand/90" title="New contact">
                    <Plus className="size-4" />
                </Link>
            </div>

            <div className="flex-1 overflow-y-auto">
                {conversations.length === 0 && <p className="p-6 text-center text-sm text-muted-foreground">No contacts yet. Add a contact to start a conversation, or wait for an inbound message.</p>}
                {conversations.map((c) => {
                    const active = c.id === selectedId;
                    return (
                        <button
                            key={c.id}
                            type="button"
                            onClick={() => onSelect(c)}
                            className={cn('flex w-full items-center gap-3 border-b px-3 py-2.5 text-left transition-colors hover:bg-muted/60', active && 'bg-brand-soft hover:bg-brand-soft')}
                        >
                            <div className="relative shrink-0">
                                <div className={cn('flex size-11 items-center justify-center rounded-full text-sm font-semibold', active ? 'bg-brand text-[#14200a]' : 'bg-zinc-200 text-zinc-700')}>{c.initials}</div>
                                <WindowBadge window={c.window} compact className="absolute right-0 bottom-0 ring-2 ring-white" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex items-baseline justify-between gap-2">
                                    <p className={cn('truncate text-sm', c.unread_count > 0 ? 'font-semibold' : 'font-medium')}>{c.display_name}</p>
                                    <span className={cn('shrink-0 text-[11px]', c.unread_count > 0 ? 'font-semibold text-brand-dark' : 'text-muted-foreground')}>{formatListTime(c.last_message_at)}</span>
                                </div>
                                <div className="flex items-center justify-between gap-2">
                                    <p className={cn('flex min-w-0 items-center gap-1 truncate text-xs', c.unread_count > 0 ? 'text-foreground' : 'text-muted-foreground')}>
                                        {c.last_message_direction === 'outbound' && (c.last_message_preview ? <CheckCheck className="size-3 shrink-0 text-muted-foreground" /> : <Check className="size-3 shrink-0" />)}
                                        <span className="truncate">{c.last_message_preview ?? (c.name ? c.phone : 'No messages yet')}</span>
                                    </p>
                                    {c.unread_count > 0 && <span className="shrink-0 rounded-full bg-brand px-1.5 py-0.5 text-[10px] font-bold text-[#14200a]">{c.unread_count}</span>}
                                </div>
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
