import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { type SegmentRule } from '@/types/whatsapp';
import { Plus, Trash2 } from 'lucide-react';

export interface SegmentSchema {
    fields: Record<string, string>;
    ops: Record<string, string[]>;
    attribute_keys: string[];
    tags: string[];
    sources: string[];
    opt_in_statuses: string[];
}

const FIELD_LABELS: Record<string, string> = {
    name: 'Name', email: 'Email', company: 'Company', phone: 'Phone', tags: 'Tags', source: 'Source', opt_in_status: 'Consent', window: '24h window',
    created_at: 'Added', last_activity_at: 'Last activity', last_message_at: 'Last message', last_inbound_at: 'Last customer message', unread_count: 'Unread count',
};
const OP_LABELS: Record<string, string> = {
    eq: 'is', neq: 'is not', contains: 'contains', not_contains: "doesn't contain", starts_with: 'starts with', is_empty: 'is empty', is_not_empty: 'is not empty',
    within_days: 'within the last (days)', older_than_days: 'older than (days)', gt: 'greater than', lt: 'less than',
};

interface Props {
    rules: SegmentRule[];
    match: 'all' | 'any';
    schema: SegmentSchema;
    onChange: (rules: SegmentRule[], match: 'all' | 'any') => void;
    className?: string;
}

export default function SegmentRuleBuilder({ rules, match, schema, onChange, className }: Props) {
    const fieldType = (f: string) => (f.startsWith('custom.') ? 'text' : (schema.fields[f] ?? 'text'));
    const update = (i: number, patch: Partial<SegmentRule>) => {
        const next = rules.map((r, j) => (j === i ? { ...r, ...patch } : r));
        if (patch.field) {
            const t = fieldType(patch.field);
            next[i].op = schema.ops[t]?.[0] ?? 'eq';
            next[i].value = '';
        }
        onChange(next, match);
    };

    const valueInput = (r: SegmentRule, i: number) => {
        const t = fieldType(r.field);
        if (['is_empty', 'is_not_empty'].includes(r.op)) return null;
        if (r.field === 'source') return enumSelect(r, i, schema.sources);
        if (r.field === 'opt_in_status') return enumSelect(r, i, schema.opt_in_statuses);
        if (r.field === 'window') return enumSelect(r, i, ['open', 'closed']);
        if (t === 'tags' && schema.tags.length > 0) return enumSelect(r, i, schema.tags, 'Pick a tag');
        if (t === 'days' || t === 'number') return <Input type="number" min={0} className="w-28" value={r.value} onChange={(e) => update(i, { value: e.target.value })} />;
        return <Input value={r.value} onChange={(e) => update(i, { value: e.target.value })} placeholder="value" />;
    };

    const enumSelect = (r: SegmentRule, i: number, options: string[], placeholder = 'Choose') => (
        <Select value={r.value || ''} onValueChange={(v) => update(i, { value: v })}>
            <SelectTrigger className="w-48">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((o) => (
                    <SelectItem key={o} value={o}>
                        {o.replace(/_/g, ' ')}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <div className={cn('space-y-3', className)}>
            <div className="flex items-center gap-2 text-sm">
                Contacts matching
                <Select value={match} onValueChange={(v) => onChange(rules, v as 'all' | 'any')}>
                    <SelectTrigger className="h-8 w-24">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">all</SelectItem>
                        <SelectItem value="any">any</SelectItem>
                    </SelectContent>
                </Select>
                of these rules:
            </div>
            {rules.map((r, i) => (
                <div key={i} className="flex flex-wrap items-center gap-2 rounded-md border p-2">
                    <Select value={r.field} onValueChange={(v) => update(i, { field: v })}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Field" />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.keys(schema.fields).map((f) => (
                                <SelectItem key={f} value={f}>
                                    {FIELD_LABELS[f] ?? f}
                                </SelectItem>
                            ))}
                            {schema.attribute_keys.map((k) => (
                                <SelectItem key={`custom.${k}`} value={`custom.${k}`}>
                                    Custom · {k}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={r.op} onValueChange={(v) => update(i, { op: v })}>
                        <SelectTrigger className="w-44">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {(schema.ops[fieldType(r.field)] ?? []).map((o) => (
                                <SelectItem key={o} value={o}>
                                    {OP_LABELS[o] ?? o}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {valueInput(r, i)}
                    <Button type="button" variant="ghost" size="icon" className="ml-auto" onClick={() => onChange(rules.filter((_, j) => j !== i), match)}>
                        <Trash2 className="text-red-500" />
                    </Button>
                </div>
            ))}
            <Button type="button" variant="outline" size="sm" onClick={() => onChange([...rules, { field: 'tags', op: 'contains', value: '' }], match)}>
                <Plus /> Add rule
            </Button>
        </div>
    );
}
