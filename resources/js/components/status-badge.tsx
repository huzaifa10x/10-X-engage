import { cn } from '@/lib/utils';

const tones: Record<string, string> = {
    // generic / account
    active: 'bg-brand-soft text-[#2b4a08] border-brand/60',
    pending_setup: 'bg-amber-50 text-amber-800 border-amber-200',
    disabled: 'bg-red-50 text-red-700 border-red-200',
    disconnected: 'bg-gray-100 text-gray-600 border-gray-200',
    // messages
    pending: 'bg-gray-100 text-gray-700 border-gray-200',
    accepted: 'bg-sky-50 text-sky-700 border-sky-200',
    sent: 'bg-sky-50 text-sky-700 border-sky-200',
    delivered: 'bg-brand-soft text-[#2b4a08] border-brand/60',
    read: 'bg-brand text-[#14200a] border-brand',
    failed: 'bg-red-50 text-red-700 border-red-200',
    deleted: 'bg-gray-100 text-gray-600 border-gray-200',
    received: 'bg-violet-50 text-violet-700 border-violet-200',
    // templates
    APPROVED: 'bg-brand-soft text-[#2b4a08] border-brand/60',
    PENDING: 'bg-amber-50 text-amber-800 border-amber-200',
    REJECTED: 'bg-red-50 text-red-700 border-red-200',
    PAUSED: 'bg-orange-50 text-orange-700 border-orange-200',
    DISABLED: 'bg-red-50 text-red-700 border-red-200',
    IN_APPEAL: 'bg-violet-50 text-violet-700 border-violet-200',
    PENDING_DELETION: 'bg-gray-100 text-gray-600 border-gray-200',
    DELETED: 'bg-gray-100 text-gray-600 border-gray-200',
    FLAGGED: 'bg-orange-50 text-orange-700 border-orange-200',
    // quality
    GREEN: 'bg-brand-soft text-[#2b4a08] border-brand/60',
    YELLOW: 'bg-amber-50 text-amber-800 border-amber-200',
    RED: 'bg-red-50 text-red-700 border-red-200',
    VERIFIED: 'bg-brand-soft text-[#2b4a08] border-brand/60',
    NOT_VERIFIED: 'bg-amber-50 text-amber-800 border-amber-200',
    EXPIRED: 'bg-red-50 text-red-700 border-red-200',
};

export default function StatusBadge({ status, className }: { status?: string | null; className?: string }) {
    if (!status) return <span className="text-xs text-muted-foreground">—</span>;
    const tone = tones[status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
    return (
        <span className={cn('inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold tracking-wide uppercase', tone, className)}>
            {status.replace(/_/g, ' ')}
        </span>
    );
}
