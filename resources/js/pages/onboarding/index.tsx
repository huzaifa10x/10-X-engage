import FlashMessages from '@/components/flash-messages';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useEmbeddedSignup, type SessionInfo, type SignupConfig } from '@/hooks/use-embedded-signup';
import AppLayout from '@/layouts/app-layout';
import { formatDate, timeAgo } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, CheckCircle2, Circle, Loader2, PlugZap, ShieldCheck, Webhook } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Onboarding', href: '/onboarding' }];

interface AccountRow {
    id: number;
    waba_id: string;
    name: string | null;
    status: string;
    onboarding_step: string;
    phone_numbers_count: number;
    webhook_subscribed: boolean;
    created_at: string | null;
}

interface SessionRow {
    id: number;
    event: string | null;
    status: string;
    waba_id: string | null;
    phone_number_id: string | null;
    current_step: string | null;
    error_message: string | null;
    created_at: string | null;
}

interface Props {
    accounts: AccountRow[];
    sessions: SessionRow[];
    signup: SignupConfig & { configured: boolean; partner_type: string; webhook_url: string; is_https: boolean };
}

const flowSteps = [
    { title: 'Customer completes Embedded Signup', detail: 'FB.login() opens Meta’s popup; the customer creates or selects a WABA and adds a phone number.' },
    { title: 'Exchange token code', detail: 'GET /oauth/access_token — the 30-second code becomes a business token (stored encrypted).' },
    { title: 'Debug token & fetch WABA', detail: 'GET /debug_token confirms scopes and shared WABA IDs; GET /{WABA-ID} pulls account details.' },
    { title: 'Subscribe to webhooks', detail: 'POST /{WABA-ID}/subscribed_apps so messages, statuses and template updates reach this app.' },
    { title: 'Fetch phone numbers', detail: 'GET /{WABA-ID}/phone_numbers lists the numbers and their verification state.' },
    { title: 'Register phone number', detail: 'POST /{Phone-Number-ID}/register with a 6-digit PIN — done from the account page (within 14 days).' },
];

export default function OnboardingIndex({ accounts, sessions, signup }: Props) {
    const [submitting, setSubmitting] = useState(false);
    const [serverError, setServerError] = useState<string | null>(null);

    const logSession = (info: SessionInfo) => {
        if (info.event === 'CANCEL' || info.event === 'ERROR') {
            router.post(
                route('onboarding.session'),
                {
                    event: info.event,
                    current_step: info.data.current_step ?? null,
                    error_code: info.data.error_code ?? null,
                    error_message: info.data.error_message ?? null,
                    session_id: info.data.session_id ?? null,
                    timestamp: info.data.timestamp ?? null,
                    data: info.data as Record<string, string>,
                },
                { preserveScroll: true, preserveState: true, only: ['sessions'] },
            );
        }
    };

    const { status, error, launch, lastSession } = useEmbeddedSignup(signup, {
        onSessionEvent: logSession,
        onComplete: ({ code, session }) => {
            setSubmitting(true);
            setServerError(null);
            router.post(
                route('onboarding.callback'),
                {
                    code,
                    event: session?.event ?? 'FINISH',
                    waba_id: session?.data.waba_id ?? null,
                    phone_number_id: session?.data.phone_number_id ?? null,
                    business_id: session?.data.business_id ?? null,
                    waba_ids: session?.data.waba_ids ?? null,
                },
                {
                    onError: (errors) => setServerError(Object.values(errors).join(' ')),
                    onFinish: () => setSubmitting(false),
                },
            );
        },
    });

    const busy = submitting || status === 'exchanging' || status === 'launching';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Onboarding" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Connect a WhatsApp Business Account"
                    description="Onboard your business customers through Meta’s Embedded Signup. The flow below follows Meta’s documented sequence for Tech Providers and Solution Partners."
                />
                <FlashMessages />

                <div className="grid gap-6 lg:grid-cols-5">
                    <Card className="lg:col-span-3">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <PlugZap className="size-5 text-brand-dark" /> Embedded Signup
                            </CardTitle>
                            <CardDescription>
                                Opens Meta’s hosted popup. On completion the WABA ID, phone number ID and an exchangeable token code are returned to this page and immediately exchanged on the server.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {!signup.configured && (
                                <Notice tone="warning">
                                    Set <code>WHATSAPP_APP_ID</code>, <code>WHATSAPP_APP_SECRET</code> and <code>WHATSAPP_CONFIG_ID</code> in your <code>.env</code> to enable the flow.
                                </Notice>
                            )}
                            {!signup.is_https && (
                                <Notice tone="warning">
                                    Embedded Signup only returns data to pages served over HTTPS whose domain is listed under Facebook Login for Business → Allowed domains.
                                </Notice>
                            )}
                            {(error || serverError) && <Notice tone="error">{serverError ?? error}</Notice>}

                            <div className="flex flex-wrap items-center gap-3">
                                <Button size="lg" onClick={launch} disabled={!signup.configured || busy || status === 'loading-sdk'} className="font-semibold">
                                    {busy ? <Loader2 className="animate-spin" /> : <PlugZap />}
                                    {status === 'exchanging' || submitting ? 'Completing onboarding…' : status === 'launching' ? 'Waiting for Meta…' : 'Launch Embedded Signup'}
                                </Button>
                                <span className="text-xs text-muted-foreground">
                                    SDK: {status === 'loading-sdk' ? 'loading…' : status === 'idle' ? 'not loaded' : 'ready'} · Graph {signup.graph_version} · ES {signup.version} ·{' '}
                                    {signup.partner_type === 'solution_partner' ? 'Solution Partner' : 'Tech Provider'}
                                </span>
                            </div>

                            {lastSession && (
                                <div className="rounded-md border bg-muted/40 p-3 text-xs">
                                    <p className="mb-1 font-semibold">Last session event: {lastSession.event}</p>
                                    <pre className="max-h-40 overflow-auto whitespace-pre-wrap text-muted-foreground">{JSON.stringify(lastSession.data, null, 2)}</pre>
                                </div>
                            )}

                            <ol className="space-y-3">
                                {flowSteps.map((s, i) => (
                                    <li key={s.title} className="flex gap-3">
                                        <span className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-[#2b4a08]">{i + 1}</span>
                                        <div>
                                            <p className="text-sm font-medium">{s.title}</p>
                                            <p className="text-xs text-muted-foreground">{s.detail}</p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </CardContent>
                    </Card>

                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Webhook className="size-4 text-brand-dark" /> Webhook endpoint
                                </CardTitle>
                                <CardDescription>Configure this callback URL and your verify token in App Dashboard → WhatsApp → Configuration.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <code className="block rounded-md bg-muted px-3 py-2 text-xs break-all">{signup.webhook_url}</code>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <ShieldCheck className="size-4 text-brand-dark" /> Meta prerequisites
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-2 text-sm">
                                    {[
                                        'App reviewed with Advanced Access to business_management, whatsapp_business_management and whatsapp_business_messaging',
                                        'Facebook Login for Business configuration (WhatsApp Embedded Signup template)',
                                        'Allowed domains + valid OAuth redirect URIs include this host (HTTPS)',
                                        'Webhook fields: messages, message_template_status_update, account_update, account_review_update, phone_number_name_update, phone_number_quality_update',
                                    ].map((t) => (
                                        <li key={t} className="flex gap-2">
                                            <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-brand-dark" />
                                            <span className="text-muted-foreground">{t}</span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Connected accounts</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {accounts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No WhatsApp Business Accounts connected yet.</p>
                        ) : (
                            <div className="divide-y">
                                {accounts.map((a) => (
                                    <div key={a.id} className="flex flex-wrap items-center gap-3 py-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">{a.name ?? 'Unnamed WABA'}</p>
                                            <p className="text-xs text-muted-foreground">
                                                WABA {a.waba_id} · {a.phone_numbers_count} number(s) · {a.webhook_subscribed ? 'webhooks on' : 'webhooks off'} · {timeAgo(a.created_at)}
                                            </p>
                                        </div>
                                        <StatusBadge status={a.status} />
                                        <StepPill step={a.onboarding_step} />
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={route('accounts.show', a.id)}>
                                                Manage <ArrowRight />
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Recent signup sessions</CardTitle>
                        <CardDescription>Every FINISH / CANCEL / ERROR event from the popup plus the result of the server-side exchange.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {sessions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No sessions recorded.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-left text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="py-2 pr-3">When</th>
                                            <th className="py-2 pr-3">Event</th>
                                            <th className="py-2 pr-3">Result</th>
                                            <th className="py-2 pr-3">WABA</th>
                                            <th className="py-2 pr-3">Phone number ID</th>
                                            <th className="py-2">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {sessions.map((s) => (
                                            <tr key={s.id}>
                                                <td className="py-2 pr-3 whitespace-nowrap text-muted-foreground">{formatDate(s.created_at)}</td>
                                                <td className="py-2 pr-3 font-medium">{s.event ?? '—'}</td>
                                                <td className="py-2 pr-3">
                                                    <StatusBadge status={s.status} />
                                                </td>
                                                <td className="py-2 pr-3 font-mono text-xs">{s.waba_id ?? '—'}</td>
                                                <td className="py-2 pr-3 font-mono text-xs">{s.phone_number_id ?? '—'}</td>
                                                <td className="py-2 text-xs text-muted-foreground">{s.current_step ? `Step: ${s.current_step}` : (s.error_message ?? '—')}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function Notice({ tone, children }: { tone: 'warning' | 'error'; children: React.ReactNode }) {
    return (
        <div className={`flex items-start gap-2 rounded-md border px-3 py-2 text-sm ${tone === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900'}`}>
            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
            <div>{children}</div>
        </div>
    );
}

export function StepPill({ step }: { step: string }) {
    const steps = ['token_exchanged', 'webhook_subscribed', 'phone_registered', 'completed'];
    const idx = Math.max(0, steps.indexOf(step));
    return (
        <span className="inline-flex items-center gap-1 text-xs text-muted-foreground" title={step}>
            {steps.map((s, i) => (i <= idx ? <CheckCircle2 key={s} className="size-3.5 text-brand-dark" /> : <Circle key={s} className="size-3.5" />))}
        </span>
    );
}
