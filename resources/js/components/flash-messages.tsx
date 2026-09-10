import { useFlash } from '@/hooks/use-flash';
import { cn } from '@/lib/utils';
import { AlertTriangle, CheckCircle2, X, XCircle } from 'lucide-react';

export default function FlashMessages({ className }: { className?: string }) {
    const { flash, dismiss } = useFlash();

    if (!flash?.success && !flash?.error && !flash?.warning) return null;

    const items = [
        flash.success && { tone: 'success', text: flash.success, Icon: CheckCircle2 },
        flash.warning && { tone: 'warning', text: flash.warning, Icon: AlertTriangle },
        flash.error && { tone: 'error', text: flash.error, Icon: XCircle },
    ].filter(Boolean) as { tone: string; text: string; Icon: typeof CheckCircle2 }[];

    return (
        <div className={cn('space-y-2', className)}>
            {items.map(({ tone, text, Icon }) => (
                <div
                    key={tone}
                    className={cn(
                        'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm',
                        tone === 'success' && 'border-brand/60 bg-brand-soft text-[#2b4a08]',
                        tone === 'warning' && 'border-amber-300 bg-amber-50 text-amber-900',
                        tone === 'error' && 'border-red-300 bg-red-50 text-red-800',
                    )}
                >
                    <Icon className="mt-0.5 size-4 shrink-0" />
                    <p className="flex-1 leading-relaxed break-words">{text}</p>
                    <button type="button" onClick={dismiss} className="opacity-60 hover:opacity-100" aria-label="Dismiss">
                        <X className="size-4" />
                    </button>
                </div>
            ))}
        </div>
    );
}
