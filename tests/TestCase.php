<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function promptCompleto(Authenticatable $user): string
    {
        return (string) $this->actingAs($user)
            ->getJson('/api/company-settings/prompt-completo')
            ->json('prompt_completo');
    }
}
