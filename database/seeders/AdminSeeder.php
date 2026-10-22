<?php

namespace Database\Seeders;

use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * The repeated-digit placeholders earlier versions of this seeder used.
     * PhilippineMobileNumber rejects them, so a seeded customer couldn't
     * check out without first editing their phone.
     */
    private const OLD_PLACEHOLDER_PHONES = ['09000000000', '09111111111', '09222222222'];

    public function run(): void
    {
        $accounts = [
            [
                'name'     => 'Admin',
                'email'    => 'admin@cojan.com',
                'password' => 'admin123',
                'role'     => 'admin',
                'phone'    => '09171234501',
                'address'  => 'Cojan Catering Services, Baguio City',
            ],
            [
                'name'     => 'John Customer',
                'email'    => 'customer@cojan.com',
                'password' => 'customer123',
                'role'     => 'customer',
                'phone'    => '09171234502',
                'address'  => 'Sample Address, Baguio City',
            ],
            [
                'name'     => 'Juan Delivery',
                'email'    => 'delivery@cojan.com',
                'password' => 'delivery123',
                'role'     => 'delivery',
                'phone'    => '09171234503',
                'address'  => 'Sample Address, Baguio City',
            ],
        ];

        foreach ($accounts as $account) {
            // Fail loudly here rather than seed a number checkout rejects.
            $check = Validator::make(['phone' => $account['phone']], ['phone' => [new PhilippineMobileNumber]]);
            if ($check->fails()) {
                throw new RuntimeException("Seeded phone for {$account['email']} is invalid: " . $check->errors()->first('phone'));
            }

            $user = User::where('email', $account['email'])->first();

            if (!$user) {
                User::create([
                    'name'              => $account['name'],
                    'email'             => $account['email'],
                    'password'          => Hash::make($account['password']),
                    'role'              => $account['role'],
                    'phone'             => $account['phone'],
                    'address'           => $account['address'],
                    // Demo accounts must be able to order straight away —
                    // checkout and Avail require a verified email.
                    'email_verified_at' => now(),
                ]);

                continue;
            }

            // Safe to re-run on an existing database: never touches the
            // password, name or anything else — it only replaces one of the
            // old invalid placeholder phones with the valid one.
            if (in_array($user->phone, self::OLD_PLACEHOLDER_PHONES, true)) {
                $user->update(['phone' => $account['phone']]);
            }
        }
    }
}
