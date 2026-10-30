<?php

namespace Database\Seeders;

use App\Models\InstitutionalSetting;
use Illuminate\Database\Seeder;

class InstitutionalSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (InstitutionalSetting::query()->exists()) {
            return;
        }

        InstitutionalSetting::create([
            'email' => 'contato@cbngo.org.br',
            'phone' => '(62) 3223-0000',
            'whatsapp' => '(62) 98100-0000',
            'office_hours' => 'Segunda a Sexta, das 08h às 17h',
            'street' => 'Rua 04',
            'number' => '515',
            'complement' => '12º andar, sala 1.207, Edifício Parthenon Center',
            'district' => 'Setor Central',
            'city' => 'Goiânia',
            'state' => 'GO',
            'postal_code' => '74020-045',
            'maps_url' => 'https://maps.google.com/?q=Rua+04,+515+-+Setor+Central,+Goiânia+-+GO,+74020-045',
            'instagram_url' => 'https://www.instagram.com/cbngoias/',
            'facebook_url' => 'https://www.facebook.com/cbngoias/',
            'youtube_url' => 'https://www.youtube.com/@cbngoias',
        ]);
    }
}
