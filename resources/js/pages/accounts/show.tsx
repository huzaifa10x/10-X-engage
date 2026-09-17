import Field from '@/components/field';
import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { type AccountDetail, type PhoneNumberSummary } from '@/types/whatsapp';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Circle, CreditCard, KeyRound, LayoutTemplate, Loader2, MessageSquareText, RefreshCw, ShieldCheck, Smartphone, Webhook } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Props {
    account: AccountDetail;
    partner_type: string;
    webhook_url: string;
    webhook_fields: string[];
}

export default function AccountShow({ account, partner_type, webhook_url, webhook_fields }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Accounts', href: '/accounts' },
        { title: account.name ?? account.waba_id, href: route('accounts.show', account.id) },
    ];

    const [syncing, setSyncing] = useState(false);
    const sync = () => router.post(route('accounts.sync', account.id), {}, { preserveScroll: true, onStart: () => setSyncing(true), onFinish: () => setSyncing(false) });

    const registered = account.phone_numbers.some((p) => p.is_registered);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={account.name ?? account.waba_id} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={account.name ?? 'WhatsApp Business Account'}
                    description={`WABA ${account.waba_id}${account.owner_business_name ? ` · owned by ${account.owner_business_name}` : ''}`}
                    actions={
                        <>
                            <Button variant="outline" onClick={sync} disabled={syncing}>
                                {syncing ? <Loader2 className="animate-spin" /> : <RefreshCw />} Sync with Meta
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={route('templates.index', { account: account.id })}>
                                    <LayoutTemplate /> Templates
                                </Link>
                            </Button>
                            <Button asChild disabled={!registered}>
                                <Link href={route('messages.create')}>
                                    <MessageSquareText /> Send message
                                </Link>
                            </Button>
                        </>
                    }
                />
                <FlashMessages />

                {/* Onboarding progress */}
                <Card>
                    <CardContent className="p-5">
                        <div className="grid gap-4 md:grid-cols-4">
                            <Step done label="Token exchanged" detail={account.has_token ? `${account.token_type ?? 'business'} token${account.token_expires_at ? ` · expires ${formatDate(account.token_expires_at, false)}` : ' · never expires'}` : 'No valid token'} />
                            <Step done={account.webhook_subscribed} label="Webhooks subscribed" detail={account.webhook_subscribed_at ? formatDate(account.webhook_subscribed_at) : 'POST /subscribed_apps pending'} />
                            <Step done={registered} label="Phone registered" detail={registered ? 'Ready to send' : 'Set a 6-digit PIN below'} />
                            <Step done={account.status === 'active'} label="Active" detail={account.onboarded_at ? `Onboarded ${formatDate(account.onboarded_at, false)}` : 'Complete the steps above'} />
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {/* Phone numbers */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Smartphone className="size-4 text-brand-dark" /> Phone numbers
                                </CardTitle>
                                <CardDescription>
                                    Numbers must be registered for Cloud API with a two-step verification PIN (POST /{'{Phone-Number-ID}'}/register). Embedded Signup numbers must be registered within 14 days.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {account.phone_numbers.length === 0 && (
                                    <p className="text-sm text-muted-foreground">No phone numbers found. Click “Sync with Meta” after the customer adds a number.</p>
                                )}
                                {account.phone_numbers.map((p) => (
                                    <PhoneCard key={p.id} phone={p} />
                                ))}
                            </CardContent>
                        </Card>

                        {/* Webhooks */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Webhook className="size-4 text-brand-dark" /> Webhook subscription
                                </CardTitle>
                                <CardDescription>
                                    Subscribing the app to this WABA sends all events for its phone numbers to <code className="text-xs">{webhook_url}</code>.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge status={account.webhook_subscribed ? 'active' : 'pending_setup'} />
                                    <span className="text-sm text-muted-foreground">{account.webhook_subscribed ? `Subscribed ${formatDate(account.webhook_subscribed_at)}` : 'Not subscribed'}</span>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button size="sm" onClick={() => router.post(route('accounts.subscribe', account.id), {}, { preserveScroll: true })}>
                                        Subscribe app
                                    </Button>
                                    <Button size="sm" variant="outline" onClick={() => router.post(route('accounts.subscriptions', account.id), {}, { preserveScroll: true })}>
                                        Check subscriptions
                                    </Button>
                                    <Button size="sm" variant="ghost" onClick={() => confirm('Unsubscribe this app from the WABA?') && router.delete(route('accounts.unsubscribe', account.id), { preserveScroll: true })}>
                                        Unsubscribe
                                    </Button>
                                </div>
                                <p className="text-xs text-muted-foreground">Fields to enable in App Dashboard: {webhook_fields.join(', ')}</p>
                            </CardContent>
                        </Card>

                        {partner_type === 'solution_partner' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <CreditCard className="size-4 text-brand-dark" /> Line of credit
                                    </CardTitle>
                                    <CardDescription>Solution Partners attach their credit line to each client WABA so usage is invoiced to the partner.</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-wrap items-center gap-3">
                                    <StatusBadge status={account.credit_line_shared_at ? 'active' : 'pending_setup'} />
                                    <span className="text-sm text-muted-foreground">
                                        {account.credit_allocation_config_id ? `Allocation config ${account.credit_allocation_config_id}` : 'Not shared'}
                                    </span>
                                    <Button size="sm" variant="outline" onClick={() => router.post(route('accounts.credit-line', account.id), {}, { preserveScroll: true })}>
                                        Share credit line
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <ShieldCheck className="size-4 text-brand-dark" /> Account details
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="space-y-2.5 text-sm">
                                    <Row k="Status" v={<StatusBadge status={account.status} />} />
                                    <Row k="Review status" v={<StatusBadge status={account.account_review_status} />} />
                                    {account.ban_state && <Row k="Ban state" v={account.ban_state} />}
                                    <Row k="Currency" v={account.currency ?? '—'} />
                                    <Row k="Timezone ID" v={account.timezone_id ?? '—'} />
                                    <Row k="Business ID" v={<span className="font-mono text-xs">{account.business_id ?? '—'}</span>} />
                                    <Row k="Template namespace" v={<span className="font-mono text-xs break-all">{account.message_template_namespace ?? '—'}</span>} />
                                    <Row k="Funding ID" v={<span className="font-mono text-xs">{account.primary_funding_id ?? '—'}</span>} />
                                    <Row k="System user" v={account.system_user_assigned_at ? 'Assigned' : 'Not assigned'} />
                                    <Row k="Last sync" v={formatDate(account.last_synced_at)} />
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <KeyRound className="size-4 text-brand-dark" /> Access token
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <Row k="Type" v={account.token_type ?? '—'} />
                                <Row k="Valid" v={account.has_token ? 'Yes' : 'No'} />
                                <Row k="Expires" v={account.token_expires_at ? formatDate(account.token_expires_at) : 'Never'} />
                                <div>
                                    <p className="mb-1 text-xs text-muted-foreground">Scopes</p>
                                    <div className="flex flex-wrap gap-1">
                                        {(account.token_scopes ?? []).map((s) => (
                                            <span key={s} className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px]">
                                                {s}
                                            </span>
                                        ))}
                                        {!account.token_scopes?.length && <span className="text-xs text-muted-foreground">—</span>}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-red-200">
                            <CardContent className="flex items-center justify-between p-4">
                                <p className="text-sm text-muted-foreground">Disconnect and discard the stored token.</p>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => confirm('Disconnect this account? The stored token will be deleted.') && router.delete(route('accounts.destroy', account.id))}
                                >
                                    Disconnect
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Step({ done, label, detail }: { done: boolean; label: string; detail: string }) {
    return (
        <div className="flex gap-3">
            {done ? <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-brand-dark" /> : <Circle className="mt-0.5 size-5 shrink-0 text-gray-300" />}
            <div>
                <p className="text-sm font-medium">{label}</p>
                <p className="text-xs text-muted-foreground">{detail}</p>
            </div>
        </div>
    );
}

function Row({ k, v }: { k: string; v: React.ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-3">
            <dt className="shrink-0 text-muted-foreground">{k}</dt>
            <dd className="text-right font-medium">{v}</dd>
        </div>
    );
}

function PhoneCard({ phone }: { phone: PhoneNumberSummary }) {
    const register = useForm({ pin: '' });
    const verify = useForm({ code: '' });
    const request = useForm({ code_method: 'SMS', locale: 'en_US' });
    const [showPin, setShowPin] = useState(false);

    const submitRegister = (e: FormEvent) => {
        e.preventDefault();
        register.post(route('phones.register', phone.id), { preserveScroll: true, onSuccess: () => register.reset() });
    };

    const needsVerification = phone.code_verification_status && phone.code_verification_status !== 'VERIFIED';

    return (
        <div className="rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-base font-semibold">{phone.display_phone_number ?? phone.phone_number_id}</p>
                    <p className="text-sm text-muted-foreground">{phone.verified_name ?? 'No display name'}</p>
                    <p className="mt-1 font-mono text-[11px] text-muted-foreground">ID {phone.phone_number_id}</p>
                </div>
                <div className="flex flex-wrap items-center gap-1.5">
                    {phone.is_default && <span className="rounded-full bg-brand px-2 py-0.5 text-[11px] font-semibold text-[#14200a]">DEFAULT</span>}
                    <StatusBadge status={phone.is_registered ? 'active' : 'pending_setup'} />
                    <StatusBadge status={phone.quality_rating ?? undefined} />
                </div>
            </div>

            <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-4">
                <div>
                    <dt className="text-muted-foreground">Name status</dt>
                    <dd className="font-medium">{phone.name_status ?? '—'}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Verification</dt>
                    <dd className="font-medium">{phone.code_verification_status ?? '—'}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Limit tier</dt>
                    <dd className="font-medium">{phone.messaging_limit_tier ?? '—'}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Platform</dt>
                    <dd className="font-medium">{phone.platform_type ?? '—'}</dd>
                </div>
            </dl>

            {needsVerification && (
                <div className="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3">
                    <p className="mb-2 text-xs font-medium text-amber-900">Ownership not verified — request an SMS/voice code, then submit it (POST /request_code → POST /verify_code).</p>
                    <div className="flex flex-wrap items-end gap-2">
                        <Select value={request.data.code_method} onValueChange={(v) => request.setData('code_method', v)}>
                            <SelectTrigger className="h-9 w-28 bg-white">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="SMS">SMS</SelectItem>
                                <SelectItem value="VOICE">Voice</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button size="sm" variant="outline" className="bg-white" disabled={request.processing} onClick={() => request.post(route('phones.request-code', phone.id), { preserveScroll: true })}>
                            Request code
                        </Button>
                        <Input className="h-9 w-32 bg-white" placeholder="Code" value={verify.data.code} onChange={(e) => verify.setData('code', e.target.value)} />
                        <Button size="sm" disabled={verify.processing || !verify.data.code} onClick={() => verify.post(route('phones.verify-code', phone.id), { preserveScroll: true, onSuccess: () => verify.reset() })}>
                            Verify
                        </Button>
                    </div>
                </div>
            )}

            <div className="mt-4 flex flex-wrap items-end gap-3">
                {!phone.is_registered ? (
                    <form onSubmit={submitRegister} className="flex flex-wrap items-end gap-2">
                        <Field label="Two-step verification PIN" error={register.errors.pin} hint="6 digits — memorise it, Meta will ask for it on future registrations.">
                            <Input
                                type={showPin ? 'text' : 'password'}
                                inputMode="numeric"
                                maxLength={6}
                                className="w-40"
                                value={register.data.pin}
                                onChange={(e) => register.setData('pin', e.target.value.replace(/\D/g, ''))}
                            />
                        </Field>
                        <Button type="button" variant="ghost" size="sm" onClick={() => setShowPin((s) => !s)}>
                            {showPin ? 'Hide' : 'Show'}
                        </Button>
                        <Button type="submit" disabled={register.processing || register.data.pin.length !== 6}>
                            {register.processing && <Loader2 className="animate-spin" />} Register number
                        </Button>
                    </form>
                ) : (
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <CheckCircle2 className="size-4 text-brand-dark" />
                        <span>Registered {phone.registered_at ? formatDate(phone.registered_at) : ''}</span>
                        {phone.two_step_pin && (
                            <span className="rounded-md bg-muted px-2 py-0.5 font-mono text-xs">
                                PIN {showPin ? phone.two_step_pin : '••••••'}
                                <button type="button" className="ml-2 text-brand-dark underline" onClick={() => setShowPin((s) => !s)}>
                                    {showPin ? 'hide' : 'show'}
                                </button>
                            </span>
                        )}
                        {!phone.is_default && (
                            <Button size="sm" variant="ghost" onClick={() => router.post(route('phones.default', phone.id), {}, { preserveScroll: true })}>
                                Make default
                            </Button>
                        )}
                        <Button
                            size="sm"
                            variant="ghost"
                            className="text-red-600"
                            onClick={() => confirm('Deregister this number from Cloud API?') && router.post(route('phones.deregister', phone.id), {}, { preserveScroll: true })}
                        >
                            Deregister
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}
