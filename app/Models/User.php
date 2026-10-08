<?php

namespace App\Models;

use App\Mail\VerifyEmailMail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'address',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isDelivery(): bool
    {
        return $this->role === 'delivery';
    }

    /**
     * Overrides MustVerifyEmail's default, which sends Laravel's generic
     * Illuminate\Auth\Notifications\VerifyEmail notification. Sent via a
     * custom Mailable instead, matching the same Mailable+Blade pattern
     * already used for WelcomeMail/OrderStatusMail, so there's one
     * consistent way mail gets built and sent across the app rather than
     * two (Mailables here, Notifications there).
     */
    public function sendEmailVerificationNotification(): void
    {
        // Same signed-URL construction Illuminate\Auth\Notifications\VerifyEmail
        // uses internally — same route, same 60-minute default expiry.
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id'   => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ]
        );

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($this->email)->send(new VerifyEmailMail($this, $verificationUrl));
        } catch (Throwable $e) {
            Log::error('Failed to send verification email', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}