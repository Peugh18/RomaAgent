<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campos específicos para configuración de flujos de venta personalizados.
     * Estructura: Saludos → Reglas → Flujo → Plantillas → Horarios → Protocolos
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // === SALUDOS Y PRESENTACIÓN ===
            $table->text('saludo_inicial')->nullable()->after('informacion_adicional')
                ->comment('Saludo personalizado al iniciar chat. Ej: "Hermosa muchas gracias..."');

            // === REGLAS DE COMUNICACIÓN ===
            $table->text('reglas_comunicacion')->nullable()->after('saludo_inicial')
                ->comment('Reglas críticas: formato, emojis, lenguaje, etc');

            // === FLUJO DE VENTAS ===
            $table->text('flujo_ventas')->nullable()->after('reglas_comunicacion')
                ->comment('Pasos del flujo: reconocimiento, stock, confirmación, etc');

            // === PLANTILLAS DE RECOLECCIÓN DE DATOS ===
            $table->json('plantillas_datos')->nullable()->after('flujo_ventas')
                ->comment('Plantillas para motorizado y shalom con campos específicos');

            // === HORARIOS Y TIEMPOS ===
            $table->string('horario_entregas')->nullable()->after('plantillas_datos')
                ->comment('Horario de entregas. Ej: "Lunes a Sábado 5pm-9pm"');

            $table->string('horario_shalom')->nullable()->after('horario_entregas')
                ->comment('Horario Shalom. Ej: "Lunes, Miércoles, Viernes"');

            // === PROTOCOLOS Y CONTINGENCIAS ===
            $table->text('protocolo_traspaso')->nullable()->after('horario_shalom')
                ->comment('Mensaje y procedimiento para traspasar a agente humano');

            $table->text('mensaje_recordatorio_3min')->nullable()->after('protocolo_traspaso')
                ->comment('Recordatorio si no responde en 3 minutos');

            $table->text('mensaje_recordatorio_15min')->nullable()->after('mensaje_recordatorio_3min')
                ->comment('Recordatorio si no responde en 15 minutos (despedida)');

            // === INFORMACIÓN DE CONTACTO PARA PAGOS ===
            $table->string('titular_yape')->nullable()->after('mensaje_recordatorio_15min')
                ->comment('Nombre del titular para Yape');

            $table->string('numero_yape')->nullable()->after('titular_yape')
                ->comment('Número de Yape para pagos');

            $table->decimal('comision_tarjeta', 5, 2)->default(5.00)->after('numero_yape')
                ->comment('Porcentaje de comisión por pago con tarjeta');

            // === INFORMACIÓN ADICIONAL DE ENTREGAS ===
            $table->json('tarifario_motorizado')->nullable()->after('comision_tarjeta')
                ->comment('Tarifario por distrito para motorizado');

            $table->decimal('tarifa_shalom_lima', 5, 2)->nullable()->after('tarifario_motorizado')
                ->comment('Tarifa Shalom para Lima');

            $table->decimal('tarifa_shalom_provincia', 5, 2)->nullable()->after('tarifa_shalom_lima')
                ->comment('Tarifa Shalom para provincia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'saludo_inicial',
                'reglas_comunicacion',
                'flujo_ventas',
                'plantillas_datos',
                'horario_entregas',
                'horario_shalom',
                'protocolo_traspaso',
                'mensaje_recordatorio_3min',
                'mensaje_recordatorio_15min',
                'titular_yape',
                'numero_yape',
                'comision_tarjeta',
                'tarifario_motorizado',
                'tarifa_shalom_lima',
                'tarifa_shalom_provincia',
            ]);
        });
    }
};
