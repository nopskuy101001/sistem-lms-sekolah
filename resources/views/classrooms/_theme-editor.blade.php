@php
    $themeClassroom = $themeClassroom ?? null;
    $themeUrl = $themeClassroom?->theme_image_path ? asset('storage/'.$themeClassroom->theme_image_path) : null;
    $themeZoom = old('theme_zoom', $themeClassroom?->theme_zoom ?? 100);
    $themeX = old('theme_position_x', $themeClassroom?->theme_position_x ?? 50);
    $themeY = old('theme_position_y', $themeClassroom?->theme_position_y ?? 50);
@endphp

<div class="lms-theme-editor" data-theme-editor>
    <label class="grid gap-1 text-sm font-medium text-slate-700">
        {{ $themeClassroom ? 'Ganti gambar tema' : 'Gambar tema kelas (opsional)' }}
        <input type="file" name="theme_image" accept="image/jpeg,image/png,image/webp" data-theme-file class="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
        <span class="text-xs font-normal text-slate-500">JPG, PNG, atau WebP; maksimal 4 MB. Bagian di luar kotak akan terpotong.</span>
    </label>
    @error('theme_image')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

    <div class="lms-theme-preview {{ $themeUrl ? 'lms-has-theme-image' : '' }}" data-theme-preview data-color="{{ old('color', $themeClassroom?->color ?? 'indigo') }}">
        <img class="lms-theme-image" data-theme-preview-image @if($themeUrl) src="{{ $themeUrl }}" @else hidden @endif alt="Pratinjau gambar tema">
        <span class="lms-theme-preview-title">Pratinjau sampul kelas</span>
        <span class="lms-theme-preview-empty" data-theme-empty @if($themeUrl) hidden @endif>Pilih gambar untuk melihat ukurannya di kartu kelas.</span>
    </div>

    <div class="lms-theme-sliders">
        <label>Ukuran / zoom <output data-theme-zoom-output>{{ $themeZoom }}%</output>
            <input type="range" name="theme_zoom" min="100" max="250" step="5" value="{{ $themeZoom }}" data-theme-zoom>
        </label>
        <label>Posisi horizontal <output data-theme-x-output>{{ $themeX }}%</output>
            <input type="range" name="theme_position_x" min="0" max="100" value="{{ $themeX }}" data-theme-x>
        </label>
        <label>Posisi vertikal <output data-theme-y-output>{{ $themeY }}%</output>
            <input type="range" name="theme_position_y" min="0" max="100" value="{{ $themeY }}" data-theme-y>
        </label>
    </div>
    @foreach(['theme_zoom', 'theme_position_x', 'theme_position_y'] as $field)
        @error($field)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @endforeach
</div>

@once
    <script defer src="{{ asset('js/classroom-theme-editor.js') }}?v=1"></script>
@endonce
