@php
    $themeClassroom = $themeClassroom ?? null;
    $selectedColor = old('color', $themeClassroom?->color ?? 'indigo');
    $selectedStyle = old('theme_style', $themeClassroom?->themeStyle() ?? 'orb');
@endphp

<div class="lms-theme-choices">
    <fieldset>
        <legend>Warna dasar</legend>
        <div class="lms-color-options">
            @foreach(\App\Models\Classroom::COLOR_LABELS as $value => $label)
                <label class="lms-color-option" title="{{ $label }}">
                    <input type="radio" name="color" value="{{ $value }}" @checked($selectedColor === $value) required>
                    <span class="lms-color-swatch" data-color="{{ $value }}" aria-hidden="true"></span>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('color')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </fieldset>

    <fieldset>
        <legend>Gaya sampul</legend>
        <div class="lms-style-options">
            @foreach(\App\Models\Classroom::THEME_STYLE_LABELS as $value => $label)
                <label class="lms-style-option">
                    <input type="radio" name="theme_style" value="{{ $value }}" @checked($selectedStyle === $value)>
                    <span class="lms-style-thumbnail" data-theme-style="{{ $value }}" aria-hidden="true"></span>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('theme_style')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </fieldset>
</div>
