<?php

namespace App\Helpers;

use App\Models\Session;
use Illuminate\Support\Facades\Storage;

class Lyn
{

    public static function view($view, $data = [], $mergeData = [])
    {
        $data['auth'] = auth()->user();
        $main_device = Session::where(['id' => session()->get('main_device'), 'user_id' => $data['auth']->id]);
        if (!$main_device->exists()) session()->forget('main_device');
        $data['main_device'] = $main_device->first();
        return view($view, $data, $mergeData);
    }

    public static function unique_apikey($length = 32)
    {
        do {
            $token = bin2hex(random_bytes($length));
        } while (Session::where(['api_key' => $token])->exists());
        return $token;
    }

    public static function thousandsCurrencyFormat($num)
    {

        if ($num > 1000) {

            $x = round($num);
            $x_number_format = number_format($x);
            $x_array = explode(',', $x_number_format);
            $x_parts = array('k', 'm', 'b', 't');
            $x_count_parts = count($x_array) - 1;
            $x_display = $x;
            $x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
            $x_display .= $x_parts[$x_count_parts - 1];

            return $x_display;
        }

        return $num;
    }

    /**
     * Build and persist a validated message payload.
     *
     * Kept under the legacy method name for backwards compatibility with old controllers.
     */
    public static function genereate_message($table, $request, $type)
    {
        $messageType = $type === 'save' ? (string) $request->message_type : (string) $table->message_type;
        if (!in_array($messageType, ['text', 'media', 'button', 'list'], true)) {
            abort(422, 'Unsupported message type.');
        }

        $request->validate([
            'message' => ['nullable', 'string', 'max:10000'],
            'footer' => ['nullable', 'string', 'max:1000'],
            'title' => ['nullable', 'string', 'max:500'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'quoted' => ['nullable', 'in:yes,no'],
            'btn_display' => ['nullable', 'array', 'max:20'],
            'btn_display.*' => ['nullable', 'string', 'max:250'],
            'btn_id' => ['nullable', 'array', 'max:20'],
            'btn_id.*' => ['nullable', 'string', 'max:250'],
            'type' => ['nullable', 'array', 'max:20'],
            'type.*' => ['nullable', 'in:section,option'],
        ]);

        if ($messageType === 'text') {
            $request->validate(['message' => ['required', 'string', 'max:10000']]);
            $data = ['message' => $request->string('message')->toString()];
        } elseif ($messageType === 'media') {
            $request->validate([
                'media' => ['nullable', 'url:http,https', 'max:2048'],
                'media_storage_path' => ['nullable', 'string', 'max:2048'],
                'media_type' => ['required', 'in:image,video,audio,document'],
            ]);
            $storagePath = ltrim(str_replace('\\', '/', (string) $request->input('media_storage_path')), '/');
            if ($storagePath !== '') {
                $prefix = auth()->id().'/';
                abort_if(str_contains($storagePath, '..') || !str_starts_with($storagePath, $prefix), 403, 'Invalid media path.');
                abort_unless(Storage::exists($storagePath), 422, 'Selected media file no longer exists.');
                $ext = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
                abort_unless(in_array($ext, ['jpg','jpeg','png','webp','gif','pdf','txt','doc','docx','xls','xlsx','ppt','pptx'], true), 422, 'Unsupported media file type.');
                $data = [
                    'local_path' => Storage::path($storagePath),
                    'storage_path' => $storagePath,
                    'filename' => basename($storagePath),
                    'media_type' => (string) $request->media_type,
                    'caption' => (string) ($request->message ?? ''),
                ];
            } else {
                $request->validate(['media' => ['required', 'url:http,https', 'max:2048']]);
                $data = [
                    'url' => (string) $request->media,
                    'media_type' => (string) $request->media_type,
                    'caption' => (string) ($request->message ?? ''),
                ];
            }
        } elseif ($messageType === 'button') {
            $request->validate(['message' => ['required', 'string', 'max:10000']]);
            $buttons = [];
            foreach ((array) $request->input('btn_display', []) as $key => $label) {
                $label = trim((string) $label);
                if ($label === '') continue;
                $buttons[] = ['display' => $label, 'id' => (string) $request->input("btn_id.$key", '')];
            }
            if ($buttons === []) abort(422, 'At least one button is required.');
            $data = [
                'message' => (string) $request->message,
                'footer' => (string) ($request->footer ?? ''),
                'buttons' => $buttons,
            ];
        } else {
            $request->validate(['message' => ['required', 'string', 'max:10000']]);
            $sections = [];
            foreach ((array) $request->input('btn_display', []) as $key => $label) {
                $label = trim((string) $label);
                if ($label === '') continue;
                if ($request->input("type.$key") === 'section') {
                    $sections[] = ['title' => $label, 'rows' => []];
                    continue;
                }
                if ($sections === []) $sections[] = ['title' => '', 'rows' => []];
                $sections[array_key_last($sections)]['rows'][] = [
                    'title' => $label,
                    'rowId' => (string) $request->input("btn_id.$key", ''),
                ];
            }
            if ($sections === [] || collect($sections)->sum(fn ($section) => count($section['rows'])) === 0) {
                abort(422, 'At least one list option is required.');
            }
            $data = [
                'title' => (string) ($request->title ?? ''),
                'message' => (string) $request->message,
                'footer' => (string) ($request->footer ?? ''),
                'buttonText' => (string) ($request->button_text ?? 'Choose'),
                'sections' => $sections,
            ];
        }

        if ($request->input('quoted') === 'yes') $data['quoted'] = 'yes';
        $table->message = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $table->save();
    }


}
