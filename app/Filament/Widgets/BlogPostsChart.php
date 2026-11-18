<?php

namespace App\Filament\Widgets;

use App\Models\Arsip;
use Filament\Widgets\ChartWidget;

class BlogPostsChart extends ChartWidget
{
    protected static ?int $sort = 3; // ➡️ KANAN

    protected int|string|array $columnSpan = 8;

    protected ?string $heading = 'Distribusi Arsip Berdasarkan RW';

    protected function getMaxHeight(): string
    {
        return '300px';
    }

    protected function getData(): array
    {
        $data = Arsip::query()
            ->whereNotNull('rw')
            ->selectRaw('rw, COUNT(*) as total')
            ->groupBy('rw')
            ->orderBy('rw')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Arsip',
                    'data' => $data->pluck('total'),
                ],
            ],
            'labels' => $data->pluck('rw'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}