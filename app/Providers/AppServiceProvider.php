<?php

namespace App\Providers;

use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // A 10s client timeout keeps a slow Brevo API from ever hanging
        // registration or an order-status update.
        Mail::extend('brevo', function (array $config = []) {
            $factory = new BrevoTransportFactory(client: HttpClient::create(['timeout' => 10]));

            return $factory->create(new Dsn(
                scheme: 'brevo+api',
                host: 'default',
                user: $config['key'] ?? null,
            ));
        });

        // Activity log: auth events. These all already fire on their own —
        // Auth::attempt() (used by LoginRequest::authenticate()) fires
        // Login/Failed/Logout natively, and Registered/Verified/PasswordReset
        // are already manually dispatched in the existing auth controllers —
        // so none of those controllers need to change for this.
        Event::listen(Login::class, function (Login $event) {
            ActivityLogger::log(
                'auth.login',
                'auth',
                "{$event->user->name} logged in",
                actor: $event->user,
            );
        });

        Event::listen(Logout::class, function (Logout $event) {
            if (!$event->user) {
                return;
            }
            ActivityLogger::log(
                'auth.logout',
                'auth',
                "{$event->user->name} logged out",
                actor: $event->user,
            );
        });

        Event::listen(Failed::class, function (Failed $event) {
            ActivityLogger::logFailedLogin($event->credentials['email'] ?? 'unknown');
        });

        Event::listen(Registered::class, function (Registered $event) {
            ActivityLogger::log(
                'auth.registered',
                'auth',
                "{$event->user->name} registered a new customer account",
                actor: $event->user,
            );
        });

        Event::listen(Verified::class, function (Verified $event) {
            ActivityLogger::log(
                'auth.email_verified',
                'auth',
                "{$event->user->name} verified their email",
                actor: $event->user,
            );
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event) {
            ActivityLogger::log(
                'auth.password_reset',
                'auth',
                "{$event->user->name} reset their password",
                actor: $event->user,
            );
        });
    }
}
