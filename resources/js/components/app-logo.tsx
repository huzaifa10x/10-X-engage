import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-brand text-brand-dark">
                <AppLogoIcon className="size-5 fill-current text-[#14200a]" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">Engage</span>
                <span className="truncate text-[11px] leading-none text-muted-foreground">WhatsApp Business Platform</span>
            </div>
        </>
    );
}
