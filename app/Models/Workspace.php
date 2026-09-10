<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Workspace extends Model
{
    protected $fillable = ['name', 'slug', 'owner_id', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function whatsappAccounts(): HasMany
    {
        return $this->hasMany(WhatsAppAccount::class);
    }

    public function phoneNumbers(): HasManyThrough
    {
        return $this->hasManyThrough(PhoneNumber::class, WhatsAppAccount::class, 'workspace_id', 'whatsapp_account_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function signupSessions(): HasMany
    {
        return $this->hasMany(SignupSession::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }
}
