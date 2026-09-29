import StatusTicks from '@/components/inbox/status-ticks';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type ChatMessage } from '@/types/whatsapp';
import { Ban, Download, FileText, Image as ImageIcon, LayoutTemplate, Loader2, MapPin, Megaphone, Mic, Video } from 'lucide-react';

const mediaIcon: Record<string, typeof ImageIcon> = { image: ImageIcon, video: Video, document: FileText, audio: Mic, sticker: ImageIcon };

function Content({ m }: { m: ChatMessage }) {
    const b = m.body ?? {};

    if (b.media) {
        const Icon = mediaIcon[b.media.type] ?? FileText;
        const url = b.media.url ?? b.media.link;
        const label = b.media.filename ?? (url && !url.startsWith('/') ? url : `${b.media.type}${b.media.id ? ` · ${b.media.id}` : ''}`);
        return (
            <div className="space-y-1">
                {b.media.type === 'image' && url ? (
                    <a href={url} target="_blank" rel="noreferrer">
                        <img src={url} alt={b.media.caption ?? ''} className="max-h-72 rounded-md" loading="lazy" />
                    </a>
                ) : b.media.type === 'sticker' && url ? (
                    <img src={url} alt="sticker" className="size-32" loading="lazy" />
                ) : b.media.type === 'video' && url ? (
                    <video src={url} controls preload="metadata" className="max-h-72 rounded-md" />
                ) : b.media.type === 'audio' && url ? (
                    <audio src={url} controls preload="metadata" className="h-10 w-64 max-w-full" />
                ) : (
                    <a href={url ? `${url}${url.startsWith('/') ? '?download=1' : ''}` : undefined} target="_blank" rel="noreferrer" className={cn('flex items-center gap-2 rounded-md bg-black/5 px-2.5 py-2 text-sm', url && 'hover:bg-black/10')}>
                        <Icon className="size-4 shrink-0" />
                        <span className="min-w-0 flex-1 truncate">{label}</span>
                        {url && <Download className="size-3.5 shrink-0 opacity-60" />}
                        {b.media.pending && <Loader2 className="size-3.5 shrink-0 animate-spin opacity-60" />}
                    </a>
                )}
                {b.media.caption && <p className="text-sm whitespace-pre-wrap">{b.media.caption}</p>}
            </div>
        );
    }
    if (b.location) {
        return (
            <a className="flex items-center gap-2 text-sm underline-offset-2 hover:underline" target="_blank" rel="noreferrer" href={`https://maps.google.com/?q=${b.location.latitude},${b.location.longitude}`}>
                <MapPin className="size-4 shrink-0" /> {b.location.name || `${b.location.latitude}, ${b.location.longitude}`}
            </a>
        );
    }
    if (b.unsupported) {
        return (
            <div className="space-y-1">
                <p className="flex items-center gap-1 text-sm font-medium text-zinc-700">
                    <Ban className="size-4 shrink-0" /> Unsupported message{b.unsupported.kind ? ` · ${b.unsupported.kind}` : ''}
                </p>
                <p className="text-xs text-muted-foreground">{b.unsupported.hint}</p>
                {b.unsupported.code && <p className="text-[10px] text-muted-foreground">Meta #{b.unsupported.code}{b.unsupported.detail ? ` · ${b.unsupported.detail}` : ''}</p>}
            </div>
        );
    }
    if (b.template) {
        return (
            <div className="space-y-1">
                <p className="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-[#2b4a08] uppercase">
                    <LayoutTemplate className="size-3" /> Template · {b.template.name}
                </p>
                {b.template.header && <p className="text-sm font-semibold">{b.template.header}</p>}
                <p className="text-sm whitespace-pre-wrap">{b.template.body || m.preview}</p>
                {b.template.footer && <p className="text-xs text-muted-foreground">{b.template.footer}</p>}
                {b.template.buttons.length > 0 && (
                    <div className="mt-1 -mx-1 border-t pt-1">
                        {b.template.buttons.map((btn, i) => (
                            <div key={i} className="py-1 text-center text-sm font-medium text-[#027eb5]">
                                {btn.text}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    }
    return <p className="text-sm break-words whitespace-pre-wrap">{b.text ?? m.preview ?? `(${m.type})`}</p>;
}

export default function MessageBubble({ m, onReply }: { m: ChatMessage; onReply?: (m: ChatMessage) => void }) {
    const out = m.direction === 'outbound';

    return (
        <div className={cn('group flex w-full', out ? 'justify-end' : 'justify-start')}>
            <div
                className={cn(
                    'relative max-w-[78%] rounded-lg px-3 py-1.5 shadow-sm md:max-w-[65%]',
                    out ? 'rounded-tr-none bg-[#d9fdd3]' : 'rounded-tl-none bg-white',
                    m.status === 'failed' && 'ring-1 ring-red-300',
                )}
                onDoubleClick={() => onReply?.(m)}
                title="Double-click to reply"
            >
                {m.context_wamid && <p className="mb-1 border-l-2 border-brand bg-black/5 px-2 py-1 text-[11px] text-muted-foreground">Reply</p>}
                {m.origin === 'broadcast' && (
                    <p className="mb-0.5 flex items-center gap-1 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                        <Megaphone className="size-3" /> Broadcast
                    </p>
                )}
                {m.origin === 'system' && <p className="mb-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">Auto-reply</p>}
                <Content m={m} />
                <div className="mt-0.5 flex items-center justify-end gap-1 text-[10px] text-muted-foreground">
                    <span>{formatTime(m.timestamp)}</span>
                    {out && <StatusTicks status={m.status} pending={m.pending} />}
                </div>
                {m.status === 'failed' && (
                    <p className="mt-1 rounded bg-red-50 px-2 py-1 text-[11px] text-red-700">
                        Not delivered{m.error_code ? ` (#${m.error_code})` : ''}: {m.error_message ?? 'unknown error'}
                    </p>
                )}
            </div>
        </div>
    );
}
