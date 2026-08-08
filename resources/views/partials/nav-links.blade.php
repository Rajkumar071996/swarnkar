@php $isGirvi = ($activeModule ?? 'goldscore') === 'girvi'; @endphp

@include('partials.module-switcher', ['isGirvi' => $isGirvi])

@include($isGirvi ? 'partials.nav-girvi' : 'partials.nav-goldscore')
