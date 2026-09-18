# Engage — WhatsApp Business Platform (Laravel 12 · Inertia · React · MySQL)

Phase‑1 implementation of the three priority modules, built strictly from Meta's Postman collections
(Embedded Signup, WhatsApp Cloud API, Business Management API) and the Embedded Signup v4 docs:

1. **Embedded Signup & Onboarding** – token exchange, token debug, WABA sync, webhook subscription,
   phone number registration / verification, optional system‑user assignment and credit‑line sharing.
2. **Message Sending** – text, image, video, document, audio, sticker, location, interactive (reply buttons / list)
   and template messages, with delivery status tracking through webhooks.
3. **Template Creation** – header (text / image / video / document / location), body variables with examples,
   footer, quick‑reply / URL / phone / copy‑code buttons and authentication (OTP) templates, plus status sync.

Graph API version: **v25.0**. Embedded Signup: **v4** (v2 is deprecated by Meta on 15 Oct 2026).

---

## 1. Requirements

| Tool | Version |
|------|---------|
| PHP | 8.2+ (8.3 recommended) with `mbstring`, `openssl`, `pdo_mysql`, `curl`, `fileinfo` |
| Composer | 2.x |
| Node.js | 20+ (22 recommended) |
| MySQL | 8.0+ |
| HTTPS | Required for Embedded Signup and webhooks (use ngrok / Cloudflare Tunnel / Expose locally) |

## 2. Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# MySQL
mysql -u root -e "CREATE DATABASE engage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# edit DB_* in .env, then:
php artisan migrate

npm install
npm run build          # or: npm run dev

php artisan serve      # http://127.0.0.1:8000
php artisan queue:work # webhooks are processed on the queue (QUEUE_CONNECTION=database by default)
```

Register a user at `/register` – a workspace (tenant) is created automatically.

## 3. Meta configuration

Fill these in `.env` (see comments in `.env.example`):

| Variable | Where to find it |
|----------|------------------|
| `WHATSAPP_APP_ID`, `WHATSAPP_APP_SECRET` | App Dashboard → App settings → Basic |
| `WHATSAPP_CONFIG_ID` | App Dashboard → Facebook Login for Business → Configurations → *WhatsApp Embedded Signup* configuration |
| `WHATSAPP_BUSINESS_ID` | Your (partner) business portfolio ID |
| `WHATSAPP_SYSTEM_USER_TOKEN` | Permanent System User token (business_management, whatsapp_business_management, whatsapp_business_messaging). Used for partner‑level calls and as fallback. |
| `WHATSAPP_SYSTEM_USER_ID` | Optional – system user to assign to each onboarded WABA (`POST /{WABA}/assigned_users`) |
| `WHATSAPP_PARTNER_TYPE` | `tech_provider` (default) or `solution_partner` |
| `WHATSAPP_CREDIT_LINE_ID` | Solution partners only – `GET /{Business-ID}/extendedcredits` |
| `WHATSAPP_WEBHOOK_VERIFY_TOKEN` | Any secret string – also enter it in App Dashboard → WhatsApp → Configuration |

In the App Dashboard:

1. **Facebook Login for Business → Settings**: enable Client OAuth login, Web OAuth login, Enforce HTTPS,
   Embedded Browser OAuth Login, Login with the JavaScript SDK. Add your HTTPS domain to *Allowed domains for the
   JavaScript SDK* and *Valid OAuth redirect URIs*.
2. **WhatsApp → Configuration**: Callback URL = `https://<your-domain>/webhooks/whatsapp`, Verify token =
   `WHATSAPP_WEBHOOK_VERIFY_TOKEN`. Subscribe to `messages`, `message_template_status_update`, `account_update`,
   `account_review_update`, `phone_number_name_update`, `phone_number_quality_update`.
3. App Review with Advanced Access to `business_management`, `whatsapp_business_management`,
   `whatsapp_business_messaging` (required before non‑admin businesses can onboard).

## 4. Onboarding flow (what the code does)

| Step | Endpoint (Postman) | Code |
|------|--------------------|------|
| Browser: launch popup | `FB.login({ config_id, response_type:'code', override_default_response_type:true, extras:{ setup:{}, version:'v4', sessionInfoVersion:'3' } })` | `resources/js/hooks/use-embedded-signup.ts` |
| Browser: session logging | `message` event, `type === 'WA_EMBEDDED_SIGNUP'` → `phone_number_id`, `waba_id`, `business_id` / `current_step` / error | same hook |
| 1. Exchange code | `GET /oauth/access_token?client_id&client_secret&code` | `EmbeddedSignupService::exchangeCode` |
| 2. Debug token | `GET /debug_token?input_token=` | `EmbeddedSignupService::debugToken` |
| 3. WABA details | `GET /{WABA-ID}?fields=…` | `WabaService::getWaba` (via `SyncWhatsAppAccount`) |
| 4. Subscribe webhooks | `POST /{WABA-ID}/subscribed_apps` | `WabaService::subscribeApp` |
| 5. Phone numbers | `GET /{WABA-ID}/phone_numbers` | `WabaService::phoneNumbers` |
| 6. (optional) Assign system user | `POST /{WABA-ID}/assigned_users?user=&tasks=` | `EmbeddedSignupService::addSystemUserToWaba` |
| 7. (solution partner) Share credit line | `POST /{Credit-Line-ID}/whatsapp_credit_sharing_and_attach?waba_id=&waba_currency=` | `EmbeddedSignupService::shareCreditLine` |
| 8. Register number (from account page) | `POST /{Phone-Number-ID}/register { messaging_product, pin }` | `RegisterPhoneNumber` |
| Verify ownership if needed | `POST /request_code`, `POST /verify_code` | `PhoneNumberController` |

Orchestration lives in `app/Actions/Onboarding/CompleteEmbeddedSignup.php`. Each API failure after the token
exchange is recorded as a warning (the account still exists so the step can be retried from the UI).
Tokens are stored encrypted (`encrypted` cast). Every popup event is logged in `signup_sessions`.

## 5. Messaging

`POST /{Phone-Number-ID}/messages` bodies are assembled by `app/Support/MessagePayloadBuilder.php` exactly as
in the *Messages* folder of the Cloud API collection (text / media by id or link / location / interactive /
template with header, body and button parameters). `SendMessage` stores the request, the response
(`contacts[].wa_id`, `messages[].id`) or the Graph error. Statuses (`sent → delivered → read | failed`) are
applied by `ProcessWhatsAppWebhook` from the `messages` webhook; inbound messages are stored too.
Media uploads use `POST /{Phone-Number-ID}/media` (multipart, `messaging_product=whatsapp`).

## 6. Templates

`app/Support/TemplatePayloadBuilder.php` builds `POST /{WABA-ID}/message_templates` bodies
(`HEADER` / `BODY` with `example.body_text` / `FOOTER` / `BUTTONS`, and `AUTHENTICATION` templates with
`OTP` buttons). Media headers use the Resumable Upload API (`POST /{app-id}/uploads` → `POST /{upload-id}`)
to obtain `example.header_handle`. Status changes arrive via `message_template_status_update`; you can also
*Sync from Meta* (`GET /{WABA-ID}/message_templates`) or refresh one template (`GET /{TEMPLATE_ID}`).

## 6b. Contacts, Inbox & the 24-hour window (Phase 2)

- **Contacts** (`/contacts`) – full CRUD (name, WhatsApp number, email, company, tags, notes, preferred sender).
  A number must exist as a contact before it can be messaged. Inbound messages from unknown numbers create the
  contact automatically (`source = inbound`, name from the WhatsApp profile).
- **Inbox** (`/inbox`) – WhatsApp-style two-pane chat: conversation list with unread counters and window status,
  bubbles with sent/delivered/read ticks, day separators, reply-to, media attachments and a template dialog.
  Live updates use polling of two JSON endpoints (`GET /inbox/conversations`, `GET /inbox/{contact}/messages?after=&since=`)
  every `WHATSAPP_INBOX_POLL_MS` (default 4000 ms), which works on shared hosting without websockets. Opening a chat
  clears the unread counter and sends a read receipt (`PUT /{Phone}/messages status=read`).
- **24-hour window** – enforced server-side by `App\Support\ConversationGuard` for both the inbox and the composer:
  - the first message to a contact must be an approved template;
  - a customer message opens/extends the window for 24 h (`window_opened_by = inbound`);
  - a successfully sent template also opens it (`window_opened_by = template`, toggle `WHATSAPP_TEMPLATE_OPENS_WINDOW`);
  - once expired, free-form sends are rejected with “24-hour window has been closed…” and the composer shows the
    template-only state until another template is sent or the customer writes again.

  Meta note: WhatsApp itself only guarantees free-form delivery inside a *customer-initiated* service window. If the
  customer never replies to a template, a free-form message may still be rejected by Meta (error 131047); the inbox
  shows that on the bubble.

### Template sync & scheduler

`php artisan engage:sync-templates` mirrors every WABA's templates and **removes** templates that Meta no longer
returns (deleted in WhatsApp Manager) or reports as `DELETED`. It is scheduled hourly; add the Laravel scheduler
cron on the server (every minute):

```
cd /path/to/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Template bubbles in the inbox render the parameters that were actually sent ("Hello Daniyal", not "Hello {{1}}")
via `App\Support\TemplateRenderer`, reading `payload.template.components` of the stored message.

### Profile pictures

The Cloud API does not expose customers' profile photos (only `contacts[].profile.name`), so the inbox shows
initials. A photo can be attached to a contact manually if needed.

## 7. Webhooks

`GET /webhooks/whatsapp` handles the `hub.challenge` handshake; `POST /webhooks/whatsapp` validates
`X-Hub-Signature-256` (HMAC‑SHA256 of the raw body with the app secret), stores every change in `webhook_events`
and dispatches `ProcessWhatsAppWebhook`. Run `php artisan queue:work`.

## 8. Project layout

```
app/
  Actions/            Onboarding (CompleteEmbeddedSignup, SyncWhatsAppAccount, RegisterPhoneNumber),
                      Messaging (SendMessage), Templates (SyncTemplates)
  Services/Meta/      GraphClient, EmbeddedSignupService, WabaService, MessagingService, TemplateService
  Support/            MessagePayloadBuilder, TemplatePayloadBuilder
  Http/Controllers/   Onboarding/, Messaging/, Templates/, Webhooks/, DashboardController
  Http/Requests/      Validation for signup callback, sending, template creation
  Jobs/               ProcessWhatsAppWebhook
  Models/             Workspace, WhatsAppAccount, PhoneNumber, SignupSession, Contact, Message,
                      MessageTemplate, MediaAsset, WebhookEvent
  Policies/           Workspace scoping
config/whatsapp.php   All Meta settings
database/migrations/  Schema (see file headers)
resources/js/pages/   onboarding/, accounts/, contacts/, inbox/, messages/, templates/, dashboard
resources/js/components/inbox/  conversation list, bubbles, composer, template dialog, window badge
resources/js/hooks/use-embedded-signup.ts   FB SDK + session logging + FB.login
tests/                Unit tests for both payload builders; feature tests for onboarding, registration, webhooks
```

## 9. Tests

```bash
php artisan test
```

## 10. Next phases (not in scope yet)

Shared inbox / realtime (Reverb), broadcast campaigns, template editing & appeals, analytics
(`GET /{WABA-ID}?fields=analytics`), QR codes, business profile, flows.
