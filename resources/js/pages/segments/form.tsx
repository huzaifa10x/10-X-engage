import Field from '@/components/field';
import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import SegmentRuleBuilder, { type SegmentSchema } from '@/components/segment-rule-builder';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { http } from '@/lib/http';
import { type BreadcrumbItem } from '@/types';
import { type SegmentRule, type SegmentRow } from '@/types/whatsapp';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2, Save } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface Props {
    segment: (SegmentRow & { rules: SegmentRule[]; match: 'all' | 'any'; description: string | null }) | null;
    schema: SegmentSchema;
}

export default function SegmentForm({ segment, schema }: Props) {
    const editing = !!segment;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Segments', href: '/segments' },
        { title: editing ? segment.name : 'New segment', href: '#' },
    ];
    const form = useForm<{ name: string; description: string; match: 'all' | 'any'; rules: SegmentRule[] }>({
        name: segment?.name ?? '',
        description: segment?.description ?? '',
        match: segment?.match ?? 'all',
        rules: segment?.rules?.length ? segment.rules : [{ field: 'tags', op: 'contains', value: '' }],
    });
    const { data, setData, errors, processing } = form;
    const [count, setCount] = useState<{ match: number; eligible: number } | null>(null);

    useEffect(() => {
        const id = window.setTimeout(async () => {
            const c = await http<{ match: number; eligible: number }>(route('segments.count'), { method: 'POST', json: { rules: data.rules, match: data.match } }).catch(() => null);
            setCount(c);
        }, 300);
        return () => window.clearTimeout(id);
    }, [data.rules, data.match]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (editing) form.put(route('segments.update', segment.id));
        else form.post(route('segments.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? segment.name : 'New segment'} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title={editing ? segment.name : 'New segment'} description="A segment is a saved rule, not a list — contacts move in and out automatically." />
                <FlashMessages />
                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Rules</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Field label="Name" error={errors.name} required>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="VIP members" />
                            </Field>
                            <Field label="Description" error={errors.description}>
                                <Input value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            </Field>
                            <SegmentRuleBuilder rules={data.rules} match={data.match} schema={schema} onChange={(rules, match) => form.setData({ ...data, rules, match })} />
                            {errors.rules && <p className="text-sm text-red-600">{errors.rules}</p>}
                        </CardContent>
                    </Card>
                    <div className="space-y-4">
                        <Card>
                            <CardContent className="p-5">
                                <p className="text-xs text-muted-foreground uppercase">Contacts matching now</p>
                                <p className="text-3xl font-semibold">{count ? count.match : '—'}</p>
                                {count && <p className="text-xs text-muted-foreground">{count.eligible} eligible for broadcasts (not opted out / suppressed)</p>}
                            </CardContent>
                        </Card>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={processing} className="flex-1">
                                {processing ? <Loader2 className="animate-spin" /> : <Save />} {editing ? 'Save' : 'Create segment'}
                            </Button>
                            <Button asChild type="button" variant="outline">
                                <Link href={route('segments.index')}>Cancel</Link>
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
