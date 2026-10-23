<?php

namespace Tests;

use App\Services\SubmissionToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Order-creating endpoints => the SubmissionToken form name they expect. */
    private const TOKEN_FORMS = [
        '/customer/orders'           => 'checkout',
        '/customer/packages/checkout' => 'package_checkout',
    ];

    /**
     * Real forms always carry a one-time submission_token (see
     * App\Services\SubmissionToken). Tests that aren't about that token
     * shouldn't each have to fetch one, so a fresh token is issued and added
     * here whenever a test POSTs to an order-creating URL WITHOUT one. Tests
     * that are about the token pass 'submission_token' explicitly (even ''),
     * which is left exactly as given.
     */
    public function post($uri, array $data = [], array $headers = [])
    {
        if (isset(self::TOKEN_FORMS[$uri]) && !array_key_exists('submission_token', $data)) {
            $data['submission_token'] = SubmissionToken::issue(self::TOKEN_FORMS[$uri]);
        }

        // Menu checkout requires a map pin; tests that aren't about the pin
        // get San Jose's town centre. Tests about the pin pass 'latitude'
        // explicitly (even '') and are left as given.
        if ($uri === '/customer/orders' && !array_key_exists('latitude', $data)) {
            [$data['latitude'], $data['longitude']] = \App\Services\DeliveryLocation::townCenter();
        }

        return parent::post($uri, $data, $headers);
    }
}
