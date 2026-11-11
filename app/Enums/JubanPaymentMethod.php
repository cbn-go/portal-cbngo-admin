<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JubanPaymentMethod: string implements HasLabel
{
    case PIX = 'pix';
    case TRANSFERENCIA = 'transferencia';
    case DINHEIRO = 'dinheiro';
    case CARTAO_DEBITO = 'cartao_debito';
    case CARTAO_CREDITO = 'cartao_credito';
    case BOLETO = 'boleto';
    case CHEQUE = 'cheque';
    case OUTRO = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::PIX => 'PIX',
            self::TRANSFERENCIA => 'Transferência Bancária (TED/DOC)',
            self::DINHEIRO => 'Dinheiro (Espécie)',
            self::CARTAO_DEBITO => 'Cartão de Débito',
            self::CARTAO_CREDITO => 'Cartão de Crédito',
            self::BOLETO => 'Boleto Bancário',
            self::CHEQUE => 'Cheque',
            self::OUTRO => 'Outro',
        };
    }
}
