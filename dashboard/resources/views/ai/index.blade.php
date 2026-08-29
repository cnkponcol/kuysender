@extends('dash.layouts.app')
@section('title','AI ASSISTANT')
@section('content')
<div class="row g-4">
 <div class="col-12 col-xl-6"><div class="card"><div class="card-body"><h5>AI Assistant · {{ $session->session_name }}</h5><form method="post" action="{{ route('ai.update') }}">@csrf
  <div class="form-check form-switch mb-3"><input type="hidden" name="enabled" value="0"><input class="form-check-input" type="checkbox" name="enabled" value="1" {{ $setting->enabled?'checked':'' }}><label class="form-check-label">Enable AI</label></div>
  <div class="mb-3"><label class="form-label">Mode</label><select name="mode" class="form-select"><option value="off" @selected($setting->mode==='off')>Off</option><option value="suggest" @selected($setting->mode==='suggest')>Suggest Only</option><option value="auto" @selected($setting->mode==='auto')>Auto Reply</option><option value="out_of_hours" @selected($setting->mode==='out_of_hours')>Auto Reply Outside Business Hours</option></select></div>
  <div class="mb-3"><label class="form-label">Provider endpoint</label><input type="url" name="provider_url" class="form-control" value="{{ $setting->provider_url }}" placeholder="https://api.provider.com/v1/chat/completions"></div>
  <div class="mb-3"><label class="form-label">API key</label><div class="input-group"><input id="ai-api-key" type="password" name="api_key" class="form-control" placeholder="Leave blank to keep current key"><button class="btn btn-outline-secondary" type="button" id="toggle-ai-key"><i class="ti ti-eye"></i></button></div><small class="text-muted">For security, saved key is not returned to the browser. Enter a value only when replacing it.</small></div>
  <div class="mb-3"><label class="form-label">Model</label><input name="model" class="form-control" value="{{ $setting->model }}"></div>
  <div class="mb-3"><label class="form-label">System prompt</label><textarea name="system_prompt" rows="8" class="form-control">{{ $setting->system_prompt }}</textarea></div>
  <div class="row"><div class="col"><label class="form-label">Business start</label><input type="time" name="business_start" class="form-control" value="{{ data_get($setting->business_hours,'mon.start') }}"></div><div class="col"><label class="form-label">Business end</label><input type="time" name="business_end" class="form-control" value="{{ data_get($setting->business_hours,'mon.end') }}"></div></div>
  <div class="mt-3"><label class="form-label">Context messages</label><input type="number" name="max_context_messages" min="4" max="30" class="form-control" value="{{ $setting->max_context_messages ?: 12 }}"></div>
  <button class="btn btn-primary mt-3">Save AI Settings</button></form><form class="mt-2" method="post" action="{{ route('ai.prompt.clear') }}" onsubmit="return confirm('Clear the saved system prompt?')">@csrf<button class="btn btn-label-danger">Clear Prompt</button></form>
 </div></div></div>

 <div class="col-12 col-xl-6">
  <div class="card mb-4"><div class="card-body"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h5 class="mb-1">Knowledge Base</h5><small class="text-muted">Active items are included in AI context.</small></div><div class="d-flex gap-1"><a class="btn btn-sm btn-label-primary" href="{{ route('ai.knowledge.export') }}"><i class="ti ti-download me-1"></i>Export JSON</a><button class="btn btn-sm btn-label-primary" data-bs-toggle="modal" data-bs-target="#knowledge-import"><i class="ti ti-upload me-1"></i>Import</button></div></div>
   <hr><form method="post" action="{{ route('ai.knowledge.store') }}">@csrf<div class="row g-2"><div class="col-md-7"><input name="title" class="form-control" required placeholder="FAQ / Product / Policy"></div><div class="col-md-5"><input name="category" class="form-control" placeholder="Category (optional)"></div></div><textarea name="content" class="form-control mt-2" rows="5" required placeholder="Facts the AI may use..."></textarea><button class="btn btn-primary mt-2">Add Knowledge</button></form>
   <div class="mt-3"><input id="knowledge-search" class="form-control" placeholder="Search knowledge..."></div>
  </div></div>

  @forelse($knowledge as $item)
   <div class="card mb-2 knowledge-card" data-search="{{ Str::lower($item->title.' '.$item->category.' '.$item->content) }}"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2"><div><strong>{{ $item->title }}</strong> @if($item->category)<span class="badge bg-label-info ms-1">{{ $item->category }}</span>@endif <span class="badge bg-label-{{ $item->is_active?'success':'secondary' }}">{{ $item->is_active?'Active':'Disabled' }}</span></div><div class="d-flex gap-1"><button class="btn btn-sm btn-label-primary" type="button" data-bs-toggle="collapse" data-bs-target="#knowledge-edit-{{ $item->id }}">Edit</button><form method="post" action="{{ route('ai.knowledge.toggle',$item->id) }}">@csrf<button class="btn btn-sm btn-label-secondary">{{ $item->is_active?'Disable':'Enable' }}</button></form><form method="post" action="{{ route('ai.knowledge.delete',$item->id) }}" onsubmit="return confirm('Delete this knowledge item?')">@csrf @method('DELETE')<button class="btn btn-sm btn-label-danger">Delete</button></form></div></div>
    <div class="small text-muted mt-2" style="white-space:pre-wrap">{{ Str::limit($item->content,800) }}</div>
    <div class="collapse mt-3" id="knowledge-edit-{{ $item->id }}"><form method="post" action="{{ route('ai.knowledge.update',$item->id) }}">@csrf @method('PUT')<div class="row g-2"><div class="col-md-7"><input name="title" class="form-control" required value="{{ $item->title }}"></div><div class="col-md-5"><input name="category" class="form-control" value="{{ $item->category }}" placeholder="Category"></div></div><textarea name="content" class="form-control mt-2" rows="8" required>{{ $item->content }}</textarea><button class="btn btn-primary btn-sm mt-2">Save Changes</button></form></div>
   </div></div>
  @empty<div class="card"><div class="card-body text-muted">No knowledge items yet.</div></div>@endforelse
 </div>
</div>

<div class="modal fade" id="knowledge-import" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Import Knowledge JSON</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><form method="post" action="{{ route('ai.knowledge.import') }}" enctype="multipart/form-data">@csrf<div class="modal-body"><input type="file" name="file" class="form-control" accept="application/json,.json" required><div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="replace_existing" value="1" id="replace-knowledge"><label class="form-check-label" for="replace-knowledge">Replace existing knowledge first</label></div><small class="text-muted d-block mt-2">Without this option, imported items are added to the current knowledge base.</small></div><div class="modal-footer"><button class="btn btn-primary">Import</button></div></form></div></div></div>
@endsection
@push('js')
<script>
(function(){
 $('#toggle-ai-key').on('click',function(){const el=document.getElementById('ai-api-key');el.type=el.type==='password'?'text':'password';$(this).find('i').toggleClass('ti-eye ti-eye-off');});
 $('#knowledge-search').on('input',function(){const q=this.value.toLowerCase().trim();$('.knowledge-card').each(function(){$(this).toggle(!q||String($(this).data('search')).includes(q));});});
})();
</script>
@endpush
