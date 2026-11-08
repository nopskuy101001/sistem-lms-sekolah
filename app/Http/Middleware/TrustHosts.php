<?php

namespace App\Http\Middleware;

class TrustHosts extends \Illuminate\Http\Middleware\TrustHosts
{
    /**
     * Enforce the same exact-host policy in local and test environments so a
     * deployment cannot silently become permissive because APP_ENV is wrong.
     */
    protected function shouldSpecifyTrustedHosts(): bool
    {
        return true;
    }
}
