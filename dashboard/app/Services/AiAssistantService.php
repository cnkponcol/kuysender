<?php

namespace App\Services;

use App\Models\AiKnowledgeItem;
use App\Models\AiSetting;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiAssistantService
{
    public function shouldRun(AiSetting $setting, Contact $contact): bool
    {
        if (!$setting->enabled || $setting->mode === 'off' || $contact->human_takeover || $contact->blocklisted_at) {
            return false;
        }
        if ($contact->ai_paused_until && $contact->ai_paused_until->isFuture()) {
            return false;
        }
        if ($setting->mode === 'out_of_hours') {
            return !$this->insideBusinessHours($setting->business_hours ?? []);
        }
        return true;
    }

    private function insideBusinessHours(array $hours): bool
    {
        if ($hours === []) {
            return false;
        }
        $day = strtolower(now()->format('D'));
        $rule = $hours[$day] ?? null;
        if (!$rule || empty($rule['start']) || empty($rule['end'])) {
            return false;
        }
        $now = now()->format('H:i');
        return $now >= $rule['start'] && $now <= $rule['end'];
    }

    public function generate(AiSetting $setting, string $sessionId, string $chatJid): string
    {
        $url = $setting->provider_url ?: config('services.ai.provider_url');
        $apiKey = $setting->api_key ?: config('services.ai.api_key');
        $model = $setting->model ?: config('services.ai.model');
        if (!$url || !$apiKey || !$model) {
            throw new RuntimeException('AI provider is not configured.');
        }

        $url = rtrim($url, '/');
        if (str_ends_with($url, '/v1')) {
            $url .= '/chat/completions';
        }

        SafeOutboundUrl::assert(
            $url,
            app()->environment('production'),
            config('services.ai.trusted_internal_targets', [])
        );

        $max = max(4, min(30, (int) ($setting->max_context_messages ?: config('services.ai.max_context_messages', 6))));
        $history = Message::where('session_id', $sessionId)
            ->where('chat_jid', $chatJid)
            ->orderByDesc('message_at')
            ->limit($max)
            ->get()
            ->reverse();

        $recentInbound = $history
            ->filter(fn ($row) => $row->direction !== 'outbound' && !empty($row->body))
            ->pluck('body')
            ->take(-3)
            ->implode(' ');

        $knowledgeItems = AiKnowledgeItem::where('session_id', $sessionId)
            ->where('is_active', true)
            ->limit(100)
            ->get(['title', 'content']);

        $normalized = mb_strtolower($recentInbound);
        $terms = preg_split('/[^\pL\pN]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = ['yang','dan','atau','ini','itu','ada','apa','bisa','buat','untuk','dari','dengan','saya','aku','gan','agan','bang','min','mau','minta','berapa','harga','produk','ready','stok','beli','pesan','order','link','pembelian','detail','fitur','info','informasi'];
        $terms = array_values(array_unique(array_filter($terms, fn ($term) => mb_strlen($term) >= 3 && !in_array($term, $stopWords, true))));

        $ranked = $knowledgeItems->map(function ($item) use ($terms) {
            $title = mb_strtolower($item->title);
            $content = mb_strtolower($item->content);
            $score = 0;
            foreach ($terms as $term) {
                if (str_contains($title, $term)) $score += 6;
                if (str_contains($content, $term)) $score += 1;
            }
            return ['item' => $item, 'score' => $score];
        })->sortByDesc('score')->values();

        $matched = $ranked->filter(fn ($row) => $row['score'] > 0)->take(1);
        if ($matched->isNotEmpty()) {
            $knowledge = $matched->map(fn ($row) => $row['item']->title."\n".$row['item']->content)->implode("\n\n");
        } else {
            $catalog = $knowledgeItems->map(function ($item) {
                preg_match('/Link pembelian\s*:\s*(https?:\/\/\S+)/i', $item->content, $match);
                return $item->title.(!empty($match[1]) ? ' | '.$match[1] : '');
            })->implode("\n");
            $knowledge = $catalog !== '' ? "KATALOG PRODUK RINGKAS:\n".$catalog : '';
        }

        $system = trim((string) ($setting->system_prompt ?: 'You are a concise WhatsApp customer service assistant. Only use known facts. If unsure, ask the customer to wait for a human admin.'));
        if ($knowledge !== '') {
            $system .= "\n\nKNOWLEDGE BASE:\n".$knowledge;
        }

        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($history as $row) {
            if (!$row->body) continue;
            $messages[] = [
                'role' => $row->direction === 'outbound' ? 'assistant' : 'user',
                'content' => $row->body,
            ];
        }

        $response = Http::timeout(45)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($url, [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.3,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('AI provider returned HTTP '.$response->status());
        }

        $text = $response->json('choices.0.message.content')
            ?: $response->json('output_text')
            ?: $response->json('response');

        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }

        return trim($text);
    }
}
