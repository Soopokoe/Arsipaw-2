<x-filament::widget>
    <x-filament::card>
        <h2 class="text-lg font-bold mb-4">Log Aktivitas</h2>

        <div class="space-y-4">
            @forelse ($activities as $activity)
                <div class="flex items-start justify-between">
                    <div class="text-sm">
                        {{-- AKSI --}}
                        <div class="font-medium text-gray-200">
                            {{ ucfirst($activity->description) }}
                        </div>

                        {{-- OLEH SIAPA --}}
                        <div class="text-xs text-gray-400">
                            oleh
                            <span class="font-semibold text-primary-400">
                                {{ $activity->causer?->name ?? 'System' }}
                            </span>
                        </div>
                    </div>

                    {{-- WAKTU --}}
                    <div class="text-xs text-gray-500">
                        {{ $activity->created_at->diffForHumans() }}
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada aktivitas</p>
            @endforelse
        </div>
    </x-filament::card>
</x-filament::widget>
