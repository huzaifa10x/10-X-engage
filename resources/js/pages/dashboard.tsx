import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { timeAgo } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Building2, LayoutTemplate, MessageSquareText, PlugZap, Smartphone } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface Props {
    stats: Record<string, number>;
    recent_messages: { id: number; direction: string; type: string; status: string; to: string | null; from: string | null; preview: string | null; created_at: string | null }[];
    accounts: { id: number; waba_id: string; name: string | null; status: string; onboarding_step: string; phone_numbers_count: number }[];
}

export default function Dashboard({ stats, recent_messages, accounts }: Props) {
    const cards = [
        { label: 'Accounts', value: stats.accounts, sub: `${stats.active_accounts} active`, icon: Building2, href: route('accounts.index') },
        { label: 'Phone numbers', value: stats.phone_numbers, sub: `${stats.registered_numbers} registered`, icon: Smartphone, href: route('accounts.index') },
        { label: 'Templates', value: stats.templates, sub: `${stats.approved_templates} approved`, icon: LayoutTemplate, href: route('templates.index') },
        { label: 'Messages sent', value: stats.messages_sent, sub: `${stats.messages_failed} failed · ${stats.messages_received} received`, icon: MessageSquareText, href: route('messages.index') },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Overview"
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={route('onboarding.index')}>
                                    <PlugZap /> Connect account
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={route('messages.create')}>
                                    <MessageSquareText /> Send message
                                </Link>
                            </Button>
                        </>
                    }
                />
                <FlashMessages />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((c) => (
                        <Link key={c.label} href={c.href}>
                            <Card className="transition-colors hover:border-brand">
                                <CardContent className="flex items-center gap-4 p-5">
                                    <div className="flex size-11 items-center justify-center rounded-lg bg-brand-soft text-brand-dark">
                                        <c.icon className="size-5" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground uppercase">{c.label}</p>
                                        <p className="text-2xl font-semibold leading-tight">{c.value}</p>
                                        <p className="text-xs text-muted-foreground">{c.sub}</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Recent messages</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {recent_messages.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Nothing sent or received yet.</p>
                            ) : (
                                <div className="divide-y">
                                    {recent_messages.map((m) => (
                                        <Link key={m.id} href={route('messages.show', m.id)} className="flex items-center gap-3 py-2.5 hover:bg-muted/40">
                                            {m.direction === 'outbound' ? <ArrowUpRight className="size-4 shrink-0 text-brand-dark" /> : <ArrowDownLeft className="size-4 shrink-0 text-violet-600" />}
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm">{m.preview || m.type}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    +{m.direction === 'outbound' ? m.to : m.from} · {timeAgo(m.created_at)}
                                                </p>
                                            </div>
                                            <StatusBadge status={m.status} />
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Accounts</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {accounts.length === 0 ? (
                                <div className="space-y-3 text-sm text-muted-foreground">
                                    <p>No WhatsApp Business Account connected yet.</p>
                                    <Button asChild size="sm">
                                        <Link href={route('onboarding.index')}>Start Embedded Signup</Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {accounts.map((a) => (
                                        <Link key={a.id} href={route('accounts.show', a.id)} className="flex items-center gap-3 py-2.5 hover:bg-muted/40">
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">{a.name ?? a.waba_id}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {a.phone_numbers_count} number(s) · {a.onboarding_step.replace(/_/g, ' ')}
                                                </p>
                                            </div>
                                            <StatusBadge status={a.status} />
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
