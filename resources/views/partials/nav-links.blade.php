@php $isGirvi = request()->routeIs('girvi.*'); @endphp

@include('partials.module-switcher', ['isGirvi' => $isGirvi])

@include($isGirvi ? 'partials.nav-girvi' : 'partials.nav-goldscore')
