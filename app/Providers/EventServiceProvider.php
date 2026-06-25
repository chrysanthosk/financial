<?php

namespace App\Providers;

use App\Listeners\AuditAuthFailed;
use App\Listeners\AuditAuthLogin;
use App\Listeners\AuditAuthLogout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            AuditAuthLogin::class,
        ],
        Logout::class => [
            AuditAuthLogout::class,
        ],
        Failed::class => [
            AuditAuthFailed::class,
        ],
    ];
}
