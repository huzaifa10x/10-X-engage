import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { LayoutTemplate, MessageSquareText, PlugZap } from 'lucide-react';
import logo from '../../../public/favicon.ico'

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col bg-white text-foreground">
                <header className="flex items-center justify-between px-6 py-4 lg:px-12">
                    <div className="flex items-center gap-2">
                        {/* <div className="flex size-9 items-center justify-center rounded-md bg-brand">
                            <AppLogoIcon className="size-5 fill-current text-[#14200a]" />
                        </div>
                        <span className="text-lg font-semibold">Engage</span> */}
                        <img src={logo} width={60} height={60} />
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

                <main className="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
                    <span className="mb-4 rounded-full bg-brand-soft px-3 py-1 text-xs font-semibold tracking-wide text-[#2b4a08] uppercase">
                        WhatsApp Business Platform
                    </span>
                    <h1 className="max-w-3xl text-4xl font-semibold tracking-tight md:text-5xl">
                        Onboard customers, send messages and manage templates on the official WhatsApp Cloud API.
                    </h1>
                    <p className="mt-4 max-w-xl text-muted-foreground">
                        Meta Embedded Signup, phone registration, message sending and template creation — built on Laravel, Inertia and React.
                    </p>
                    <div className="mt-8 flex gap-3">
                        <Button asChild size="lg">
                            <Link href={auth.user ? route('onboarding.index') : route('register')}>
                                Connect a WhatsApp account
                            </Link>
                        </Button>
                    </div>
                    <div className="mt-16 grid max-w-4xl gap-6 text-left sm:grid-cols-3">
                        {[
                            { icon: PlugZap, title: 'Embedded Signup', text: 'Token exchange, webhook subscription and phone registration exactly as Meta documents it.' },
                            { icon: MessageSquareText, title: 'Message sending', text: 'Text, media, location, interactive and template messages with live delivery status.' },
                            { icon: LayoutTemplate, title: 'Template builder', text: 'Headers, variables, footers and buttons with a WhatsApp-style preview and review status tracking.' },
                        ].map((f) => (
                            <div key={f.title} className="rounded-xl border p-5">
                                <f.icon className="mb-3 size-6 text-brand-dark" />
                                <h3 className="font-semibold">{f.title}</h3>
                                <p className="mt-1 text-sm text-muted-foreground">{f.text}</p>
                            </div>
                        ))}
                    </div>
                </main>

                <footer className="border-t py-8 text-center text-sm text-muted-foreground">
                    <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-6 sm:flex-row lg:px-12">
                        <p>© {new Date().getFullYear()} Engage. All rights reserved.</p>
                        <div className="flex flex-wrap items-center justify-center gap-6">
                            <Link href={route('privacy')} className="hover:text-foreground hover:underline">
                                Privacy Policy
                            </Link>
                            <Link href={route('terms')} className="hover:text-foreground hover:underline">
                                Terms of Service
                            </Link>
                            <Link href={route('user-dd')} className="hover:text-foreground hover:underline">
                                User Data Deletion
                            </Link>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}