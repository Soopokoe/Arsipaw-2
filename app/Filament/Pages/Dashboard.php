<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        $user = Filament::auth()->user();
        return 'Halo, ' . ($user->name ?? 'User') . '!';
    }

    public function getColumns(): int|array
    {
        return 12;
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatDashboard::class,
            \App\Filament\Widgets\BlogPostsChart::class,
            \App\Filament\Widgets\ActivityLogWidget::class,
        ];
    }
}

