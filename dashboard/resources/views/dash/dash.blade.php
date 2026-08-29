@extends('dash.layouts.app')
@section('title', 'DASHBOARD')
@section('content')
<div class="row g-4 mb-4">
  <div class="col-12"><div class="card"><div class="card-body d-flex justify-content-between align-items-center">
    <div><span>Devices</span><div class="d-flex align-items-center my-1"><h4 class="mb-0 me-2" id="count-device">{{ $count_device }}</h4><span class="text-success">({{ $count_device_online }} Online)</span></div><small class="text-muted">Limit: {{ $auth->limit_device ?: 'Unlimited' }}</small></div>
    <button class="btn btn-primary is-button-add"><i class="ti ti-plus me-1"></i>Add Device</button>
  </div></div></div>
</div>
<div class="card"><div class="card-datatable table-responsive pt-0"><table class="datatables-basic table"><thead><tr><th></th><th>Device</th><th>Number</th><th>Status</th><th>Action</th></tr></thead></table></div></div>
<div class="offcanvas offcanvas-end" tabindex="-1" id="add-new"><div class="offcanvas-header"><h5 class="offcanvas-title">Add Device</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div><div class="offcanvas-body">
<form id="device-store" action="{{ route('device.store') }}" method="post">@csrf<div class="mb-3"><label class="form-label">Session Name</label><input class="form-control" name="session_name" required maxlength="120" placeholder="WA Utama"></div><button class="btn btn-primary w-100" type="submit">Create Device</button></form>
</div></div>
@endsection
@push('js')
<script>
(function(){
 const app = new velixs();
 const dbs = app.datatables({url:"{{ route('dashboard.data') }}",header:'WhatsApp Devices',columns:[{data:'responsive_id'},{data:'session_name'},{data:'whatsapp_number'},{data:'status'},{data:'action'}]});
 $(document).on('click','.is-button-add',()=>$('#add-new').offcanvas('show'));
 $('#device-store').on('submit',function(e){e.preventDefault();app.ajax({url:this.action,data:$(this).serialize(),addons_success:()=>{dbs.ajax.reload();main_device();this.reset();$('#add-new').offcanvas('hide')}})});
 $(document).on('click','.is-delete-device',function(){const id=$(this).data('id');Swal.fire({text:'Delete this device and its local session data?',icon:'warning',showCancelButton:true,confirmButtonText:'Delete',customClass:{confirmButton:'btn btn-danger',cancelButton:'btn btn-label-secondary ms-2'},buttonsStyling:false}).then(r=>{if(r.isConfirmed)app.ajax({url:"{{ route('device.delete') }}",data:{id},addons_success:()=>{dbs.ajax.reload();main_device();}})})});
})();
</script>
@endpush
@push('cssvendor')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}" />
@endpush
@push('jsvendor')<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>@endpush
