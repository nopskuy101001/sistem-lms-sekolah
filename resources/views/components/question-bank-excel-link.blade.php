@props(['questionBank'])

<a href="{{ route('question-banks.excel', $questionBank) }}"
   data-question-bank-excel-download
   data-warning="Periksa isi soal terlebih dahulu sebelum mengunduh Excel. Jika terdapat stimulus video URL, MP4, atau gambar, disarankan mengunduh Paket ZIP agar media dan referensinya tetap lengkap. Tetap unduh Excel saja?"
   aria-label="Unduh Excel saja tanpa paket media"
   {{ $attributes }}>{{ $slot }}</a>
