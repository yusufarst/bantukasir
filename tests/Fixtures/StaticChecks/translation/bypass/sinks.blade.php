{{-- Negative samples of the literal check in Blade, from the class-A adversarial review of TASK-003, finding A-07. A line tagged K is reported; a line tagged C is a control and is not. --}}
<input type="submit" value="Simpan"> {{-- K21 the value of a button --}}
<p>{{ 'Teks literal' }}</p> {{-- K22 a literal inside an echo --}}
@section('title', 'Judul halaman') {{-- K23 a literal inside a directive --}}
<x-alert message="Pesan literal" /> {{-- K24 a component attribute --}}
<meta name="description" content="Deskripsi literal"> {{-- K25 a meta description --}}
<p>{!! 'Teks mentah' !!}</p> {{-- K26 a literal inside a raw echo --}}
<p>{{ $ok ? 'Ya' : 'Tidak' }}</p> {{-- K27 literals of a conditional inside an echo --}}
<x-alert :message="'Pesan terikat'" /> {{-- K28 a literal bound to a component attribute --}}
<button data-confirm="Yakin hapus?">{{ __('sample.greeting.title') }}</button> {{-- K29 a data attribute that holds text --}}
@include('partials.header', ['title' => 'Judul sisipan']) {{-- K30 a literal passed to a view --}}
{{ __('sample.greeting.nope_blade') }} {{-- K31 a missing key in an echo --}}
@lang('sample.greeting.nope_lang') {{-- K32 a missing key through @lang --}}
<x-alert :message="__('sample.greeting.nope_bound')" /> {{-- K33 a missing key in a bound attribute --}}
<meta charset="utf-8"> {{-- C01 a non-text attribute --}}
<meta name="viewport" content="width=device-width, initial-scale=1"> {{-- C02 the reviewed viewport --}}
<input type="text" name="harga" id="harga" class="field" autocomplete="off" inputmode="numeric"> {{-- C03 non-text attributes --}}
<p>{{ __('sample.greeting.title') }}</p> {{-- C04 a key in an echo --}}
@section('title', __('sample.greeting.title')) {{-- C05 a key in a directive --}}
<x-alert :message="__('sample.greeting.title')" /> {{-- C06 a key in a bound attribute --}}
<a href="{{ route('home') }}">{{ __('sample.greeting.title') }}</a> {{-- C07 a route name is not text --}}
@vite(['resources/js/app.tsx']) {{-- C08 an asset path is not text --}}
<p>{{ $amount }}</p> {{-- C09 a value --}}
