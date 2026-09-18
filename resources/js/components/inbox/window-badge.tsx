import { formatCountdown } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type WindowState } from '@/types/whatsapp';
import { Lock, Unlock } from 'lucide-react';

/** Compact 24-hour window indicator used in the list, header and contacts table. */
export default function WindowBadge({ window, secondsLeft, compact = false, className }: { window: WindowState; secondsLeft?: number; compact?: boolean; className?: string }) {
    const left = secondsLeft ?? window.seconds_left;
    const open = window.open && left > 0;

    if (compact) {
        return <span className={cn('inline-block size-2 rounded-full', open ? 'bg-brand' : 'bg-zinc-300', className)} title={open ? `Window open · ${formatCountdown(left)} left` : 'Window closed'} />;
    }

    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium', open ? 'border-brand bg-brand-soft text-[#2b4a08]' : 'border-zinc-200 bg-zinc-50 text-zinc-600', className)}>
            {open ? <Unlock className="size-3" /> : <Lock className="size-3" />}
            {open ? `Window open · ${formatCountdown(left)} left` : window.has_history ? '24-hour window closed' : 'No conversation yet'}
        </span>
    );
}
