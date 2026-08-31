<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        $promocodes = [
            [
                'code' => 'WELCOME10',
                'type' => 'percent',
                'value' => 10,
                'currency' => null,
                'max_uses' => 100,
            ],
            [
                'code' => 'GG500',
                'type' => 'amount',
                'value' => 500,
                'currency' => 'RUB',
                'max_uses' => 20,
            ],
            [
                'code' => 'LIMIT3',
                'type' => 'percent',
                'value' => 25,
                'currency' => null,
                'max_uses' => 3,
            ],
            [
                'code' => 'ONCEONLY',
                'type' => 'percent',
                'value' => 50,
                'currency' => null,
                'max_uses' => 1,
            ],
        ];

        foreach ($promocodes as $promocode) {
            PromoCode::updateOrCreate(
                ['code' => $promocode['code']],
                $promocode,
            );
        }
    }
}
