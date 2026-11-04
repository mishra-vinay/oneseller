<?php

namespace Tests\Feature\Api;

/**
 * In tests the app instance is shared across requests, so Sanctum's guard would keep the
 * previous token's user, and the default guard stays switched to Sanctum. Real requests
 * start fresh; this makes each test request do the same, while web actingAs() users stay.
 */
trait ResetsApiGuards
{
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->guard('sanctum')->forgetUser();
        $this->app['auth']->shouldUse(config('auth.defaults.guard'));

        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            // So a later actingAs() signs in on the web guard again.
            $this->app['auth']->shouldUse(config('auth.defaults.guard'));
        }
    }
}
