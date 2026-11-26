<?php

namespace Tests\Unit\Models;

use App\Enums\JubanReportStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use PHPUnit\Framework\TestCase;

class JubanReportStatusFlowTest extends TestCase
{
    public function test_enum_implements_filament_contracts(): void
    {
        $this->assertInstanceOf(HasLabel::class, JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL);
        $this->assertInstanceOf(HasColor::class, JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL);
        $this->assertInstanceOf(HasIcon::class, JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL);
    }

    public function test_enum_contains_all_flow_cases(): void
    {
        $expected = [
            'aguardando_tesouraria',
            'aguardando_presidencia',
            'aguardando_conselho_fiscal',
            'em_revisao',
            'aprovado',
            'aprovado_com_ressalvas',
            'reprovado',
            'assinado',
            'reaberto',
        ];

        $actual = array_map(fn (JubanReportStatus $status) => $status->value, JubanReportStatus::cases());

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function test_is_locked_locks_all_statuses_except_reaberto(): void
    {
        $this->assertTrue(JubanReportStatus::AGUARDANDO_TESOURARIA->isLocked());
        $this->assertTrue(JubanReportStatus::AGUARDANDO_PRESIDENCIA->isLocked());
        $this->assertTrue(JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL->isLocked());
        $this->assertTrue(JubanReportStatus::EM_REVISAO->isLocked());
        $this->assertTrue(JubanReportStatus::APROVADO->isLocked());
        $this->assertTrue(JubanReportStatus::APROVADO_COM_RESSALVAS->isLocked());
        $this->assertTrue(JubanReportStatus::REPROVADO->isLocked());
        $this->assertTrue(JubanReportStatus::ASSINADO->isLocked());

        $this->assertFalse(JubanReportStatus::REABERTO->isLocked());
    }

    public function test_visibility_to_fiscal_council(): void
    {
        $this->assertFalse(JubanReportStatus::AGUARDANDO_TESOURARIA->isVisibleToFiscalCouncil());
        $this->assertFalse(JubanReportStatus::AGUARDANDO_PRESIDENCIA->isVisibleToFiscalCouncil());
        $this->assertFalse(JubanReportStatus::REABERTO->isVisibleToFiscalCouncil());

        $this->assertTrue(JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL->isVisibleToFiscalCouncil());
        $this->assertTrue(JubanReportStatus::EM_REVISAO->isVisibleToFiscalCouncil());
        $this->assertTrue(JubanReportStatus::APROVADO->isVisibleToFiscalCouncil());
        $this->assertTrue(JubanReportStatus::APROVADO_COM_RESSALVAS->isVisibleToFiscalCouncil());
        $this->assertTrue(JubanReportStatus::REPROVADO->isVisibleToFiscalCouncil());
        $this->assertTrue(JubanReportStatus::ASSINADO->isVisibleToFiscalCouncil());
    }

    public function test_labels_colors_and_icons_are_defined(): void
    {
        foreach (JubanReportStatus::cases() as $status) {
            $this->assertNotEmpty($status->getLabel());
            $this->assertNotNull($status->getColor());
            $this->assertNotEmpty($status->getIcon());
        }
    }
}
