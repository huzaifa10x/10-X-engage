import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { type SegmentRow } from '@/types/whatsapp';
import { Head, Link, router } from '@inertiajs/react';
import { Megaphone, Pencil, Plus, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Broadcasts', href: '/broadcasts' },
    { title: 'Segments', href: '/segments' },
];

export default function SegmentsIndex({ segments, total_contacts }: { segments: SegmentRow[]; total_contacts: number }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Segments" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Segments"
                    description={`Saved audience rules evaluated live against your ${total_contacts} contacts. Membership updates automatically when a contact changes.`}
                    actions={
                        <Button asChild>
                            <Link href={route('segments.create')}>
                                <Plus /> New segment
                            </Link>
                        </Button>
                    }
                />
                <FlashMessages />
                <Card>
                    <CardContent className="p-4">
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="py-2 pr-3">Name</th>
                                    <th className="py-2 pr-3">Rules</th>
                                    <th className="py-2 pr-3">Contacts now</th>
                                    <th className="py-2 pr-3">Updated</th>
                                    <th className="py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {segments.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="py-10 text-center text-muted-foreground">
                                            No segments yet. A broadcast can also target all contacts without one.
                                        </td>
                                    </tr>
                                )}
                                {segments.map((s) => (
                                    <tr key={s.id} className="hover:bg-muted/40">
                                        <td className="py-2.5 pr-3">
                                            <p className="font-medium">{s.name}</p>
                                            {s.description && <p className="text-xs text-muted-foreground">{s.description}</p>}
                                        </td>
                                        <td className="py-2.5 pr-3 text-muted-foreground">{s.rules_count}</td>
                                        <td className="py-2.5 pr-3 font-semibold">{s.count}</td>
                                        <td className="py-2.5 pr-3 text-muted-foreground">{formatDate(s.updated_at)}</td>
                                        <td className="py-2.5">
                                            <div className="flex justify-end gap-1">
                                                <Button asChild size="sm" variant="ghost" title="New broadcast to this segment">
                                                    <Link href={route('broadcasts.create', { segment: s.id })}>
                                                        <Megaphone />
                                                    </Link>
                                                </Button>
                                                <Button asChild size="sm" variant="ghost">
                                                    <Link href={route('segments.edit', s.id)}>
                                                        <Pencil />
                                                    </Link>
                                                </Button>
                                                <Button size="sm" variant="ghost" onClick={() => confirm(`Delete segment ${s.name}?`) && router.delete(route('segments.destroy', s.id))}>
                                                    <Trash2 className="text-red-500" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
