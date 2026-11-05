<?php

namespace App\Filament\Resources\MinisterResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FamilyMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'familyMembers';

    protected static ?string $title = 'Família';

    protected static ?string $modelLabel = 'familiar';

    protected static ?string $pluralModelLabel = 'familiares';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('relationship')
                    ->label('Parentesco')
                    ->required()
                    ->maxLength(50),

                Forms\Components\TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                Forms\Components\DatePicker::make('birth_date')
                    ->label('Nascimento')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('relationship')
                    ->label('Parentesco'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome'),

                Tables\Columns\TextColumn::make('birth_date')
                    ->label('Nascimento')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
