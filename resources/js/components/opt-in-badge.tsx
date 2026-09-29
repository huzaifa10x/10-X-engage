import { cn } from '@/lib/utils';
import { CheckCircle2, HelpCircle, XCircle } from 'lucide-react';

export default function OptInBadge({ status, className }: { status: 'unknown' | 'opted_in' | 'opted_out'; className?: string }) {
    const map = {
        opted_in: { label: 'Opted in', icon: CheckCircle2, cls: 'border-brand bg-brand-soft text-[#2b4a08]' },
        opted_out: { label: 'Opted out', icon: XCircle, cls: 'border-red-200 bg-red-50 text-red-700' },
        unknown: { label: 'Unknown', icon: HelpCircle, cls: 'border-zinc-200 bg-zinc-50 text-zinc-600' },
    } as const;
    const m = map[status] ?? map.unknown;
    return (
        <span className={cn('inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium', m.cls, className)}>
            <m.icon className="size-3" /> {m.label}
        </span>
    );
}
