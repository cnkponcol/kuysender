@extends('dash.layouts.app')
@section('title','API DOCS')
@section('content')
<div class="row g-4"><div class="col-12 col-xl-8"><div class="card"><div class="card-body"><h4>REST API v1</h4><p class="text-muted">Create a separate API Client for every website or application. Never put the token in frontend JavaScript.</p>
<div class="alert alert-info">Base URL: <code>{{ url('/api/v1') }}</code></div>
<h5>Authentication</h5><pre class="bg-dark text-white p-3 rounded"><code>Authorization: Bearer kuy_KEY_ID.SECRET</code></pre>
<h5 class="mt-4">Send text</h5><pre class="bg-dark text-white p-3 rounded"><code>POST {{ url('/api/v1/messages/send') }}
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "session_id": "{{ $device?->id ?: 'DEVICE_UUID' }}",
  "receiver": "628123456789",
  "message_type": "text",
  "data": {"message": "Halo dari API"}
}</code></pre>
<h5 class="mt-4">Send media</h5><pre class="bg-dark text-white p-3 rounded"><code>{
  "session_id": "{{ $device?->id ?: 'DEVICE_UUID' }}",
  "receiver": "628123456789",
  "message_type": "media",
  "data": {
    "url": "https://example.com/image.jpg",
    "media_type": "image",
    "caption": "Hello"
  }
}</code></pre>
<h5 class="mt-4">Device endpoints</h5><pre class="bg-dark text-white p-3 rounded"><code>GET  /api/v1/devices
GET  /api/v1/devices/{session_id}
POST /api/v1/devices/{session_id}/connect
POST /api/v1/devices/{session_id}/logout</code></pre>
<h5 class="mt-4">Contacts and Inbox</h5><pre class="bg-dark text-white p-3 rounded"><code>GET  /api/v1/contacts?session_id={uuid}
POST /api/v1/contacts
POST /api/v1/contacts/{id}/opt-out
GET  /api/v1/inbox?session_id={uuid}
GET  /api/v1/inbox/{session_id}/{chat_jid}
POST /api/v1/inbox/{session_id}/{chat_jid}/reply</code></pre>
</div></div></div>
<div class="col-12 col-xl-4"><div class="card"><div class="card-body"><h5>Webhook Security</h5><p>Each API Client gets its own webhook secret. Verify the signature before trusting an event.</p><div class="small"><code>X-KuySender-Event</code><br><code>X-KuySender-Timestamp</code><br><code>X-KuySender-Signature</code></div><hr><p class="small text-muted">Signature payload:</p><pre class="bg-dark text-white p-2 rounded"><code>HMAC_SHA256(
 timestamp + "." + raw_body,
 webhook_secret
)</code></pre><p class="small text-muted mb-0">Reject stale timestamps and invalid signatures on the receiving website.</p></div></div><div class="card mt-4"><div class="card-body"><h5>Scopes</h5><code>messages:send</code><br><code>devices:read</code><br><code>devices:manage</code><br><code>contacts:read</code><br><code>contacts:write</code><br><code>inbox:read</code><br><code>inbox:reply</code></div></div></div></div>
@endsection
