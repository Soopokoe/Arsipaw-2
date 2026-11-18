<?php

namespace App\Filament\Widgets;

use App\Models\Arsip;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatDashboard extends StatsOverviewWidget
{
    protected static ?int $sort = 1; // ⬆️ PALING ATAS

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Arsip', Arsip::count())
                ->description('Data arsip tersimpan'),

            Stat::make(
                'Total Arsip Bulan Ini',
                Arsip::whereMonth('created_at', now()->month)->count()
            )->description('Update bulan ini'),

            Stat::make('Total User', User::count())
                ->description('Pengguna aktif'),
        ];
    }

    public function getColumns(): int
    {
        return 3;
    }
}