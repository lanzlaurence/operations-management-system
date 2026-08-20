<?php

namespace Database\Seeders;

use App\Models\Preference;
use Illuminate\Database\Seeder;

class PreferenceSeeder extends Seeder
{
    public function run(): void
    {
        $preferences = [
            ['key' => 'app_name', 'value' => 'Operations Management System', 'type' => 'text'],
            ['key' => 'app_logo', 'value' => 'default-logo.jpg', 'type' => 'image'],
            ['key' => 'decimal_places', 'value' => '2', 'type' => 'number'],
            ['key' => 'color_theme', 'value' => 'zinc', 'type' => 'text'],
            ['key' => 'timezone', 'value' => 'Asia/Manila', 'type' => 'text'],
            ['key' => 'currency', 'value' => 'PHP', 'type' => 'text'],
            ['key' => 'date_format', 'value' => 'MM/DD/YYYY'],
            ['key' => 'time_format', 'value' => '12h'],
        ];

        foreach ($preferences as $preference) {
            Preference::updateOrCreate(
                ['key' => $preference['key']],
                $preference
            );
        }
    }
}
