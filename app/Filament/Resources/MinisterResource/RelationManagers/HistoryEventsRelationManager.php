<?php

namespace App\Filament\Resources\MinisterResource\RelationManagers;

use App\Models\Minister;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class HistoryEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'historyEvents';

    protected static ?string $title = 'Histórico';

    protected static ?string $modelLabel = 'evento';

    protected static ?string $pluralModelLabel = 'eventos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('occurred_on')
                    ->label('Data')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                Forms\Components\TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->maxLength(20)
                    ->helperText('1 = diplomação, 2 = ordenação.'),

                Forms\Components\TextInput::make('description')
                    ->label('Descrição')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('occurred_on')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição'),
            ])
            ->defaultSort('occurred_on', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    protected function canCreate(): bool
    {
        return $this->canManageHistory();
    }

    protected function canEdit(Model $record): bool
    {
        return $this->canManageHistory();
    }

    protected function canDelete(Model $record): bool
    {
        return $this->canManageHistory();
    }

    private function canManageHistory(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Minister
            && (auth()->user()?->can('manageHistory', $owner) ?? false);
    }
}
