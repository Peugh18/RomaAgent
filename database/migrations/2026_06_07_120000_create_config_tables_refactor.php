<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla base simplificada - solo identificador
        Schema::table('company_settings', function (Blueprint $table) {
            // Mantener solo campos esenciales de identificación
            // Los demás se migrarán a tablas especializadas
        });

        // 2. Configuración del Agente IA (antes: 6 campos en CompanySetting)
        Schema::create('agente_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_setting_id')->constrained()->cascadeOnDelete();

            // Configuración IA
            $table->boolean('activado')->default(false);
            $table->string('modelo')->default('gemini-2.5-flash-lite');
            $table->text('api_key_encrypted')->nullable();
            $table->decimal('temperatura', 3, 2)->default(0.70);

            // Personalidad (antes estaban en CompanySetting)
            $table->string('tono_bot')->nullable();
            $table->text('estilo_comunicacion')->nullable();
            $table->text('personalidad_bot')->nullable();
            $table->text('respuesta_si_es_bot')->nullable();

            $table->timestamps();

            $table->unique('company_setting_id');
        });

        // 3. Mensajes Automáticos (antes: 8 campos en CompanySetting)
        Schema::create('mensaje_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_setting_id')->constrained()->cascadeOnDelete();

            // Mensajes de interacción
            $table->text('saludo_inicial')->nullable();
            $table->text('reglas_comunicacion')->nullable();
            $table->text('flujo_ventas')->nullable();

            // Recordatorios
            $table->text('recordatorio_3min')->nullable();
            $table->text('recordatorio_15min')->nullable();
            $table->text('recordatorio_datos')->nullable();

            // Mensajes de pedido
            $table->text('pedido_confirmado')->nullable();
            $table->text('pedido_enviado')->nullable();
            $table->text('pedido_entregado')->nullable();
            $table->text('comprobante_recibido')->nullable();
            $table->text('comprobante_fuera_horario')->nullable();
            $table->text('espera_link_tarjeta')->nullable();

            $table->timestamps();

            $table->unique('company_setting_id');
        });

        // 4. Configuración de Ventas (antes: 4 campos)
        Schema::create('venta_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_setting_id')->constrained()->cascadeOnDelete();

            $table->string('moneda')->default('PEN');
            $table->json('metodos_pago')->nullable();
            $table->decimal('comision_tarjeta', 5, 2)->default(0);
            $table->string('formato_registro_venta')->default('formato_simple');
            $table->text('protocolo_traspaso')->nullable();

            $table->timestamps();

            $table->unique('company_setting_id');
        });

        // 5. Información de Empresa (antes: 12 campos mezclados)
        Schema::create('empresa_info_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_setting_id')->constrained()->cascadeOnDelete();

            // Datos básicos
            $table->string('company_name');
            $table->string('ruc')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('celular')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();

            // Información adicional
            $table->text('actividad_economica')->nullable();
            $table->text('informacion_adicional')->nullable();
            $table->json('social_networks')->nullable();
            $table->text('address')->nullable();

            $table->timestamps();

            $table->unique('company_setting_id');
        });

        // 6. Horarios y Delivery (antes: 6 campos)
        Schema::create('horario_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_setting_id')->constrained()->cascadeOnDelete();

            $table->text('horario_atencion')->nullable();
            $table->text('horario_entregas')->nullable();
            $table->text('horario_shalom')->nullable();
            $table->text('politica_devoluciones')->nullable();
            $table->text('restricciones_especiales')->nullable();
            $table->json('plantillas_datos')->nullable();
            $table->string('standard_size')->nullable();

            $table->timestamps();

            $table->unique('company_setting_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horario_configs');
        Schema::dropIfExists('empresa_info_configs');
        Schema::dropIfExists('venta_configs');
        Schema::dropIfExists('mensaje_configs');
        Schema::dropIfExists('agente_configs');
    }
};
