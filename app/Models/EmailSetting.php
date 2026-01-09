<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSetting extends Model
{
    protected $table = 'email_settings';

    protected $fillable = [
        'enabled',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'from_address',
        'from_name',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'mail_port' => 'integer',
    ];
}
