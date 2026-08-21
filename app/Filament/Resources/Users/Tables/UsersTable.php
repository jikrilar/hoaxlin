<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->sortable(),
                IconColumn::make('is_admin')->label('Admin')->boolean(),
                IconColumn::make('email_verified_at')->label('Terverifikasi')->boolean(),
                TextColumn::make('created_at')->label('Terdaftar')->dateTime()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin')->label('Administrator'),
                TernaryFilter::make('email_verified_at')->label('Email terverifikasi')
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
