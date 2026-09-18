import StatusTicks from '@/components/inbox/status-ticks';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type ChatMessage } from '@/types/whatsapp';
import { FileText, Image as ImageIcon, LayoutTemplate, MapPin, Mic, Video } from 'lucide-react';

const mediaIcon: Record<string, typeof ImageIcon> = { image: ImageIcon, video: Video, document: FileText, audio: Mic, sticker: ImageIcon };

function Content({ m }: { m: ChatMessage }) {
    const b = m.body ?? {};

    if (b.media) {
        const Icon = mediaIcon[b.media.type] ?? FileText;
        return (
            <div className="space-y-1">
                {b.media.type === 'image' && b.media.link ? (
                    <img src={b.media.link} alt={b.media.caption ?? ''} className="max-h-72 rounded-md" />
                ) : (
                    <div className="flex items-center gap-2 rounded-md bg-black/5 px-2.5 py-2 text-sm">
                        <Icon className="size-4 shrink-0" />
                        <span className="truncate">{b.media.filename ?? b.media.link ?? `${b.media.type}${b.media.id ? ` · ${b.media.id}` : ''}`}</span>
                    </div>
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
    if (b.template) {
        return (
            <div className="space-y-1">
                <p className="flex items-center gap-1 text-[11px] font-semibold tracking-wide text-[#2b4a08] uppercase">
                    <LayoutTemplate className="size-3" /> Template · {b.template.name}
                </p>
                <p className="text-sm whitespace-pre-wrap">{b.template.text?.replace(/^Template [^:]+: /, '')}</p>
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
