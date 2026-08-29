<nav class="kuys-mobile-nav" aria-label="Navigasi mobile">
    <a href="{{ route('dashboard') }}" class="{{ Route::is('dashboard*') ? 'active' : '' }}">
        <i class="ti ti-home"></i><span>Home</span>
    </a>
    <a href="{{ route('inbox') }}" class="{{ Route::is('inbox*') ? 'active' : '' }}">
        <i class="ti ti-messages"></i><span>Inbox</span>
    </a>
    <a href="{{ route('single') }}" class="{{ Route::is('single*') ? 'active' : '' }}">
        <i class="ti ti-send"></i><span>Kirim</span>
    </a>
    <a href="{{ route('phonebook') }}" class="{{ Route::is('phonebook*') ? 'active' : '' }}">
        <i class="ti ti-address-book"></i><span>Kontak</span>
    </a>
    <a href="javascript:void(0);" class="layout-menu-toggle {{ Route::is('responder*','campaigns.*','ai.*','api.clients*','apidocs*','logs.*','files','admin*') ? 'active' : '' }}">
        <i class="ti ti-grid-dots"></i><span>Lainnya</span>
    </a>
</nav>
