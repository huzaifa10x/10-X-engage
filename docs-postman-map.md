# Postman collection → code map

| Collection / folder | Request | Method + path | Implemented in |
|---|---|---|---|
| Embedded Signup / WABAs | Debug Token | GET /debug_token | `EmbeddedSignupService::debugToken` |
| Embedded Signup / WABAs | Shared WABAs | GET /{Business-ID}/client_whatsapp_business_accounts | `EmbeddedSignupService::sharedWabas` |
| Embedded Signup / WABAs | Owned and Client WABAs | GET /{Business-ID}/owned_whatsapp_business_accounts | `EmbeddedSignupService::ownedWabas` |
| Embedded Signup / WABAs | WABA ID | GET /{Assigned-WABA-ID} | `WabaService::getWaba` |
| Embedded Signup / Step 2 | Get IDs for the BSP's System Users | GET /{Business-ID}/system_users | `EmbeddedSignupService::systemUsers` |
| Embedded Signup / Step 2 | Add System User to WABA | POST /{WABA}/assigned_users?user=&tasks= | `EmbeddedSignupService::addSystemUserToWaba` |
| Embedded Signup / Step 2 | Fetch Assigned Users | GET /{WABA}/assigned_users?business= | `EmbeddedSignupService::assignedUsers` |
| Embedded Signup / Step 2 | Subscribe App to WABA | POST /{WABA}/subscribed_apps | `WabaService::subscribeApp` |
| Embedded Signup / Step 3 | Get credit line ID | GET /{Business-ID}/extendedcredits | `EmbeddedSignupService::extendedCredits` |
| Embedded Signup / Step 3 | Attach credit line | POST /{Credit-Line-ID}/whatsapp_credit_sharing_and_attach | `EmbeddedSignupService::shareCreditLine` |
| Embedded Signup / Step 3 | Verify credit share | GET /{Allocation-Config-ID}?fields=receiving_credential{id} | `EmbeddedSignupService::verifyCreditShare` |
| Embedded Signup / Lines of Credit | Credit Sharing Record (GET / DELETE) | /{Allocation-Config-ID} | `creditShareStatus`, `revokeCreditShare` |
| Embedded Signup / WABA Subscriptions | Get / Delete / Override | /{WABA}/subscribed_apps | `WabaService::subscriptions`, `unsubscribeApp`, `subscribeApp(override…)` |
| Embedded Signup | Phone Numbers | GET /{WABA}/phone_numbers | `WabaService::phoneNumbers` |
| Embedded Signup / Message Templates | Fetch templates / namespace | GET /{WABA}/message_templates, ?fields=message_template_namespace | `TemplateService::all`, `namespace` |
| Embedded Signup docs (Step 1) | Exchange code (server‑to‑server) | GET /oauth/access_token | `EmbeddedSignupService::exchangeCode` |
| Cloud API / Get Started | Register Phone Number | POST /{Phone}/register | `WabaService::registerPhone` |
| Cloud API / Registration | Deregister | POST /{Phone}/deregister | `WabaService::deregisterPhone` |
| Cloud API / Phone Numbers | Get by ID | GET /{Phone} | `WabaService::phoneNumber` |
| Cloud API / Phone Numbers | Request / Verify code | POST /{Phone}/request_code, /verify_code | `requestVerificationCode`, `verifyCode` |
| Cloud API / Phone Numbers | Set two‑step PIN | POST /{Phone} { pin } | `WabaService::setTwoStepPin` |
| Cloud API / Messages | Send text / reply / preview URL | POST /{Phone}/messages | `MessagePayloadBuilder::text` |
| Cloud API / Messages | Send image/audio/document/sticker/video by ID or URL | POST /{Phone}/messages | `MessagePayloadBuilder::media` |
| Cloud API / Messages | Send location | POST /{Phone}/messages | `MessagePayloadBuilder::location` |
| Cloud API / Messages | Send reply button / list | POST /{Phone}/messages | `replyButtons`, `list` |
| Cloud API / Messages | Send template text / media / interactive | POST /{Phone}/messages | `template` + `templateComponents` |
| Cloud API / Messages | Mark as read | PUT /{Phone}/messages | `MessagingService::markAsRead` |
| Cloud API / Media | Upload media | POST /{Phone}/media (multipart) | `MessagingService::uploadMedia` → `MediaController` |
| Cloud API / Media | Retrieve / delete media | GET, DELETE /{Media-ID} | `mediaUrl`, `deleteMedia` |
| Cloud API / Templates | Get all / by name / by ID / namespace | GET … | `TemplateService::all`, `byName`, `byId`, `namespace` |
| Cloud API / Templates | Create (auth, text, image, location, document…) | POST /{WABA}/message_templates | `TemplateService::create` + `TemplatePayloadBuilder` |
| Cloud API / Templates | Edit | POST /{TEMPLATE_ID} | `TemplateService::update` |
| Cloud API / Templates | Delete by name / by ID | DELETE /{WABA}/message_templates | `deleteByName`, `deleteById` |
| Business Mgmt / Media | Resumable upload steps 1–2 | POST /{app-id}/uploads, POST /{upload-id} | `TemplateService::uploadHeaderMedia` → `TemplateMediaController` |
| Webhooks | messages (statuses + inbound), template status, account update/review, phone name/quality | — | `ProcessWhatsAppWebhook` |
