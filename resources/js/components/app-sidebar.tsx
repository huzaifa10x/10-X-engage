import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Building2, LayoutGrid, LayoutTemplate, MessageSquareText, PlugZap } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'Onboarding', url: '/onboarding', icon: PlugZap },
    { title: 'Accounts', url: '/accounts', icon: Building2 },
    { title: 'Messages', url: '/messages', icon: MessageSquareText },
    { title: 'Templates', url: '/templates', icon: LayoutTemplate },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Cloud API docs',
        url: 'https://developers.facebook.com/docs/whatsapp/cloud-api',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
