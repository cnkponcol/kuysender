<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
  <div class="app-brand demo"><a href="{{ route('dashboard') }}" class="app-brand-link"><span class="app-brand-text demo menu-text fw-bold ms-2">KUY SENDER</span></a><a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto"><i class="ti menu-toggle-icon d-none d-xl-block ti-sm align-middle"></i><i class="ti ti-x d-block d-xl-none ti-sm align-middle"></i></a></div>
  <div class="menu-inner-shadow"></div>
  <ul class="menu-inner py-1">
    <li class="menu-item {{ Route::is('dashboard*') ? 'active' : '' }}"><a href="{{ route('dashboard') }}" class="menu-link"><i class="menu-icon tf-icons ti ti-apps"></i><div>Dashboard</div></a></li>
    <li class="menu-item"><div class="menu-link px-0"><select class="form-control main-device"></select></div></li>
    <li class="menu-header small text-uppercase"><span class="menu-header-text">Messaging</span></li>
    <li class="menu-item {{ Route::is('inbox*') ? 'active' : '' }}"><a href="{{ route('inbox') }}" class="menu-link"><i class="menu-icon ti ti-messages"></i><div>Inbox</div></a></li>
    <li class="menu-item {{ Route::is('single*') ? 'active' : '' }}"><a href="{{ route('single') }}" class="menu-link"><i class="menu-icon ti ti-send"></i><div>Single Sender</div></a></li>
    <li class="menu-item {{ Route::is('responder*') ? 'active' : '' }}"><a href="{{ route('responder') }}" class="menu-link"><i class="menu-icon ti ti-message-2-code"></i><div>Auto Responders</div></a></li>
    <li class="menu-item {{ Route::is('phonebook*') ? 'active' : '' }}"><a href="{{ route('phonebook') }}" class="menu-link"><i class="menu-icon ti ti-address-book"></i><div>Contacts</div></a></li>
    <li class="menu-item {{ Route::is('campaigns.*') ? 'active' : '' }}"><a href="{{ route('campaigns.index') }}" class="menu-link"><i class="menu-icon ti ti-speakerphone"></i><div>Broadcast</div></a></li>
    <li class="menu-header small text-uppercase"><span class="menu-header-text">Automation</span></li>
    <li class="menu-item {{ Route::is('ai.*') ? 'active' : '' }}"><a href="{{ route('ai.index') }}" class="menu-link"><i class="menu-icon ti ti-robot"></i><div>AI Assistant</div></a></li>
    <li class="menu-item {{ Route::is('api.clients*') ? 'active' : '' }}"><a href="{{ route('api.clients') }}" class="menu-link"><i class="menu-icon ti ti-key"></i><div>API Clients</div></a></li>
    <li class="menu-item {{ Route::is('apidocs*') ? 'active' : '' }}"><a href="{{ route('apidocs') }}" class="menu-link"><i class="menu-icon ti ti-api"></i><div>API Docs</div></a></li>
    <li class="menu-item {{ Route::is('logs.*') ? 'active' : '' }}"><a href="{{ route('logs.index') }}" class="menu-link"><i class="menu-icon ti ti-activity"></i><div>Logs</div></a></li>
    <li class="menu-header small text-uppercase"><span class="menu-header-text">Other</span></li>
    <li class="menu-item {{ Route::is('files') ? 'active' : '' }}"><a href="{{ route('files') }}" class="menu-link"><i class="menu-icon ti ti-folder"></i><div>File Manager</div></a></li>
    @if($auth->role === 'admin')<li class="menu-item {{ Route::is('admin*') ? 'active' : '' }}"><a href="{{ route('admin.users') }}" class="menu-link"><i class="menu-icon ti ti-users"></i><div>Manage Users</div></a></li>@endif
    <li class="menu-item"><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="menu-link border-0 bg-transparent w-100 text-start"><i class="menu-icon ti ti-logout"></i><div>Log out</div></button></form></li>
  </ul>
</aside>
