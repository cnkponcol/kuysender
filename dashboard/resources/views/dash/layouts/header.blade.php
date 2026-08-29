<nav class="layout-navbar container-fluid navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none"><a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="ti ti-menu-2 ti-sm"></i></a></div>
  <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <div class="navbar-nav align-items-center"><div class="nav-item mb-0">
      @if($main_device)<span class="badge rounded-pill bg-label-{{ $main_device->connection_state==='connected'?'success':($main_device->connection_state==='qr'?'warning':'secondary') }}"><i class="ti ti-brand-whatsapp me-1"></i>{{ $main_device->session_name }} · {{ strtoupper($main_device->connection_state) }}</span>@else<span class="badge rounded-pill bg-label-secondary"><i class="ti ti-shield-lock me-1"></i>WA service protected</span>@endif
    </div></div>
    <ul class="navbar-nav flex-row align-items-center ms-auto"><li class="nav-item me-2"><a class="nav-link style-switcher-toggle hide-arrow" href="javascript:void(0);"><i class="ti ti-md"></i></a></li><li class="nav-item"><span class="nav-link"><i class="ti ti-user-circle me-1"></i>{{ $auth->username }}</span></li></ul>
  </div>
</nav>
