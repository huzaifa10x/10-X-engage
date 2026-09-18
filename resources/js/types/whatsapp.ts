export interface PhoneNumberSummary {
    id: number;
    phone_number_id: string;
    display_phone_number: string | null;
    verified_name: string | null;
    quality_rating: string | null;
    name_status: string | null;
    code_verification_status: string | null;
    account_mode: string | null;
    messaging_limit_tier: string | null;
    platform_type: string | null;
    is_registered: boolean;
    two_step_pin?: string | null;
    registered_at: string | null;
    is_default: boolean;
}

export interface AccountSummary {
    id: number;
    waba_id: string;
    name: string | null;
    status: string;
    onboarding_step: string;
    webhook_subscribed: boolean;
    templates_count?: number | null;
    phone_numbers: PhoneNumberSummary[];
    created_at: string | null;
}

export interface AccountDetail extends AccountSummary {
    currency: string | null;
    timezone_id: string | null;
    message_template_namespace: string | null;
    business_id: string | null;
    owner_business_name: string | null;
    account_review_status: string | null;
    ban_state: string | null;
    primary_funding_id: string | null;
    token_type: string | null;
    token_scopes: string[] | null;
    token_expires_at: string | null;
    has_token: boolean;
    webhook_subscribed_at: string | null;
    system_user_assigned_at: string | null;
    credit_allocation_config_id: string | null;
    credit_line_shared_at: string | null;
    onboarded_at: string | null;
    last_synced_at: string | null;
}

export type TemplateComponent = {
    type: string;
    format?: string;
    text?: string;
    example?: Record<string, unknown>;
    buttons?: TemplateButton[];
    add_security_recommendation?: boolean;
    code_expiration_minutes?: number;
};

export type TemplateButton = {
    type: string;
    text?: string;
    url?: string;
    phone_number?: string;
    example?: string[] | string;
    otp_type?: string;
};

export interface TemplateRow {
    id: number;
    template_id: string | null;
    name: string;
    language: string;
    category: string;
    status: string;
    body: string | null;
    account: { id: number; waba_id: string; name: string | null } | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface MessageRow {
    id: number;
    wamid: string | null;
    direction: 'outbound' | 'inbound';
    type: string;
    status: string;
    to: string | null;
    from: string | null;
    preview: string | null;
    contact: { id: number; wa_id: string; name: string | null } | null;
    phone: { id: number; display_phone_number: string | null; verified_name: string | null } | null;
    error_code: number | null;
    error_title: string | null;
    error_message: string | null;
    sent_at: string | null;
    delivered_at: string | null;
    read_at: string | null;
    failed_at: string | null;
    received_at: string | null;
    created_at: string | null;
}

/* Contacts & inbox --------------------------------------------------------- */

export interface WindowState {
    open: boolean;
    expires_at: string | null;
    opened_by: 'inbound' | 'template' | null;
    seconds_left: number;
    has_history: boolean;
}

export interface ContactRow {
    id: number;
    wa_id: string;
    phone: string;
    name: string | null;
    display_name: string;
    initials: string;
    email: string | null;
    company: string | null;
    tags: string[];
    source: string;
    sender: string | null;
    unread_count: number;
    last_message_at: string | null;
    last_message_preview: string | null;
    last_message_direction: 'inbound' | 'outbound' | null;
    window: WindowState;
    created_at: string | null;
}

export interface ChatMessage {
    id: number;
    wamid: string | null;
    direction: 'inbound' | 'outbound';
    type: string;
    status: string;
    preview: string | null;
    body: {
        text?: string;
        media?: { type: string; id: string | null; link: string | null; caption: string | null; filename: string | null; mime_type: string | null };
        location?: { latitude: string; longitude: string; name?: string; address?: string } | null;
        template?: { name: string | null; text: string | null };
        interactive?: Record<string, unknown> | null;
        unsupported?: { kind: string | null; code: number | null; detail: string | null; hint: string };
    };
    context_wamid: string | null;
    template_id: number | null;
    error_code: number | null;
    error_message: string | null;
    sent_at: string | null;
    delivered_at: string | null;
    read_at: string | null;
    failed_at: string | null;
    timestamp: string | null;
    updated_at: string | null;
    /** client-only: optimistic bubble not yet confirmed by the server */
    pending?: boolean;
}

export interface InboxPhone {
    id: number;
    account_id: number;
    display_phone_number: string | null;
    verified_name: string | null;
    is_default: boolean;
}

export interface InboxTemplate {
    id: number;
    account_id: number;
    name: string;
    language: string;
    category: string;
    components: TemplateComponent[];
}
