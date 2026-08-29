<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileManager extends Controller
{
    private const ALLOWED_EXTENSIONS = ['jpg','jpeg','png','webp','gif','pdf','txt','doc','docx','xls','xlsx','ppt','pptx'];
    private function directory(Request $request): string
    {
        $base = (string) $request->user()->id;
        $requested = trim(str_replace('\\', '/', (string) $request->input('subfolder', $base)), '/');
        if ($requested === '') $requested = $base;
        abort_if(str_contains($requested, '..') || ($requested !== $base && !str_starts_with($requested, $base.'/')), 403);
        return $requested;
    }

    public function index(Request $request)
    {
        $dir = $this->directory($request);
        if (!Storage::exists($dir)) Storage::makeDirectory($dir);
        $files = collect(Storage::files($dir))
            ->reject(fn ($f) => in_array(basename($f), ['.gitignore', '.gitkeep'], true))
            ->filter(fn ($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true))
            ->map(fn ($file) => [
            'path' => $file, 'filename' => basename($file), 'mime' => explode('/', Storage::mimeType($file) ?: 'application/octet-stream')[0], 'ext' => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
        ])->values()->all();
        return view('ilsya.files.index', ['files' => $files, 'subfolder' => $dir, 'ismain' => $request->boolean('ismain')]);
    }

    public function upload(Request $request)
    {
        $dir = $this->directory($request);
        $request->validate(['file' => ['required', 'file', 'max:15360', 'mimes:jpg,jpeg,png,webp,gif,pdf,txt,doc,docx,xls,xlsx,ppt,pptx']]);
        $file = $request->file('file');
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $ext = strtolower($file->getClientOriginalExtension());
        $name = $base.'.'.$ext;
        if (Storage::exists($dir.'/'.$name)) $name = $base.'-'.Str::lower(Str::random(8)).'.'.$ext;
        $path = $file->storeAs($dir, $name);
        return response()->json(['message' => 'File uploaded successfully.', 'data' => [
            'path' => $path, 'filename' => $name, 'mime' => explode('/', Storage::mimeType($path) ?: 'application/octet-stream')[0], 'ext' => $ext,
        ]]);
    }

    public function delete(Request $request)
    {
        $request->validate(['file' => ['required', 'string', 'max:2048']]);
        $base = $request->user()->id.'/';
        $path = ltrim(str_replace('\\', '/', $request->input('file')), '/');
        abort_if(str_contains($path, '..') || !str_starts_with($path, $base), 403);
        if (Storage::exists($path)) Storage::delete($path);
        return response()->json(['status' => 'success', 'message' => 'File deleted successfully.']);
    }
}
