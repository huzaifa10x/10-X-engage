<?php

namespace App\Providers;

use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Models\Contact;
use App\Policies\ContactPolicy;
use App\Policies\MessagePolicy;
use App\Policies\WhatsAppAccountPolicy;
use App\Services\Meta\GraphClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GraphClient::class, fn () => new GraphClient(
            token: config('whatsapp.partner.system_user_token'),
            version: config('whatsapp.graph.version'),
        ));
    }

    public function boot(): void
    {
        Gate::policy(WhatsAppAccount::class, WhatsAppAccountPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);

        // Outbound queue throttle (broadcasts) – per-second cap across the app; Meta allows 80 mps per number.
        RateLimiter::for('whatsapp-send', fn ($job) => Limit::perSecond((int) config('whatsapp.broadcast.rate_per_second', 20)));
    }
}
