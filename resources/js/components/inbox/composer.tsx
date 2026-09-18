import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { formatCountdown } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type ChatMessage, type WindowState } from '@/types/whatsapp';
import { LayoutTemplate, Loader2, Lock, Paperclip, Send, X } from 'lucide-react';
import { KeyboardEvent, useRef, useState } from 'react';

interface Props {
    window: WindowState;
    secondsLeft: number;
    sending: boolean;
    replyTo: ChatMessage | null;
    onCancelReply: () => void;
    onSendText: (body: string) => Promise<void> | void;
    onSendFile: (file: File) => Promise<void> | void;
    onOpenTemplates: () => void;
    hasTemplates: boolean;
}

export default function Composer({ window: w, secondsLeft, sending, replyTo, onCancelReply, onSendText, onSendFile, onOpenTemplates, hasTemplates }: Props) {
    const [text, setText] = useState('');
    const fileRef = useRef<HTMLInputElement>(null);
    const open = w.open && secondsLeft > 0;

    const submit = async () => {
        const body = text.trim();
        if (!body || sending) return;
        setText('');
        await onSendText(body);
    };

    const onKey = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submit();
        }
    };

    if (!open) {
        return (
            <div className="border-t bg-white p-3">
                <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed bg-zinc-50 px-4 py-4 text-center">
                    <p className="flex items-center gap-2 text-sm font-medium text-zinc-800">
                        <Lock className="size-4" />
                        {w.has_history ? '24-hour window has been closed.' : 'No conversation window yet.'}
                    </p>
                    <p className="max-w-md text-xs text-muted-foreground">
                        {w.has_history
                            ? 'More than 24 hours have passed since the last customer message or template. Send an approved template to reopen the conversation.'
                            : 'The first message to a new contact must be an approved template. Once it is sent (or the customer messages you), free-form replies unlock for 24 hours.'}
                    </p>
                    <Button type="button" onClick={onOpenTemplates} disabled={!hasTemplates}>
                        <LayoutTemplate /> {hasTemplates ? 'Send a template' : 'No approved templates yet'}
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="border-t bg-white">
            {replyTo && (
                <div className="flex items-center justify-between gap-2 border-b bg-zinc-50 px-3 py-1.5 text-xs">
                    <p className="truncate border-l-2 border-brand pl-2 text-muted-foreground">Replying to: {replyTo.preview}</p>
                    <button type="button" onClick={onCancelReply} className="rounded p-1 hover:bg-zinc-200">
                        <X className="size-3.5" />
                    </button>
                </div>
            )}
            <div className="flex items-end gap-2 p-2.5">
                <Button type="button" variant="ghost" size="icon" title="Send a template" onClick={onOpenTemplates}>
                    <LayoutTemplate />
                </Button>
                <Button type="button" variant="ghost" size="icon" title="Attach image, video, audio or document" onClick={() => fileRef.current?.click()} disabled={sending}>
                    <Paperclip />
                </Button>
                <input ref={fileRef} type="file" className="hidden" accept="image/jpeg,image/png,video/mp4,audio/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" onChange={(e) => e.target.files?.[0] && onSendFile(e.target.files[0])} />
                <Textarea
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    onKeyDown={onKey}
                    rows={1}
                    placeholder="Type a message — Enter to send, Shift+Enter for a new line"
                    className="max-h-40 min-h-10 flex-1 resize-none bg-zinc-50"
                />
                <Button type="button" onClick={submit} disabled={!text.trim() || sending} className={cn('shrink-0')}>
                    {sending ? <Loader2 className="animate-spin" /> : <Send />}
                </Button>
            </div>
            <p className="px-3 pb-2 text-[11px] text-muted-foreground">
                Window open — free-form messages allowed for another {formatCountdown(secondsLeft)} ({w.opened_by === 'template' ? 'opened by template' : 'opened by customer message'}).
            </p>
        </div>
    );
}
