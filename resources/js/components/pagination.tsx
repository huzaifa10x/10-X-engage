import { type Paginated } from '@/types';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export default function Pagination({ paginator }: { paginator: Paginated<unknown> }) {
    if (paginator.last_page <= 1) return null;

    return (
        <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
            <p className="text-muted-foreground">
                {paginator.from}–{paginator.to} of {paginator.total}
            </p>
            <div className="flex flex-wrap gap-1">
                {paginator.links.map((l, i) => (
                    <Link
                        key={i}
                        href={l.url ?? '#'}
                        preserveState
                        className={cn(
                            'rounded-md border px-2.5 py-1 text-xs',
                            l.active && 'border-brand bg-brand text-[#14200a] font-semibold',
                            !l.url && 'pointer-events-none opacity-40',
                        )}
                        dangerouslySetInnerHTML={{ __html: l.label }}
                    />
                ))}
            </div>
        </div>
    );
}
