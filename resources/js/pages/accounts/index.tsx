import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { type AccountSummary } from '@/types/whatsapp';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, PlugZap } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Accounts', href: '/accounts' }];

export default function AccountsIndex({ accounts }: { accounts: AccountSummary[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="WhatsApp Business Accounts" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="WhatsApp Business Accounts"
                    description="Every WABA connected to this workspace with its phone numbers and onboarding state."
                    actions={
                        <Button asChild>
                            <Link href={route('onboarding.index')}>
                                <PlugZap /> Connect account
                            </Link>
                        </Button>
                    }
                />
                <FlashMessages />

                {accounts.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-sm text-muted-foreground">No accounts yet — start with Embedded Signup.</CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {accounts.map((a) => (
                            <Card key={a.id}>
                                <CardContent className="space-y-4 p-5">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h3 className="truncate text-base font-semibold">{a.name ?? 'Unnamed WABA'}</h3>
                                            <p className="font-mono text-xs text-muted-foreground">{a.waba_id}</p>
                                        </div>
                                        <StatusBadge status={a.status} />
                                    </div>
                                    <dl className="grid grid-cols-3 gap-2 text-xs">
                                        <div>
                                            <dt className="text-muted-foreground">Numbers</dt>
                                            <dd className="font-medium">{a.phone_numbers.length}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">Templates</dt>
                                            <dd className="font-medium">{a.templates_count ?? 0}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">Webhooks</dt>
                                            <dd className="font-medium">{a.webhook_subscribed ? 'Subscribed' : 'Not subscribed'}</dd>
                                        </div>
                                    </dl>
                                    <div className="space-y-1">
                                        {a.phone_numbers.map((p) => (
                                            <div key={p.id} className="flex items-center justify-between rounded-md bg-muted/50 px-3 py-1.5 text-sm">
                                                <span>
                                                    {p.display_phone_number} <span className="text-muted-foreground">· {p.verified_name ?? '—'}</span>
                                                </span>
                                                <StatusBadge status={p.is_registered ? 'active' : 'pending_setup'} />
                                            </div>
                                        ))}
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs text-muted-foreground">Connected {formatDate(a.created_at, false)}</span>
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={route('accounts.show', a.id)}>
                                                Manage <ArrowRight />
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
