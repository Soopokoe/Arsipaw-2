<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Spatie\Activitylog\Models\Activity;

class ActivityLogWidget extends Widget
{
    protected static ?int $sort = 99; // paling bawah

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.activity-log-widget';

    protected function getViewData(): array
    {
        return [
            'activities' => Activity::query()
                ->with('causer') // 🔥 ambil user pelaku
                ->latest()
                ->limit(3)       // ✅ LIMIT 3
                ->get(),
        ];
    }
}
