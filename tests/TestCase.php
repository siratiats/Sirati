<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic baseline: test outcomes must not depend on whether the
        // machine running the suite happens to export MONETIZATION_ENABLED.
        // Tests that care about the paywall opt in with enforceMonetization();
        // SubscriptionPaywallTest covers every gated boundary in both states.
        config(['monetization.enabled' => false]);
    }

    protected function enforceMonetization(): void
    {
        config(['monetization.enabled' => true]);
    }
}
