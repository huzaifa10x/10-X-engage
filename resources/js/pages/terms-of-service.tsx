import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function TermsofService() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Terms of Service" />
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
                    <h1 className="text-3xl font-bold tracking-tight md:text-4xl">Terms of Service</h1>
                    <p className="mt-2 text-sm text-muted-foreground">Last updated: 10 September 2026</p>

                    <div className="mt-8 space-y-8 text-sm leading-relaxed text-foreground/90">
                        <section>
                            <h2 className="text-lg font-semibold text-foreground">1. Acceptance of terms</h2>
                            <p className="mt-2">
                                By creating an account or using 10X Engage (the “Service”), operated by Tenx Digital Fzco (“we”, “us”, “our”), a company registered in the United Arab Emirates, you (“Customer”, “you”) agree to these Terms of Service. If you use the Service on behalf of an organization, you represent that you are authorized to bind that organization.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">2. Description of the Service</h2>
                            <p className="mt-2">
                                10X Engage is a software platform that enables businesses to send, receive, and manage WhatsApp messages, templates, and campaigns through the WhatsApp Business Platform (Meta Cloud API). The Service relies on Meta’s platform and is subject to its availability, limits, and terms.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">3. Eligibility and accounts</h2>
                            <p className="mt-2">
                                You must be at least 18 years old and provide accurate, complete registration information. You are responsible for maintaining the confidentiality of your account credentials and for all activity that occurs under your account.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">4. Third-party (Meta) terms</h2>
                            <p className="mt-2">
                                Your use of the Service requires compliance with Meta’s terms, including the WhatsApp Business Messaging Policy, the WhatsApp Business Terms of Service, and Meta’s Platform Terms and Developer Policies, which are incorporated by reference. If your use violates any of Meta’s policies, we may suspend or terminate your access.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">5. Your responsibilities and acceptable use</h2>
                            <p className="mt-2">You agree that you will:</p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li>Obtain valid opt-in consent from each recipient before messaging them, as required by WhatsApp’s policies.</li>
                                <li>Send only permitted content and not use the Service for spam, scams, or illegal, deceptive, or prohibited content.</li>
                                <li>Comply with all applicable laws, including data protection and anti-spam laws.</li>
                                <li>Not resell, sublicense, reverse-engineer, or misuse the Service, or exceed applicable rate or usage limits.</li>
                            </ul>
                            <p className="mt-2">
                                We may suspend messaging or your account immediately if we reasonably believe you have violated these terms or Meta’s policies, or where necessary to protect the platform’s integrity.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">6. Fees and billing</h2>
                            <p className="mt-2">
                                Subscription fees are billed according to your selected plan.
                            </p>
                            <p className="mt-2">
                                <strong>Messaging charges:</strong> Meta charges for conversations and messages by category (marketing, utility, authentication, and service). These charges are passed through to you and/or included in your plan as described at signup.
                            </p>
                            <p className="mt-2">
                                Fees are exclusive of any applicable UAE VAT. Fees are non-refundable except where required by law. Late or failed payment may result in suspension of the Service.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">7. Data protection</h2>
                            <p className="mt-2">
                                Our handling of personal data is described in our Privacy Policy. You remain the controller of your customers’ personal data and are responsible for having a lawful basis and the necessary consents to process it. We act as your processor under our data-processing terms.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">8. Intellectual property</h2>
                            <p className="mt-2">
                                We retain all rights, title, and interest in the Service and its software. You retain all rights to your content and data. You grant us a limited, non-exclusive license to process your content solely to provide the Service.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">9. Disclaimers</h2>
                            <p className="mt-2">
                                The Service is provided “as is” and “as available”. We do not warrant that it will be uninterrupted or error-free, and we are not responsible for outages, changes, or restrictions imposed by Meta/WhatsApp.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">10. Limitation of liability</h2>
                            <p className="mt-2">
                                To the maximum extent permitted by law, we are not liable for any indirect, incidental, special, or consequential damages. Our total aggregate liability under these terms is limited to the fees you paid to us in the twelve (12) months preceding the event giving rise to the claim.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">11. Indemnification</h2>
                            <p className="mt-2">
                                You agree to indemnify and hold us harmless against any claims, losses, or damages arising from your content, your use of the Service, or your violation of these terms or Meta’s policies.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">12. Suspension and termination</h2>
                            <p className="mt-2">
                                Either party may terminate on notice. We may suspend or terminate your access immediately for breach of these terms or Meta’s policies. On termination, your right to use the Service ends, and we will delete or return your data in accordance with the Privacy Policy.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">13. Changes to these terms</h2>
                            <p className="mt-2">
                                We may update these terms from time to time. Material changes will be posted on this page with a revised date. Your continued use of the Service after changes take effect constitutes acceptance.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-foreground">14. Governing law and jurisdiction</h2>
                            <p className="mt-2">
                                These terms are governed by the laws of the United Arab Emirates and the applicable laws of the Emirate of Dubai. Any dispute arising out of or in connection with these terms shall be subject to the exclusive jurisdiction of the courts of Dubai, United Arab Emirates.
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