<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
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
    }
}
