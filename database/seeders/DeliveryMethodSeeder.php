<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $config = \App\Models\CompanySetting::first();
        if (!$config || !is_array($config->plantillas_datos)) {
            return;
        }

        foreach (['motorizado', 'shalom'] as $type) {
            if (isset($config->plantillas_datos[$type]) && is_array($config->plantillas_datos[$type])) {
                $method = \App\Models\DeliveryMethod::firstOrCreate([
                    'name' => ucfirst($type)
                ]);

                $order = 0;
                foreach ($config->plantillas_datos[$type] as $key => $field) {
                    \App\Models\DeliveryMethodField::firstOrCreate([
                        'delivery_method_id' => $method->id,
                        'name' => $field,
                        'sort_order' => $order++
                    ]);
                }
            }
        }
    }
}
