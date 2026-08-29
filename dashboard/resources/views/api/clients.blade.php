@extends('dash.layouts.app')
@section('title','API CLIENTS')
@section('content')
@if(session('api_token'))
<div class="alert alert-warning">
  <strong>API credentials created/rotated.</strong>
  <div class="mt-2"><small>API key</small><div class="input-group"><input id="new-api-token" type="password" class="form-control font-monospace secret-field" readonly value="{{ session('api_token') }}"><button type="button" class="btn btn-outline-secondary toggle-secret" data-target="new-api-token"><i class="ti ti-eye"></i></button><button type="button" class="btn btn-outline-secondary copy-field" data-target="new-api-token">Copy</button></div></div>
  @if(session('webhook_secret'))<div class="mt-2"><small>Webhook secret</small><div class="input-group"><input id="new-webhook-secret" type="password" class="form-control font-monospace secret-field" readonly value="{{ session('webhook_secret') }}"><button type="button" class="btn btn-outline-secondary toggle-secret" data-target="new-webhook-secret"><i class="ti ti-eye"></i></button><button type="button" class="btn btn-outline-secondary copy-field" data-target="new-webhook-secret">Copy</button></div></div>@endif
</div>
@endif
<div class="row g-4">
 <div class="col-12 col-xl-5"><div class="card"><div class="card-body"><h5>Create API Client</h5><p class="text-muted">Give each website or app its own key, permissions and device access.</p>
 <form method="post" action="{{ route('api.clients.store') }}">@csrf
 <div class="mb-3"><label class="form-label">Client name</label><input name="name" class="form-control" required placeholder="Kuskuskuy Store"></div>
 <div class="mb-3"><label class="form-label">Allowed devices</label>@forelse($devices as $device)<div class="form-check"><input class="form-check-input" type="checkbox" name="sessions[]" value="{{ $device->id }}" id="dev{{ $loop->index }}"><label class="form-check-label" for="dev{{ $loop->index }}">{{ $device->session_name }} <span class="text-muted font-monospace small">({{ $device->id }})</span></label></div>@empty<div class="text-muted small">Create a WhatsApp device first.</div>@endforelse</div>
 <div class="mb-3"><label class="form-label">Scopes</label>@foreach($scopes as $scope)<div class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $scope }}" id="scope{{ $loop->index }}"><label class="form-check-label font-monospace" for="scope{{ $loop->index }}">{{ $scope }}</label></div>@endforeach</div>
 <div class="mb-3"><label class="form-label">Rate limit / minute</label><input name="rate_limit" type="number" min="1" max="1000" value="60" class="form-control" required></div>
 <div class="mb-3"><label class="form-label">Webhook URL (optional)</label><input name="webhook_url" type="url" class="form-control" placeholder="https://example.com/webhooks/whatsapp"></div>
 <button class="btn btn-primary w-100" @disabled($devices->isEmpty())>Create Client</button></form></div></div></div>
 <div class="col-12 col-xl-7"><div class="card"><div class="card-body"><h5>API Clients</h5>
 @forelse($clients as $client)
  <div class="border rounded p-3 mb-3">
   <div class="d-flex flex-wrap justify-content-between gap-2"><div><strong>{{ $client->name }}</strong> <span class="badge bg-label-{{ $client->is_active?'success':'secondary' }}">{{ $client->is_active?'Active':'Disabled' }}</span></div>
    <div class="d-flex flex-wrap gap-1"><form method="post" action="{{ route('api.clients.rotate',$client->id) }}" onsubmit="return confirm('Rotate API key? The current key will stop working immediately.')">@csrf<button class="btn btn-sm btn-label-warning">Rotate Key</button></form><form method="post" action="{{ route('api.clients.toggle',$client->id) }}">@csrf<button class="btn btn-sm btn-label-secondary">{{ $client->is_active?'Disable':'Enable' }}</button></form><form method="post" action="{{ route('api.clients.destroy',$client->id) }}" onsubmit="return confirm('Delete this API client?')">@csrf @method('DELETE')<button class="btn btn-sm btn-label-danger">Delete</button></form></div>
   </div>
   <div class="mt-3"><small class="text-muted">API key</small>@php($tokenId='token-'.str_replace('-','',$client->id))<div class="input-group input-group-sm"><input id="{{ $tokenId }}" type="password" class="form-control font-monospace secret-field" readonly value="{{ $client->secret_value ?: '' }}" placeholder="Rotate key once to enable reveal"><button type="button" class="btn btn-outline-secondary toggle-secret" data-target="{{ $tokenId }}" @disabled(!$client->secret_value)><i class="ti ti-eye"></i></button><button type="button" class="btn btn-outline-secondary copy-field" data-target="{{ $tokenId }}" @disabled(!$client->secret_value)>Copy</button></div></div>
   <div class="mt-2"><small class="text-muted">Webhook URL</small>@php($hookId='hook-'.str_replace('-','',$client->id))<div class="input-group input-group-sm"><input id="{{ $hookId }}" class="form-control font-monospace" readonly value="{{ $client->webhook_url ?: '' }}" placeholder="Not configured"><button type="button" class="btn btn-outline-secondary copy-field" data-target="{{ $hookId }}" @disabled(!$client->webhook_url)>Copy</button></div></div>
   <div class="mt-2"><small class="text-muted">Webhook secret</small>@php($secretId='whsec-'.str_replace('-','',$client->id))<div class="input-group input-group-sm"><input id="{{ $secretId }}" type="password" class="form-control font-monospace secret-field" readonly value="{{ $client->webhook_secret ?: '' }}"><button type="button" class="btn btn-outline-secondary toggle-secret" data-target="{{ $secretId }}"><i class="ti ti-eye"></i></button><button type="button" class="btn btn-outline-secondary copy-field" data-target="{{ $secretId }}">Copy</button></div></div>
   <div class="mt-3 small"><strong>Devices</strong></div>
   @forelse($client->sessions as $device)<div class="small mb-1">{{ $device->session_name }} · <span class="font-monospace">{{ $device->id }}</span> <button type="button" class="btn btn-sm p-0 ms-1 copy-text" data-value="{{ $device->id }}" title="Copy Device ID"><i class="ti ti-copy"></i></button></div>@empty<div class="small text-muted">No device assigned.</div>@endforelse
   <div class="small mt-2">Scopes: {{ implode(', ', $client->scopes ?? []) }}</div><div class="small">Rate limit: {{ $client->rate_limit }}/min</div>
  </div>
 @empty<p class="text-muted">No API clients yet.</p>@endforelse
 </div></div></div>
</div>
@endsection
@push('js')
<script>
(function(){
 function copyValue(value, button){if(!value)return;navigator.clipboard.writeText(value).then(()=>{const old=button.innerHTML;button.innerHTML='<i class="ti ti-check"></i>';setTimeout(()=>button.innerHTML=old,1200)});}
 $(document).on('click','.toggle-secret',function(){const el=document.getElementById(this.dataset.target);if(!el)return;el.type=el.type==='password'?'text':'password';$(this).find('i').toggleClass('ti-eye ti-eye-off');});
 $(document).on('click','.copy-field',function(){const el=document.getElementById(this.dataset.target);copyValue(el?.value||'',this);});
 $(document).on('click','.copy-text',function(){copyValue(this.dataset.value||'',this);});
})();
</script>
@endpush
