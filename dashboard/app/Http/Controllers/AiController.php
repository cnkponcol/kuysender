<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\AiKnowledgeItem;
use App\Models\AiSetting;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AiController extends Controller
{
    private function main(Request $request): Session
    {
        $id = session('main_device');
        abort_unless($id, 404, 'Select a device first.');
        return Session::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
    }

    private function knowledge(Request $request, int $id): AiKnowledgeItem
    {
        $session = $this->main($request);
        return AiKnowledgeItem::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('session_id', $session->id)
            ->firstOrFail();
    }

    public function index(Request $request)
    {
        $session = $this->main($request);
        $setting = AiSetting::firstOrCreate(
            ['user_id' => $request->user()->id, 'session_id' => $session->id],
            ['mode' => 'off', 'enabled' => false, 'max_context_messages' => 12]
        );
        $knowledge = AiKnowledgeItem::where('user_id', $request->user()->id)
            ->where('session_id', $session->id)
            ->latest()->get();
        return Lyn::view('ai.index', compact('session', 'setting', 'knowledge'));
    }

    public function update(Request $request)
    {
        $session = $this->main($request);
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'mode' => ['required', Rule::in(['off', 'suggest', 'auto', 'out_of_hours'])],
            'provider_url' => ['nullable', 'url:http,https', 'max:2048'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'model' => ['nullable', 'string', 'max:190'],
            'system_prompt' => ['nullable', 'string', 'max:20000'],
            'max_context_messages' => ['required', 'integer', 'min:4', 'max:30'],
            'business_start' => ['nullable', 'date_format:H:i'],
            'business_end' => ['nullable', 'date_format:H:i'],
        ]);
        $setting = AiSetting::firstOrCreate(['user_id' => $request->user()->id, 'session_id' => $session->id]);
        $hours = null;
        if (!empty($data['business_start']) && !empty($data['business_end'])) {
            $hours = [];
            foreach (['mon','tue','wed','thu','fri','sat','sun'] as $day) {
                $hours[$day] = ['start' => $data['business_start'], 'end' => $data['business_end']];
            }
        }
        $update = [
            'enabled' => $request->boolean('enabled'),
            'mode' => $data['mode'],
            'provider_url' => $data['provider_url'] ?: null,
            'model' => $data['model'] ?: null,
            'system_prompt' => $data['system_prompt'] ?: null,
            'max_context_messages' => $data['max_context_messages'],
            'business_hours' => $hours,
        ];
        if (!empty($data['api_key'])) $update['api_key'] = $data['api_key'];
        $setting->update($update);
        return back()->with('success', 'AI assistant settings saved.');
    }

    public function clearPrompt(Request $request)
    {
        $session = $this->main($request);
        AiSetting::where('user_id', $request->user()->id)->where('session_id', $session->id)->update(['system_prompt' => null]);
        return back()->with('success', 'System prompt cleared.');
    }

    public function knowledgeStore(Request $request)
    {
        $session = $this->main($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'category' => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:50000'],
        ]);
        AiKnowledgeItem::create([
            'user_id' => $request->user()->id,
            'session_id' => $session->id,
            'title' => $data['title'],
            'category' => $data['category'] ?: null,
            'content' => $data['content'],
            'is_active' => true,
        ]);
        return back()->with('success', 'Knowledge item added.');
    }

    public function knowledgeUpdate(Request $request, int $id)
    {
        $item = $this->knowledge($request, $id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'category' => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:50000'],
        ]);
        $item->update([
            'title' => $data['title'],
            'category' => $data['category'] ?: null,
            'content' => $data['content'],
        ]);
        return back()->with('success', 'Knowledge item updated.');
    }

    public function knowledgeToggle(Request $request, int $id)
    {
        $item = $this->knowledge($request, $id);
        $item->update(['is_active' => !$item->is_active]);
        return back()->with('success', $item->fresh()->is_active ? 'Knowledge item enabled.' : 'Knowledge item disabled.');
    }

    public function knowledgeDelete(Request $request, int $id)
    {
        $this->knowledge($request, $id)->delete();
        return back()->with('success', 'Knowledge item deleted.');
    }

    public function knowledgeExport(Request $request)
    {
        $session = $this->main($request);
        $items = AiKnowledgeItem::where('user_id', $request->user()->id)
            ->where('session_id', $session->id)
            ->orderBy('id')
            ->get(['title', 'category', 'content', 'is_active'])
            ->map(fn ($item) => [
                'title' => $item->title,
                'category' => $item->category,
                'content' => $item->content,
                'is_active' => (bool) $item->is_active,
            ])->values()->all();

        $payload = json_encode([
            'format' => 'kuysender-ai-knowledge',
            'version' => 1,
            'device_id' => $session->id,
            'device_name' => $session->session_name,
            'exported_at' => now()->toIso8601String(),
            'items' => $items,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $session->session_name) ?: 'device';
        return response($payload, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="kuysender-knowledge-'.$safe.'-'.date('Ymd-His').'.json"',
        ]);
    }

    public function knowledgeImport(Request $request)
    {
        $session = $this->main($request);
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:5120'],
            'replace_existing' => ['nullable', 'boolean'],
        ]);

        $decoded = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);
        if (!is_array($decoded)) return back()->withErrors(['file' => 'Invalid JSON file.']);
        $items = isset($decoded['items']) && is_array($decoded['items']) ? $decoded['items'] : $decoded;
        if (!array_is_list($items) || count($items) > 2000) return back()->withErrors(['file' => 'Knowledge import must contain an items array with at most 2000 rows.']);

        $clean = [];
        foreach ($items as $index => $row) {
            if (!is_array($row)) return back()->withErrors(['file' => 'Invalid row at index '.$index.'.']);
            $title = trim((string) ($row['title'] ?? ''));
            $content = trim((string) ($row['content'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            if ($title === '' || mb_strlen($title) > 190 || $content === '' || mb_strlen($content) > 50000 || mb_strlen($category) > 120) {
                return back()->withErrors(['file' => 'Invalid title/category/content at row '.($index + 1).'.']);
            }
            $clean[] = [
                'title' => $title,
                'category' => $category !== '' ? $category : null,
                'content' => $content,
                'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
            ];
        }

        DB::transaction(function () use ($request, $session, $clean) {
            if ($request->boolean('replace_existing')) {
                AiKnowledgeItem::where('user_id', $request->user()->id)->where('session_id', $session->id)->delete();
            }
            foreach ($clean as $row) {
                AiKnowledgeItem::create($row + ['user_id' => $request->user()->id, 'session_id' => $session->id]);
            }
        });

        return back()->with('success', count($clean).' knowledge items imported.');
    }
}
