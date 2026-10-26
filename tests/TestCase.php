<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Eviction and fake resets must not touch local users' artifacts or
        // files belonging to another PHP test process in this workspace.
        Storage::set('cv_pdfs', Storage::fake('cv_pdfs_'.getmypid(), config('filesystems.disks.cv_pdfs')));

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
