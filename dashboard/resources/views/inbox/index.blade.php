@extends('dash.layouts.app')

@section('title', 'INBOX')

@push('cssvendor')
<style>
    .wa-shell{height:calc(100vh - 9rem);min-height:620px;border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 6px 28px rgba(0,0,0,.08)}
    .wa-sidebar{height:100%;flex-direction:column;background:#fff;border-right:1px solid #e8ecef}
    .wa-side-head{background:#f0f2f5;padding:12px 14px;border-bottom:1px solid #e3e7e9}
    .wa-avatar{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#dfe5e7;color:#455a64;font-weight:700;flex:none}
    .wa-avatar.sm{width:38px;height:38px;font-size:.86rem}
    .wa-search{background:#f0f2f5;border:0;border-radius:9px;padding:9px 12px 9px 38px;width:100%;outline:none}
    .wa-search-wrap{position:relative}.wa-search-wrap i{position:absolute;left:13px;top:10px;color:#667781}
    .wa-chat-list{overflow-y:auto;flex:1;background:#fff}
    .wa-chat-row{display:flex;gap:11px;padding:11px 13px;text-decoration:none;color:inherit;border-bottom:1px solid #f0f2f5;transition:.15s}
    .wa-chat-row:hover,.wa-chat-row.active{background:#f0f2f5;color:inherit}
    .wa-chat-main{min-width:0;flex:1}.wa-chat-title{font-size:.97rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .wa-chat-preview{font-size:.84rem;color:#667781;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
    .wa-chat-time{font-size:.72rem;color:#8696a0;white-space:nowrap}.wa-unread{background:#25d366;color:#fff;border-radius:999px;min-width:20px;height:20px;padding:0 6px;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700}
    .wa-pane{height:100%;flex-direction:column;background:#efeae2}
    .wa-topbar{height:64px;display:flex;align-items:center;padding:9px 14px;background:#075e54;color:#fff;gap:10px;flex:none}
    .wa-topbar .wa-avatar{background:#d9e3e1;color:#075e54}.wa-contact{min-width:0;flex:1}.wa-contact strong{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.wa-contact small{opacity:.82}
    .wa-messages{flex:1;overflow-y:auto;padding:18px clamp(12px,4vw,64px);background-color:#efeae2;background-image:radial-gradient(rgba(17,27,33,.045) 1px,transparent 1px);background-size:18px 18px;scroll-behavior:smooth}
    .wa-line{display:flex;margin:3px 0}.wa-line.out{justify-content:flex-end}.wa-bubble{position:relative;max-width:min(78%,680px);padding:7px 9px 5px;border-radius:8px;background:#fff;box-shadow:0 1px 1px rgba(0,0,0,.08);color:#111b21;word-break:break-word}
    .wa-line.out .wa-bubble{background:#d9fdd3}.wa-body{white-space:pre-wrap;line-height:1.35;padding-right:4px}.wa-meta{text-align:right;font-size:.67rem;color:#667781;min-width:66px;margin-left:8px;white-space:nowrap;display:inline-flex;justify-content:flex-end;align-items:center;gap:2px;float:right;margin-top:5px}
    .wa-ticks.read{color:#53bdeb}.wa-ai-note{clear:both;margin-top:7px;padding:6px 8px;border-radius:6px;background:rgba(0,0,0,.05);font-size:.75rem}
    .wa-date{text-align:center;margin:14px 0}.wa-date span{display:inline-block;background:#fff;padding:5px 11px;border-radius:8px;font-size:.72rem;color:#54656f;box-shadow:0 1px 1px rgba(0,0,0,.08)}
    .wa-compose{background:#f0f2f5;padding:9px 11px;display:flex;gap:8px;align-items:flex-end;flex:none}.wa-compose textarea{resize:none;min-height:42px;max-height:120px;border:0;border-radius:22px;padding:10px 15px;box-shadow:none}.wa-send{width:43px;height:43px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex:none}
    .wa-empty{height:100%;display:flex;align-items:center;justify-content:center;text-align:center;color:#667781;background:#f7f9fa}.wa-empty i{font-size:4rem;color:#b7c3c8}
    .wa-back{color:#fff;font-size:1.35rem;display:none}.wa-actions{display:flex;align-items:center;gap:6px;flex:none}.wa-actions form{margin:0}.wa-actions .btn{border-color:rgba(255,255,255,.45);color:#fff;background:rgba(255,255,255,.12)}.wa-actions .wa-delete-chat:hover{background:#dc3545;border-color:#dc3545;color:#fff}
    @media(max-width:991.98px){.wa-shell{height:calc(100vh - 7.5rem);min-height:560px;border-radius:0}.wa-sidebar{border-right:0}.wa-back{display:inline-flex}.wa-messages{padding:14px 9px}.wa-bubble{max-width:86%}.wa-actions .btn span{display:none}.wa-actions .btn{padding:.36rem .5rem}}
</style>
@endpush

@section('content')
@if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
@if(isset($errors) && $errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
<div class="wa-shell">
    <div class="row g-0 h-100">
        <div class="col-12 col-lg-4 h-100 wa-sidebar {{ $selectedJid ? 'd-none d-lg-flex' : 'd-flex' }}">
            <div class="wa-side-head">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="wa-avatar"><i class="ti ti-brand-whatsapp"></i></div>
                    <div class="flex-grow-1 min-w-0"><strong>Inbox</strong><div class="small text-muted text-truncate">{{ $session->session_name }} · {{ $session->whatsapp_number ?: 'WhatsApp' }}</div></div>
                    <span class="badge bg-label-success">{{ $session->connection_state }}</span>
                </div>
                <div class="wa-search-wrap"><i class="ti ti-search"></i><input id="wa-chat-search" class="wa-search" type="search" placeholder="Cari atau mulai chat"></div>
            </div>
            <div class="wa-chat-list" id="wa-chat-list">
                @forelse($chats as $chat)
                    @php
                        $initial = mb_strtoupper(mb_substr(trim($chat->name ?: '?'), 0, 1));
                        $chatTime = $chat->last_message_at;
                    @endphp
                    <a class="wa-chat-row {{ $selectedJid === $chat->jid ? 'active' : '' }}" data-chat-search="{{ mb_strtolower(($chat->name ?? '').' '.$chat->jid.' '.($chat->last_body ?? '')) }}" href="{{ route('inbox', ['chat' => $chat->jid]) }}">
                        <div class="wa-avatar sm">{{ $initial }}</div>
                        <div class="wa-chat-main">
                            <div class="d-flex justify-content-between gap-2"><div class="wa-chat-title">{{ $chat->name }}</div><div class="wa-chat-time">{{ $chatTime ? ($chatTime->isToday() ? $chatTime->format('H:i') : $chatTime->format('d/m')) : '' }}</div></div>
                            <div class="d-flex align-items-center gap-2"><div class="wa-chat-preview flex-grow-1">{{ $chat->last_body ?: '[media]' }}</div>@if($chat->unread)<span class="wa-unread">{{ $chat->unread > 99 ? '99+' : $chat->unread }}</span>@endif</div>
                        </div>
                    </a>
                @empty
                    <div class="p-4 text-center text-muted"><i class="ti ti-message-off fs-2 d-block mb-2"></i>Belum ada percakapan.</div>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-lg-8 h-100 wa-pane {{ $selectedJid ? 'd-flex' : 'd-none d-lg-flex' }}">
            @if($selectedJid)
                @php
                    $displayName = $selectedContact?->name ?: $selectedContact?->profile_name ?: preg_replace('/@.*/', '', $selectedJid);
                    $initial = mb_strtoupper(mb_substr(trim($displayName ?: '?'), 0, 1));
                    $lastDate = null;
                @endphp
                <div class="wa-topbar">
                    <a href="{{ route('inbox') }}" class="wa-back"><i class="ti ti-arrow-left"></i></a>
                    <div class="wa-avatar sm">{{ $initial }}</div>
                    <div class="wa-contact"><strong>{{ $displayName }}</strong><small>{{ $selectedJid }}</small></div>
                    <div class="wa-actions">
                        <form class="wa-takeover" method="post" action="{{ route('inbox.takeover') }}">
                            @csrf
                            <input type="hidden" name="chat_jid" value="{{ $selectedJid }}">
                            <input type="hidden" name="enabled" value="{{ $selectedContact?->human_takeover ? 0 : 1 }}">
                            <button class="btn btn-sm"><i class="ti {{ $selectedContact?->human_takeover ? 'ti-robot' : 'ti-user-check' }} me-1"></i><span>{{ $selectedContact?->human_takeover ? 'Aktifkan AI' : 'Ambil Alih' }}</span></button>
                        </form>
                        <form method="post" action="{{ route('inbox.delete') }}" onsubmit="return confirm('Hapus seluruh pesan di chat ini dari Inbox KuySender? Pesan di WhatsApp HP tidak ikut terhapus.')">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="chat_jid" value="{{ $selectedJid }}">
                            <button class="btn btn-sm wa-delete-chat" type="submit" title="Hapus chat"><i class="ti ti-trash me-1"></i><span>Hapus</span></button>
                        </form>
                    </div>
                </div>
                <div class="wa-messages" id="wa-messages">
                    @foreach($messages as $message)
                        @php
                            $dateKey = optional($message->message_at)->format('Y-m-d');
                            $dateLabel = optional($message->message_at)->isToday() ? 'Hari ini' : (optional($message->message_at)->isYesterday() ? 'Kemarin' : optional($message->message_at)->translatedFormat('d M Y'));
                            $status = (string) $message->status;
                        @endphp
                        @if($dateKey && $dateKey !== $lastDate)<div class="wa-date"><span>{{ $dateLabel }}</span></div>@php $lastDate = $dateKey; @endphp @endif
                        <div class="wa-line {{ $message->direction === 'outbound' ? 'out' : 'in' }}">
                            <div class="wa-bubble">
                                <div class="wa-body">{{ $message->body ?: '['.$message->message_type.']' }}</div>
                                @if($message->ai_suggestion)<div class="wa-ai-note"><i class="ti ti-sparkles me-1"></i>{{ $message->ai_suggestion }}</div>@endif
                                <span class="wa-meta">
                                    {{ optional($message->message_at)->format('H:i') }}
                                    @if($message->direction === 'outbound')
                                        @if(in_array($status, ['delivered','read','played']))<i class="ti ti-checks wa-ticks {{ in_array($status,['read','played']) ? 'read' : '' }}"></i>@else<i class="ti ti-check wa-ticks"></i>@endif
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <form method="post" action="{{ route('inbox.reply') }}" class="wa-compose" id="wa-reply-form">
                    @csrf
                    <input type="hidden" name="chat_jid" value="{{ $selectedJid }}">
                    <textarea class="form-control" name="message" rows="1" required maxlength="10000" placeholder="Ketik pesan"></textarea>
                    <button class="btn btn-success wa-send" type="submit" title="Kirim"><i class="ti ti-send"></i></button>
                </form>
            @else
                <div class="wa-empty"><div><i class="ti ti-messages d-block mb-3"></i><h5>KuySender Inbox</h5><p class="mb-0">Pilih percakapan untuk mulai membalas.</p></div></div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
(function(){
    const search=document.getElementById('wa-chat-search');
    if(search){search.addEventListener('input',function(){const q=this.value.trim().toLowerCase();document.querySelectorAll('#wa-chat-list .wa-chat-row').forEach(el=>{el.style.display=!q||el.dataset.chatSearch.includes(q)?'flex':'none';});});}
    const box=document.getElementById('wa-messages'); if(box){box.scrollTop=box.scrollHeight;}
    const form=document.getElementById('wa-reply-form'); if(form){const ta=form.querySelector('textarea');ta.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();if(ta.value.trim())form.requestSubmit();}});}
})();
</script>
@endpush
