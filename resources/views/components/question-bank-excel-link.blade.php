@props(['questionBank'])

<a href="{{ route('question-banks.excel', $questionBank) }}"
   data-question-bank-excel-download
   data-preview-url="{{ route('question-banks.show', $questionBank) }}"
   data-package-url="{{ route('question-banks.package', $questionBank) }}"
   aria-label="Unduh Excel saja tanpa paket media"
   {{ $attributes }}>{{ $slot }}</a>
