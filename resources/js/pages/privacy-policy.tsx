import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Privacy() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Privacy Policy" />
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
                    <h1 className="text-3xl font-bold tracking-tight md:text-4xl">Privacy Policy</h1>
                    <p className="mt-2 text-sm text-muted-foreground">Last updated: 10 September 2026</p>

                    <div className="mt-8 space-y-8 text-sm leading-relaxed text-foreground/90">
                        <section>
                            <h2 className="text-lg font-semibold text-foreground">1. Who we are</h2>
                            <p className="mt-2">
                                [Company Legal Name] (“we”, “us”, “our”) is a company registered in the United Arab Emirates. We operate [App Name], a software platform that enables businesses to send and manage WhatsApp messages through the WhatsApp Business Platform (Meta Cloud API). For any privacy questions, contact us at [support email] or [Company Legal Name], [registered address], [Emirate], United Arab Emirates.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">2. Scope and our role</h2>
                            <p className="mt-2">
                                <strong>Your account data:</strong> When you register and use 10X Engage as our customer, we act as the data controller for your account information.
                            </p>
                            <p className="mt-2">
                                <strong>Your customers’ data:</strong> When you use 10X Engage to message your own customers, you are the controller of that data and we act as your data processor, processing it only on your documented instructions and under our data-processing terms.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">3. Data we collect</h2>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li><strong>Account information:</strong> name, email, phone number, company details, and billing information.</li>
                                <li><strong>WhatsApp Business Account data:</strong> WhatsApp Business Account IDs, business phone numbers, message templates, and business profile information you connect to [App Name].</li>
                                <li><strong>Message data:</strong> the content and metadata of messages sent and received through the platform, including recipient numbers, timestamps, and delivery and read status.</li>
                                <li><strong>Your customers’ contact data:</strong> phone numbers and contact attributes you upload or collect through conversations.</li>
                                <li><strong>Usage and technical data:</strong> log data, device and browser information, IP address, and cookies collected when you use our web application.</li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">4. How we use data</h2>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li>To provide, operate, and maintain the messaging platform.</li>
                                <li>To transmit and route messages to and from the WhatsApp Business Platform on your behalf.</li>
                                <li>To authenticate users, process payments, and provide customer support.</li>
                                <li>To monitor for abuse and enforce our Terms of Service and Meta’s policies.</li>
                                <li>To maintain the security, integrity, and performance of the Service.</li>
                            </ul>
                            <p className="mt-2 font-medium text-foreground">We do not sell your personal data or your customers’ personal data.</p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">5. How we share data</h2>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li><strong>Meta Platforms:</strong> message content and related data are transmitted to Meta/WhatsApp to deliver messaging services, subject to Meta’s terms and privacy policy.</li>
                                <li><strong>Service providers (subprocessors):</strong> hosting, storage, analytics, and payment providers that support the Service, bound by confidentiality and data-processing obligations.</li>
                                <li><strong>Legal and safety:</strong> where required by applicable law or to protect our rights, our users, or the public.</li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">6. Data retention</h2>
                            <p className="mt-2">
                                We retain account data while your account is active and as needed to provide the Service. Message and contact data are retained according to your plan settings and our data-processing terms. We delete or anonymize personal data when it is no longer required, subject to any legal retention obligations under UAE law.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">7. Your rights</h2>
                            <p className="mt-2">
                                Subject to the UAE Personal Data Protection Law (Federal Decree-Law No. 45 of 2021), and the GDPR where it applies to you, you have the right to access, correct, delete, restrict, or object to the processing of your personal data, and to data portability. To exercise these rights, contact [support email]. Where we process your customers’ personal data on your behalf, requests from those individuals should be directed to you as the controller, and we will assist you in responding.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">8. Data deletion</h2>
                            <p className="mt-2">
                                You may request deletion of your account and associated personal data at any time. Instructions are available at https://[yourdomain.com]/data-deletion or by emailing [support email]. We will action verified requests within the period required by applicable law.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">9. Security</h2>
                            <p className="mt-2">
                                We apply technical and organizational measures — encryption in transit, access controls, and secure storage of access tokens and credentials — to protect personal data. No method of transmission or storage is completely secure, and we cannot guarantee absolute security.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">10. International transfers</h2>
                            <p className="mt-2">
                                Your data may be processed outside the UAE, including where our service providers operate. Where required, we rely on appropriate safeguards for cross-border transfers in accordance with the UAE Personal Data Protection Law.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">11. Cookies</h2>
                            <p className="mt-2">
                                Our web application uses cookies and similar technologies for authentication, preferences, and analytics. You can manage cookies through your browser settings.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">12. Children</h2>
                            <p className="mt-2">
                                [App Name] is not directed to individuals under 18, and we do not knowingly collect their personal data.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">13. Changes to this policy</h2>
                            <p className="mt-2">
                                We may update this policy from time to time. Material changes will be posted on this page with a revised “Last updated” date.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">14. Governing law</h2>
                            <p className="mt-2">
                                This policy is governed by the laws of the United Arab Emirates and the applicable laws of the Emirate of [Emirate], including the UAE Personal Data Protection Law (Federal Decree-Law No. 45 of 2021), and the GDPR where applicable.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">15. Contact</h2>
                            <p className="mt-2">
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