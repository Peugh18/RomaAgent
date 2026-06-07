<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RecordatoriosPedidoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_recordatorios_command_is_registered(): void
    {
        $this->assertSame(0, Artisan::call('pedidos:recordatorios'));
    }
}
