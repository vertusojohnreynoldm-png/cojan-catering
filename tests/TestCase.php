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

        return parent::post($uri, $data, $headers);
    }
}
