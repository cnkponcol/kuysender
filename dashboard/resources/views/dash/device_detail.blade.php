@extends('dash.layouts.app')
@section('title', 'DEVICE')
@section('content')
<div class="row g-4">
 <div class="col-12 col-lg-8"><div class="card"><div class="card-body text-center" style="min-height:430px;display:flex;align-items:center;justify-content:center"><div id="device-panel"><div class="spinner-border text-primary mb-3"></div><div>Checking device...</div></div></div></div></div>
 <div class="col-12 col-lg-4"><div class="card"><div class="card-body">
  <h5 class="mb-3">{{ $device->session_name }}</h5>
  <div class="mb-2"><small class="text-muted">Device ID</small><div class="input-group input-group-sm"><input id="device-id" class="form-control font-monospace" readonly value="{{ $device->id }}"><button type="button" class="btn btn-outline-secondary" id="copy-device-id"><i class="ti ti-copy"></i></button></div></div>
  <div class="mb-2"><small class="text-muted">State</small><div id="device-state">{{ $device->connection_state }}</div></div>
  <div class="mb-2"><small class="text-muted">WhatsApp</small><div id="device-number">{{ $device->whatsapp_number ?: '-' }}</div></div>
  <div class="mb-4"><small class="text-muted">Last error</small><div id="device-error" class="text-danger small">{{ $device->last_error }}</div></div>
  <div class="d-grid gap-2"><button id="start-device" class="btn btn-primary">Start / Generate QR</button><button id="logout-device" class="btn btn-label-danger">Logout WhatsApp</button></div>
 </div></div></div>
</div>
<div class="card mt-4"><div class="card-body"><h6>Security</h6><p class="mb-0 text-muted">QR and device controls are proxied through Laravel. The browser never receives the private WA service token.</p></div></div>
@endsection
@push('js')
<script>
(function(){
 const panel=$('#device-panel'), state=$('#device-state'), number=$('#device-number'), error=$('#device-error');
 $('#copy-device-id').on('click',function(){navigator.clipboard.writeText($('#device-id').val()).then(()=>{$(this).html('<i class="ti ti-check"></i>');setTimeout(()=>$(this).html('<i class="ti ti-copy"></i>'),1200);});});
 const statusUrl=@json(route('device.status',$device->id));
 function render(d){state.text(d.connection_state||'disconnected');number.text(d.whatsapp_number||'-');error.text(d.last_error||'');
   if(d.connection_state==='connected'){panel.html('<i class="ti ti-circle-check text-success" style="font-size:5rem"></i><h4 class="mt-3">Connected</h4><p class="text-muted">'+(d.whatsapp_number||'WhatsApp ready')+'</p>');}
   else if(d.qr_code){panel.html('<div><img src="'+d.qr_code+'" alt="WhatsApp QR" class="img-fluid" style="max-width:320px"><h5 class="mt-3">Scan QR in WhatsApp</h5><small class="text-muted">QR refreshes automatically when needed.</small></div>');}
   else {panel.html('<div><i class="ti ti-brand-whatsapp text-muted" style="font-size:5rem"></i><h5 class="mt-3">Device disconnected</h5><small class="text-muted">Press Start to create a login session.</small></div>');}}
 function poll(){fetch(statusUrl,{headers:{'Accept':'application/json'}}).then(r=>r.json()).then(x=>render(x.data||{})).catch(()=>{});} poll(); setInterval(poll,4000);
 $('#start-device').on('click',function(){this.disabled=true;fetch(@json(route('device.start',$device->id)),{method:'POST',headers:{'X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),'Accept':'application/json'}}).then(()=>poll()).finally(()=>this.disabled=false)});
 $('#logout-device').on('click',function(){if(!confirm('Logout this WhatsApp session? A new QR will be required to connect again.'))return;this.disabled=true;fetch(@json(route('device.logout',$device->id)),{method:'POST',headers:{'X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),'Accept':'application/json'}}).then(()=>poll()).finally(()=>this.disabled=false)});
})();
</script>
@endpush
