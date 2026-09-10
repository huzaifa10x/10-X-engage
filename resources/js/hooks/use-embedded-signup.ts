import { useCallback, useEffect, useRef, useState } from 'react';

export interface SignupConfig {
    app_id: string | null;
    config_id: string | null;
    graph_version: string;
    version: string;
    features: string[];
    feature_type: string | null;
}

/** Payload of the WA_EMBEDDED_SIGNUP message event (session logging, v4). */
export interface SessionInfo {
    event: string;
    data: {
        phone_number_id?: string;
        waba_id?: string;
        business_id?: string;
        waba_ids?: string[];
        current_step?: string;
        error_message?: string;
        error_code?: string;
        session_id?: string;
        timestamp?: string;
        [key: string]: unknown;
    };
}

export interface SignupResult {
    code: string;
    session: SessionInfo | null;
}

type Status = 'idle' | 'loading-sdk' | 'ready' | 'launching' | 'exchanging' | 'error';

/**
 * Implements Meta's "Embed the Signup Flow" reference exactly:
 *  1. Load https://connect.facebook.net/en_US/sdk.js and FB.init({ appId, version })
 *  2. Listen for `message` events from facebook.com with type === 'WA_EMBEDDED_SIGNUP'
 *     (returns phone_number_id, waba_id, business_id on FINISH / current_step on CANCEL / error on ERROR)
 *  3. FB.login(callback, { config_id, response_type: 'code', override_default_response_type: true, extras })
 *     -> callback receives response.authResponse.code (exchangeable, 30 s TTL)
 */
export function useEmbeddedSignup(config: SignupConfig, handlers: { onComplete: (result: SignupResult) => void; onSessionEvent?: (info: SessionInfo) => void }) {
    const [status, setStatus] = useState<Status>('idle');
    const [error, setError] = useState<string | null>(null);
    const [lastSession, setLastSession] = useState<SessionInfo | null>(null);
    const sessionRef = useRef<SessionInfo | null>(null);
    const handlersRef = useRef(handlers);
    handlersRef.current = handlers;

    // 1. SDK loading + initialisation
    useEffect(() => {
        if (!config.app_id) return;

        const init = () => {
            window.FB?.init({
                appId: config.app_id,
                autoLogAppEvents: true,
                xfbml: true,
                version: config.graph_version,
            });
            setStatus('ready');
        };

        if (window.FB) {
            init();
            return;
        }

        setStatus('loading-sdk');
        window.fbAsyncInit = init;

        if (!document.getElementById('facebook-jssdk')) {
            const script = document.createElement('script');
            script.id = 'facebook-jssdk';
            script.src = 'https://connect.facebook.net/en_US/sdk.js';
            script.async = true;
            script.defer = true;
            script.crossOrigin = 'anonymous';
            script.onerror = () => {
                setStatus('error');
                setError('Could not load the Facebook JavaScript SDK. Check ad blockers / network.');
            };
            document.body.appendChild(script);
        }
    }, [config.app_id, config.graph_version]);

    // 2. Session logging message event listener
    useEffect(() => {
        const listener = (event: MessageEvent) => {
            if (typeof event.origin !== 'string' || !event.origin.endsWith('facebook.com')) return;

            try {
                const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                if (data?.type === 'WA_EMBEDDED_SIGNUP') {
                    const info: SessionInfo = { event: data.event, data: data.data ?? {} };
                    sessionRef.current = info;
                    setLastSession(info);
                    handlersRef.current.onSessionEvent?.(info);
                }
            } catch {
                // Non-JSON messages from facebook.com are ignored.
            }
        };

        window.addEventListener('message', listener);
        return () => window.removeEventListener('message', listener);
    }, []);

    // 3. Launch
    const launch = useCallback(() => {
        if (!window.FB || !config.config_id) {
            setError('Embedded Signup is not configured (missing app ID or configuration ID).');
            setStatus('error');
            return;
        }

        setError(null);
        sessionRef.current = null;
        setStatus('launching');

        const extras: Record<string, unknown> = { setup: {}, version: config.version };
        if (config.features?.length) extras.features = config.features;
        if (config.feature_type) extras.featureType = config.feature_type;
        // sessionInfoVersion 3 = session logging returns the asset IDs to the message listener above
        extras.sessionInfoVersion = '3';

        window.FB.login(
            (response) => {
                if (response.authResponse?.code) {
                    setStatus('exchanging');
                    // Give the message event (which usually precedes the callback) a moment to land.
                    setTimeout(() => {
                        handlersRef.current.onComplete({ code: response.authResponse!.code!, session: sessionRef.current });
                    }, 150);
                } else {
                    setStatus('ready');
                    setError(
                        sessionRef.current?.event === 'CANCEL'
                            ? `Signup was cancelled at step ${sessionRef.current.data.current_step ?? 'unknown'}.`
                            : 'Login was cancelled or not fully authorised.',
                    );
                }
            },
            {
                config_id: config.config_id,
                response_type: 'code',
                override_default_response_type: true,
                extras,
            },
        );
    }, [config]);

    return { status, error, launch, lastSession, reset: () => setStatus('ready') };
}
