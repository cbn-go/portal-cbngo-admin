<?php

namespace App\Filament\Juban\Resources\JubanTransactionResource\Pages;

use App\Filament\Juban\Resources\JubanTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJubanTransactions extends ListRecords
{
    protected static string $resource = JubanTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
