<ul class="space-y-1">
    @foreach ($getState() ?? [] as $file)
        <li>
            <a href="{{ asset('storage/' . $file) }}" target="_blank" class="text-primary-600 underline">
                {{ basename($file) }}
            </a>
        </li>
    @endforeach
</ul>