<?php

namespace Tests\Unit;

use App\Support\WhatsappSendError;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WhatsappSendErrorTest extends TestCase
{
    public function test_detects_permanent_meta_access_denied_errors(): void
    {
        $exception = new RuntimeException('Roma API send failed (500): (#131005) Access denied');

        $this->assertTrue(WhatsappSendError::isPermanent($exception));
    }

    public function test_temporary_network_errors_are_retryable(): void
    {
        $exception = new RuntimeException('Connection timed out');

        $this->assertFalse(WhatsappSendError::isPermanent($exception));
    }

    public function test_user_message_translates_meta_token_errors(): void
    {
        $exception = new RuntimeException('Roma API send failed (500): (#131005) Access denied');

        $this->assertStringContainsString('token de Meta', WhatsappSendError::userMessage($exception));
    }
}
