<?php

namespace Database\Seeders;

use App\Enums\PublishStatus;
use App\Models\Statute;
use Illuminate\Database\Seeder;

class StatuteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $xmlPath = database_path('seeders/data/estatuto-2025-10-18.akn.xml');

        if (! file_exists($xmlPath)) {
            return;
        }

        $aknXml = file_get_contents($xmlPath);

        if (Statute::query()->where('version_label', '2025-10-18')->doesntExist()) {
            $hasPublished = Statute::query()->published()->exists();

            Statute::create([
                'title' => 'Estatuto da Convenção Batista Nacional do Estado de Goiás',
                'version_label' => '2025-10-18',
                'approved_at' => '2025-10-18',
                'approved_place' => 'Anápolis, GO',
                'approval_body' => 'Assembleia Geral',
                'minutes_reference' => 'Ata da Assembleia Geral Extraordinária de 18 de outubro de 2025',
                'registry_info' => 'Cartório de Pessoas Jurídicas (Averbação à margem do registro nº 806 em 22/01/2026, protocolo 13618453 de 18/12/2025)',
                'effective_at' => '2025-10-18',
                'status' => $hasPublished ? PublishStatus::ARCHIVED : PublishStatus::PUBLISHED,
                'akn_xml' => $aknXml,
                'original_pdf' => 'statutes/04-estatuto-cbngo-atualizado.pdf',
                'published_at' => '2025-10-18 00:00:00',
            ]);
        }
    }
}
