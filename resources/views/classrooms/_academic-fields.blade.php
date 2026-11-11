@php
    $academicClassroom = $academicClassroom ?? null;
    $wrapperClass = $wrapperClass ?? '';
    $selectedGrade = old('grade_level', $academicClassroom?->grade_level);
    $selectedYear = old('academic_year', $academicClassroom?->academic_year ?: \App\Models\Classroom::currentAcademicYear());
    $yearOptions = collect(\App\Models\Classroom::academicYearOptions());
    if ($selectedYear && ! $yearOptions->has($selectedYear)) $yearOptions->put($selectedYear, $selectedYear);
    $yearOptions = $yearOptions->sortKeysDesc();
@endphp

<fieldset class="lms-academic-fields {{ $wrapperClass }}">
    <legend>Informasi periode kelas</legend>
    <p class="lms-academic-help">Tag ini memisahkan kelas dengan nama atau tingkat yang sama pada tahun ajaran berbeda.</p>

    <label class="lms-academic-field">
        <span>Tingkat kelas</span>
        <select name="grade_level">
            <option value="">Umum / tidak ditentukan</option>
            @foreach(\App\Models\Classroom::GRADE_LEVEL_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($selectedGrade === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('grade_level')<small>{{ $message }}</small>@enderror
    </label>

    <label class="lms-academic-field">
        <span>Tahun ajaran *</span>
        <select name="academic_year" required>
            @foreach($yearOptions as $value => $label)
                <option value="{{ $value }}" @selected($selectedYear === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('academic_year')<small>{{ $message }}</small>@enderror
    </label>

</fieldset>
