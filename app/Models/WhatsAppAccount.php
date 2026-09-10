<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppAccount extends Model
{
    public const STATUS_PENDING_SETUP = 'pending_setup';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STEP_TOKEN_EXCHANGED = 'token_exchanged';

    public const STEP_WEBHOOK_SUBSCRIBED = 'webhook_subscribed';

    public const STEP_PHONE_REGISTERED = 'phone_registered';

    public const STEP_COMPLETED = 'completed';

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'workspace_id', 'waba_id', 'name', 'currency', 'timezone_id', 'message_template_namespace',
        'business_id', 'owner_business_name', 'account_review_status', 'ban_state', 'primary_funding_id',
        'access_token', 'token_type', 'token_scopes', 'token_expires_at', 'token_debugged_at',
        'webhook_subscribed_at', 'webhook_override_callback_uri', 'system_user_assigned_at',
        'credit_allocation_config_id', 'credit_line_shared_at',
        'status', 'onboarding_step', 'onboarded_at', 'last_synced_at', 'meta',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_scopes' => 'array',
            'meta' => 'array',
            'token_expires_at' => 'datetime',
            'token_debugged_at' => 'datetime',
            'webhook_subscribed_at' => 'datetime',
            'system_user_assigned_at' => 'datetime',
            'credit_line_shared_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class, 'whatsapp_account_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class, 'whatsapp_account_id');
    }

    public function signupSessions(): HasMany
    {
        return $this->hasMany(SignupSession::class, 'whatsapp_account_id');
    }

    /**
     * Token used for every Graph API call on this WABA: the customer's business token
     * from Embedded Signup, falling back to the partner system-user token.
     */
    public function apiToken(): ?string
    {
        return $this->access_token ?: config('whatsapp.partner.system_user_token');
    }

    public function hasValidToken(): bool
    {
        if (! $this->apiToken()) {
            return false;
        }

        return $this->token_expires_at === null || $this->token_expires_at->isFuture();
    }

    public function isWebhookSubscribed(): bool
    {
        return $this->webhook_subscribed_at !== null;
    }

    public function markStep(string $step): void
    {
        $order = [
            self::STEP_TOKEN_EXCHANGED, self::STEP_WEBHOOK_SUBSCRIBED,
            self::STEP_PHONE_REGISTERED, self::STEP_COMPLETED,
        ];

        if (array_search($step, $order, true) >= array_search($this->onboarding_step, $order, true)) {
            $this->onboarding_step = $step;
        }

        if ($step === self::STEP_COMPLETED) {
            $this->status = self::STATUS_ACTIVE;
            $this->onboarded_at ??= now();
        }

        $this->save();
    }
}
