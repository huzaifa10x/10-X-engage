import { cn } from '@/lib/utils';

const styles: Record<string, string> = {
    draft: 'bg-zinc-100 text-zinc-700',
    scheduled: 'bg-blue-50 text-blue-700',
    queued: 'bg-amber-50 text-amber-700',
    sending: 'bg-amber-50 text-amber-700',
    completed: 'bg-brand-soft text-[#2b4a08]',
    cancelled: 'bg-zinc-100 text-zinc-500',
    failed: 'bg-red-50 text-red-700',
};

export default function BroadcastStatus({ status, className }: { status: string; className?: string }) {
    return <span className={cn('inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize', styles[status] ?? styles.draft, className)}>{status}</span>;
}
