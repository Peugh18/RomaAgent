<?php

namespace App\Services\Agente\Tools;

use App\Models\ZonaEnvio;

class ConsultarCoberturaTool
{
    /**
     * @return array{name: string, description: string, parameters: array<string, mixed>}
     */
    public static function definition(): array
    {
        return [
            'name' => 'consultar_cobertura',
            'description' => 'Consulta en la base de datos logística si un distrito tiene cobertura Motorizado o si debe enviarse por Shalom. Úsalo siempre después de preguntar el distrito a la clienta para saber cómo proceder.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'distrito' => [
                        'type' => 'string',
                        'description' => 'El distrito que indicó la clienta (ej: Los Olivos, Arequipa, San Isidro, etc.)',
                    ],
                    'provincia' => [
                        'type' => 'string',
                        'description' => 'La provincia, si la clienta la proporcionó. Opcional.',
                    ],
                ],
                'required' => ['distrito'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public static function execute(array $args): array
    {
        $rawInput = trim((string) ($args['distrito'] ?? ''));
        $inputProvincia = trim((string) ($args['provincia'] ?? ''));

        $extractedDistrito = '';
        $extractedProvincia = $inputProvincia;
        $extractedDepartamento = '';

        $departamentosPeru = [
            'lima', 'arequipa', 'la libertad', 'ancash', 'piura', 'cajamarca', 'lambayeque',
            'junin', 'cusco', 'ica', 'tacna', 'loreto', 'san martin', 'huanuco', 'ayacucho',
            'ucayali', 'puno', 'huancavelica', 'amazonas', 'apurimac', 'pasco', 'tumbes',
            'moquegua', 'madre de dios', 'callao', 'junín', 'huánuco', 'san martín', 'apurímac',
        ];

        if (str_contains($rawInput, ',')) {
            $parts = array_map('trim', explode(',', $rawInput));
            $candidateParts = [];
            foreach ($parts as $part) {
                if (in_array(mb_strtolower($part), $departamentosPeru, true)) {
                    $extractedDepartamento = $part;
                } else {
                    $candidateParts[] = $part;
                }
            }

            if (count($candidateParts) >= 2) {
                $extractedDistrito = $candidateParts[0];
                $extractedProvincia = $candidateParts[1];
            } elseif (count($candidateParts) === 1) {
                $extractedDistrito = $candidateParts[0];
            } else {
                $extractedDistrito = $rawInput; // Fallback
            }
        } else {
            $extractedDistrito = $rawInput;
        }

        if ($extractedDistrito === '') {
            return [
                'ok' => false,
                'error' => 'Debes proporcionar un distrito para consultar la cobertura.',
            ];
        }

        $query = ZonaEnvio::query()
            ->where('activo', true)
            ->where('distrito', 'like', "%{$extractedDistrito}%");

        if ($extractedProvincia !== '') {
            $query->where(function ($q) use ($extractedProvincia) {
                $q->where('provincia', 'like', "%{$extractedProvincia}%")
                    ->orWhere('departamento', 'like', "%{$extractedProvincia}%");
            });
        }

        $zona = $query->first();

        if ($zona) {
            // Existe en zonas_envio -> Motorizado (o el tipo que esté configurado ahí)
            $tipoEnvio = $zona->tipo_envio;
            $costo = (float) $zona->costo_referencial;

            return [
                'ok' => true,
                'encontrado' => true,
                'departamento' => $zona->departamento,
                'provincia' => $zona->provincia,
                'distrito' => $zona->distrito,
                'tipo_envio' => $tipoEnvio,
                'costo_referencial' => $costo,
                'instruccion_para_ia' => "Se encontró cobertura local. El envío es '{$tipoEnvio}'. El costo es S/ {$costo}. "
                    .'OBLIGATORIO: Infórmale esto a la clienta y aclárale que el costo es referencial e informativo. '
                    .'Pídele los datos que falten: Nombre completo, Celular, Dirección Exacta y (opcionalmente) ubicación. '
                    ."IMPORTANTE: NO llames a 'actualizar_pedido' todavía. Solo respóndele a la clienta con texto. Cuando ella te responda con sus datos en el siguiente mensaje, recién ahí usarás 'actualizar_pedido' para guardar todo.",
            ];
        }

        // No existe en zonas_envio -> Shalom
        return [
            'ok' => true,
            'encontrado' => false,
            'distrito_buscado' => $extractedDistrito,
            'distrito' => $extractedDistrito,
            'provincia' => $extractedProvincia,
            'departamento' => $extractedDepartamento,
            'tipo_envio' => 'shalom',
            'costo_referencial' => 'Pago en destino',
            'instruccion_para_ia' => "No se encontró el distrito en la zona de reparto local, por lo tanto el envío es por 'shalom' con Pago en destino. OBLIGATORIO: Infórmale a la clienta que para su ciudad los envíos se hacen por Shalom y el flete se paga al recoger. Pídele los datos que falten: Nombre completo, DNI, Celular y la Sede de Shalom. IMPORTANTE: NO llames a 'actualizar_pedido' todavía. Solo respóndele a la clienta con texto. Cuando ella te dé los datos en su próximo mensaje, recién usarás 'actualizar_pedido'.",
        ];
    }
}
