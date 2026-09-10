<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppAccount;

class WhatsAppAccountPolicy
{
    public function view(User $user, WhatsAppAccount $account): bool
    {
        return $user->workspace_id === $account->workspace_id;
    }

    public function update(User $user, WhatsAppAccount $account): bool
    {
        return $user->workspace_id === $account->workspace_id;
    }

    public function delete(User $user, WhatsAppAccount $account): bool
    {
        return $user->workspace_id === $account->workspace_id;
    }
}
