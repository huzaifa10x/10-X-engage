import { cn } from '@/lib/utils';
import { ExternalLink, FileText, Image as ImageIcon, MapPin, Phone, Reply, Video } from 'lucide-react';
import { ReactNode } from 'react';

/** Renders a WhatsApp-style bubble used by the template builder and composer. */
export default function WhatsAppPreview({
    header,
    body,
    footer,
    buttons = [],
    className,
}: {
    header?: { format: string; text?: string } | null;
    body?: string;
    footer?: string;
    buttons?: { type: string; text?: string }[];
    className?: string;
}) {
    const headerIcon: Record<string, ReactNode> = {
        IMAGE: <ImageIcon className="size-8 text-gray-400" />,
        VIDEO: <Video className="size-8 text-gray-400" />,
        DOCUMENT: <FileText className="size-8 text-gray-400" />,
        LOCATION: <MapPin className="size-8 text-gray-400" />,
    };

    return (
        <div className={cn('rounded-xl bg-[#e5ddd5] p-4', className)}>
            <div className="max-w-xs rounded-lg bg-white shadow-sm">
                {header && header.format !== 'NONE' && (
                    <div className="border-b border-gray-100 p-2">
                        {header.format === 'TEXT' ? (
                            <p className="px-1 text-sm font-semibold break-words text-gray-900">{header.text || 'Header'}</p>
                        ) : (
                            <div className="flex h-28 items-center justify-center rounded-md bg-gray-100">{headerIcon[header.format]}</div>
                        )}
                    </div>
                )}
                <div className="space-y-1 px-3 py-2">
                    <p className="text-sm break-words whitespace-pre-wrap text-gray-900">{body || 'Message body…'}</p>
                    {footer && <p className="text-xs text-gray-500">{footer}</p>}
                    <p className="text-right text-[10px] text-gray-400">12:00</p>
                </div>
                {buttons.length > 0 && (
                    <div className="divide-y divide-gray-100 border-t border-gray-100">
                        {buttons.map((b, i) => (
                            <div key={i} className="flex items-center justify-center gap-1.5 py-2 text-sm font-medium text-[#0a7cd6]">
                                {b.type === 'URL' && <ExternalLink className="size-3.5" />}
                                {b.type === 'PHONE_NUMBER' && <Phone className="size-3.5" />}
                                {b.type === 'QUICK_REPLY' && <Reply className="size-3.5" />}
                                {b.text || b.type.replace('_', ' ')}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
