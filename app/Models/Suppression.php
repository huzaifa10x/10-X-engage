<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Hard send-block checked before every business-initiated message. */
class Suppression extends Model
{
    protected $table = 'suppression_list';

    protected $fillable = ['workspace_id', 'wa_id', 'reason', 'user_id'];
}
