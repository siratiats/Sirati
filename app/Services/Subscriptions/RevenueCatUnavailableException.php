<?php

namespace App\Services\Subscriptions;

use RuntimeException;

/** RevenueCat could not be read; the caller must not assume any access state. */
class RevenueCatUnavailableException extends RuntimeException {}
