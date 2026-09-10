export {};

declare global {
    interface Window {
        FB?: {
            init: (options: Record<string, unknown>) => void;
            login: (callback: (response: FBLoginResponse) => void, options: Record<string, unknown>) => void;
        };
        fbAsyncInit?: () => void;
    }

    interface FBLoginResponse {
        status?: string;
        authResponse?: { code?: string; accessToken?: string; userID?: string; expiresIn?: number } | null;
    }
}
