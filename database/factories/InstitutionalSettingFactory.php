<?php

namespace Database\Factories;

use App\Models\InstitutionalSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstitutionalSetting>
 */
class InstitutionalSettingFactory extends Factory
{
    protected $model = InstitutionalSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
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
            'tiktok_url' => null,
            'spotify_url' => null,
            'linkedin_url' => null,
            'updated_by' => null,
        ];
    }
}
