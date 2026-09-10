import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { type TemplateRow } from '@/types/whatsapp';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, RefreshCw } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Templates', href: '/templates' }];

interface Props {
    templates: Paginated<TemplateRow>;
    accounts: { id: number; waba_id: string; name: string | null }[];
    filters: { status?: string; category?: string; account?: string };
}

export default function TemplatesIndex({ templates, accounts, filters }: Props) {
    const apply = (next: Partial<Props['filters']>) => router.get(route('templates.index'), { ...filters, ...next }, { preserveState: true, replace: true });
    const syncAccount = filters.account ?? (accounts[0] ? String(accounts[0].id) : '');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Templates" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Message templates"
                    description="Templates are required for business-initiated messages. Only APPROVED templates can be sent."
                    actions={
                        <>
                            {syncAccount && (
                                <Button variant="outline" onClick={() => router.post(route('accounts.templates.sync', syncAccount), {}, { preserveScroll: true })}>
                                    <RefreshCw /> Sync from Meta
                                </Button>
                            )}
                            <Button asChild>
                                <Link href={route('templates.create')}>
                                    <Plus /> New template
                                </Link>
                            </Button>
                        </>
                    }
                />
                <FlashMessages />

                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-wrap gap-2">
                            <Select value={filters.account ?? 'all'} onValueChange={(v) => apply({ account: v === 'all' ? undefined : v })}>
                                <SelectTrigger className="w-56">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All accounts</SelectItem>
                                    {accounts.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.name ?? a.waba_id}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={filters.status ?? 'all'} onValueChange={(v) => apply({ status: v === 'all' ? undefined : v })}>
                                <SelectTrigger className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All statuses</SelectItem>
                                    {['APPROVED', 'PENDING', 'REJECTED', 'PAUSED', 'DISABLED'].map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={filters.category ?? 'all'} onValueChange={(v) => apply({ category: v === 'all' ? undefined : v })}>
                                <SelectTrigger className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All categories</SelectItem>
                                    {['MARKETING', 'UTILITY', 'AUTHENTICATION'].map((c) => (
                                        <SelectItem key={c} value={c}>
                                            {c}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="py-2 pr-3">Name</th>
                                        <th className="py-2 pr-3">Language</th>
                                        <th className="py-2 pr-3">Category</th>
                                        <th className="py-2 pr-3">Status</th>
                                        <th className="py-2 pr-3">Body</th>
                                        <th className="py-2 pr-3">Account</th>
                                        <th className="py-2">Updated</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {templates.data.length === 0 && (
                                        <tr>
                                            <td colSpan={7} className="py-10 text-center text-muted-foreground">
                                                No templates yet. Create one or sync from Meta.
                                            </td>
                                        </tr>
                                    )}
                                    {templates.data.map((t) => (
                                        <tr key={t.id} className="cursor-pointer hover:bg-muted/40" onClick={() => router.visit(route('templates.show', t.id))}>
                                            <td className="py-2 pr-3 font-medium">{t.name}</td>
                                            <td className="py-2 pr-3">{t.language}</td>
                                            <td className="py-2 pr-3 text-xs">{t.category}</td>
                                            <td className="py-2 pr-3">
                                                <StatusBadge status={t.status} />
                                            </td>
                                            <td className="max-w-md truncate py-2 pr-3 text-muted-foreground" title={t.body ?? ''}>
                                                {t.body}
                                            </td>
                                            <td className="py-2 pr-3 whitespace-nowrap text-muted-foreground">{t.account?.name ?? t.account?.waba_id}</td>
                                            <td className="py-2 whitespace-nowrap text-muted-foreground">{formatDate(t.updated_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination paginator={templates} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
