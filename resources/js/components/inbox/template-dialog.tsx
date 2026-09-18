import Field from '@/components/field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import WhatsAppPreview from '@/components/whatsapp-preview';
import { countVariables, fillVariables } from '@/lib/format';
import { type InboxTemplate } from '@/types/whatsapp';
import { Loader2, Send } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

export type TemplateInput = {
    id: string;
    header: Record<string, string>;
    body: string[];
    buttons: { index: number; sub_type: string; payload?: string; text?: string; coupon_code?: string }[];
};

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    templates: InboxTemplate[];
    contactName: string;
    sending: boolean;
    error?: string | null;
    onSend: (input: TemplateInput) => Promise<void> | void;
}

export default function TemplateDialog({ open, onOpenChange, templates, contactName, sending, error, onSend }: Props) {
    const [input, setInput] = useState<TemplateInput>({ id: '', header: {}, body: [], buttons: [] });
    const template = templates.find((t) => String(t.id) === input.id);

    useEffect(() => {
        if (open && !input.id && templates[0]) select(String(templates[0].id));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, templates]);

    const header = template?.components.find((c) => c.type === 'HEADER');
    const body = template?.components.find((c) => c.type === 'BODY');
    const footer = template?.components.find((c) => c.type === 'FOOTER');
    const buttons = useMemo(() => template?.components.find((c) => c.type === 'BUTTONS')?.buttons ?? [], [template]);
    const isAuth = template?.category === 'AUTHENTICATION';
    const bodyVars = isAuth ? 1 : countVariables(body?.text);
    const headerVars = header?.format === 'TEXT' ? countVariables(header.text) : 0;

    function select(id: string) {
        const t = templates.find((x) => String(x.id) === id);
        const btns = (t?.components.find((c) => c.type === 'BUTTONS')?.buttons ?? [])
            .map((b, index) => ({ index, type: b.type, url: b.url }))
            .filter((b) => b.type === 'QUICK_REPLY' || (b.type === 'URL' && (b.url ?? '').includes('{{1}}')) || b.type === 'COPY_CODE')
            .map((b) => ({ index: b.index, sub_type: b.type.toLowerCase(), payload: '', text: '', coupon_code: '' }));
        setInput({ id, header: {}, body: [], buttons: btns });
    }

    const ready = !!template && (bodyVars === 0 || input.body.slice(0, bodyVars).every((v) => v && v.trim() !== '')) && (headerVars === 0 || !!input.header.text) && (!header || ['TEXT', 'LOCATION'].includes(header.format ?? 'TEXT') || !!(input.header.link || input.header.id));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Send a template to {contactName}</DialogTitle>
                    <DialogDescription>Only APPROVED templates can be sent. A delivered template opens the 24-hour window for free-form replies.</DialogDescription>
                </DialogHeader>

                {templates.length === 0 ? (
                    <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">No approved templates for this sender's account yet. Create one under Templates and wait for Meta's approval.</p>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2">
                        <div className="space-y-4">
                            <Field label="Template" required>
                                <Select value={input.id} onValueChange={select}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Choose a template" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {templates.map((t) => (
                                            <SelectItem key={t.id} value={String(t.id)}>
                                                {t.name} · {t.language} · {t.category}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>

                            {header && !['TEXT', 'LOCATION'].includes(header.format ?? 'TEXT') && (
                                <Field label={`${header.format} header`} hint="Public URL or an uploaded media ID." required>
                                    <Input placeholder="https://… or media ID" value={input.header.link ?? ''} onChange={(e) => setInput({ ...input, header: { ...input.header, link: e.target.value } })} />
                                </Field>
                            )}
                            {header?.format === 'LOCATION' && (
                                <div className="grid grid-cols-2 gap-2">
                                    {['latitude', 'longitude', 'name', 'address'].map((k) => (
                                        <Input key={k} placeholder={k} value={input.header[k] ?? ''} onChange={(e) => setInput({ ...input, header: { ...input.header, [k]: e.target.value } })} />
                                    ))}
                                </div>
                            )}
                            {headerVars > 0 && (
                                <Field label="Header variable {{1}}" required>
                                    <Input value={input.header.text ?? ''} onChange={(e) => setInput({ ...input, header: { ...input.header, text: e.target.value } })} />
                                </Field>
                            )}
                            {bodyVars > 0 && (
                                <div className="space-y-2">
                                    <p className="text-sm font-medium">{isAuth ? 'One-time password' : 'Body variables'}</p>
                                    {Array.from({ length: bodyVars }).map((_, i) => (
                                        <div key={i} className="flex items-center gap-2">
                                            <span className="w-12 shrink-0 font-mono text-xs text-muted-foreground">{`{{${i + 1}}}`}</span>
                                            <Input
                                                value={input.body[i] ?? ''}
                                                onChange={(e) => {
                                                    const next = [...input.body];
                                                    next[i] = e.target.value;
                                                    setInput({ ...input, body: next });
                                                }}
                                            />
                                        </div>
                                    ))}
                                </div>
                            )}
                            {!isAuth && input.buttons.length > 0 && (
                                <div className="space-y-2">
                                    <p className="text-sm font-medium">Button parameters</p>
                                    {input.buttons.map((b, i) => (
                                        <div key={i} className="flex items-center gap-2">
                                            <span className="w-24 shrink-0 text-xs text-muted-foreground">
                                                #{b.index} {b.sub_type}
                                            </span>
                                            <Input
                                                placeholder={b.sub_type === 'quick_reply' ? 'payload' : b.sub_type === 'url' ? 'URL suffix {{1}}' : 'coupon code'}
                                                value={b.sub_type === 'quick_reply' ? (b.payload ?? '') : b.sub_type === 'url' ? (b.text ?? '') : (b.coupon_code ?? '')}
                                                onChange={(e) => {
                                                    const key = b.sub_type === 'quick_reply' ? 'payload' : b.sub_type === 'url' ? 'text' : 'coupon_code';
                                                    const next = [...input.buttons];
                                                    next[i] = { ...b, [key]: e.target.value };
                                                    setInput({ ...input, buttons: next });
                                                }}
                                            />
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div>
                            <WhatsAppPreview
                                header={header ? { format: header.format ?? 'TEXT', text: fillVariables(header.text ?? '', [input.header.text ?? '']) } : null}
                                body={isAuth ? `${input.body[0] || '{{1}}'} is your verification code.` : fillVariables(body?.text ?? '', input.body)}
                                footer={footer?.text}
                                buttons={buttons}
                            />
                        </div>
                    </div>
                )}

                {error && <p className="rounded-md border border-red-200 bg-red-50 p-2 text-sm text-red-700">{error}</p>}

                <DialogFooter>
                    <Button variant="outline" type="button" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="button" disabled={!ready || sending} onClick={() => onSend(input)}>
                        {sending ? <Loader2 className="animate-spin" /> : <Send />} Send template
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
