import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function UserDataDeletion() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="User Data Deletion" />
            <div className="flex min-h-screen flex-col bg-white text-foreground">
                <header className="flex items-center justify-between border-b px-6 py-4 lg:px-12">
                    <div className="flex items-center gap-2">
                        <div className="flex size-9 items-center justify-center rounded-md bg-brand">
                            <AppLogoIcon className="size-5 fill-current text-[#14200a]" />
                        </div>
                        <span className="text-lg font-semibold">Engage</span>
                    </div>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={route('dashboard')}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href={route('login')}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={route('register')}>Get started</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto max-w-4xl px-6 py-12 text-left">
                    <h1 className="text-3xl font-bold tracking-tight md:text-4xl">User Data Deletion</h1>
                    <p className="mt-2 text-sm text-muted-foreground">Last updated: 10 September 2026</p>

                    <div className="mt-8 space-y-8 text-sm leading-relaxed text-foreground/90">
                        <section>
                            <p>
                                At 10X Digital, operator of 10X Engage, you can request deletion of your personal data and your account at any time. This page explains how.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">What gets deleted</h2>
                            <p className="mt-2">
                                On a verified deletion request, we delete the personal data associated with your account, including:
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li>Your account and profile information (name, email, phone number, company details).</li>
                                <li>Connected WhatsApp Business Account details and business phone numbers stored in 10X Engage.</li>
                                <li>Message history, templates, and contact data stored on your account.</li>
                                <li>Usage logs associated with your account.</li>
                            </ul>
                            <p className="mt-2">
                                Some data may be retained where required by law (for example, tax and accounting records) or for the establishment or defense of legal claims. Such data is deleted once the retention period expires.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">How to request deletion</h2>
                            <p className="mt-2">Choose either method:</p>
                            <ul className="mt-2 list-disc space-y-2 pl-5">
                                <li>
                                    <strong>In-app:</strong> Sign in to 10X Engage, go to Settings → Account → Delete Account, and follow the prompts.
                                </li>
                                <li>
                                    <strong>By email:</strong> Send a request to [info@10xdigital.ae] from the email address registered to your account, with the subject line “Data Deletion Request”. Include your account name so we can verify your identity.
                                </li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">What happens next</h2>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li>We will verify your identity to protect your account.</li>
                                <li>We will confirm receipt and complete the deletion within 30 days, or sooner where required by applicable law.</li>
                                <li>We will notify you by email once deletion is complete.</li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">Data processed on behalf of business customers</h2>
                            <p className="mt-2">
                                If you are an end-customer whose data was processed because a business used [App Name] to message you, that business is the controller of your data. Please direct your deletion request to that business. If you contact us, we will forward your request to the relevant business customer and assist them in fulfilling it.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">Contact</h2>
                            <p className="mt-2">
                                For any questions about data deletion, contact support email info@10xdigital.ae <br />
                                Tenx Digital Fzco, <br />
                                +971 4 564 4587, <br />
                                6162 Building A1: DDP, Dubai Silicon Oasis, Dubai, UAE. <br />
                                info@10xdigital.ae.
                            </p>
                        </section>
                    </div>
                </main>
            </div>
        </>
    );
}