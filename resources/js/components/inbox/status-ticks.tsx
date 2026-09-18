import { cn } from '@/lib/utils';
import { AlertCircle, Check, CheckCheck, Clock } from 'lucide-react';

/** WhatsApp-style delivery ticks for outbound messages. */
export default function StatusTicks({ status, pending, className }: { status: string; pending?: boolean; className?: string }) {
    if (pending || status === 'pending') return <Clock className={cn('size-3.5 text-muted-foreground', className)} />;
    if (status === 'failed') return <AlertCircle className={cn('size-3.5 text-red-500', className)} />;
    if (status === 'read') return <CheckCheck className={cn('size-3.5 text-[#53bdeb]', className)} />;
    if (status === 'delivered') return <CheckCheck className={cn('size-3.5 text-muted-foreground', className)} />;
    return <Check className={cn('size-3.5 text-muted-foreground', className)} />;
}
