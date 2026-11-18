<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Arsip;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('log_name')
                    ->label('Module')
                    ->badge(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Activity')
                    ->wrap(),

                // ✅ INI BAGIAN PENTING: TAHU ARSIP MANA
                Tables\Columns\TextColumn::make('subject')
                    ->label('Objek / Arsip')
                    ->formatStateUsing(function ($record) {
                        if (! $record->subject) {
                            return '-';
                        }

                        if ($record->subject instanceof Arsip) {
                            return $record->subject->nama_arsip
                                . ' ('
                                . $record->subject->nomor_surat
                                . ')';
                        }

                        return class_basename($record->subject_type);
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->default('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->timezone('Asia/Jakarta')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Module')
                    ->options([
                        'arsip' => 'Arsip',
                        'user' => 'User',
                    ]),
            ])
            ->actions([])       // ❌ tidak bisa edit
            ->bulkActions([]);  // ❌ tidak bisa delete
    }
}
