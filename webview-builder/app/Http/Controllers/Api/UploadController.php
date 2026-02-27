<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadController extends Controller
{
    /**
     * POST /api/upload
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $dir = 'uploads/' . Str::random(8);
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            . '-' . Str::random(4)
            . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs($dir, $filename, 'public');

        return response()->json([
            'path' => $path,
            'url' => '/storage/' . $path,
        ]);
    }

    /**
     * GET /api/upload/preview?path=uploads/xxx/file.png
     * 업로드 파일 미리보기 (private/public 둘 다 지원)
     */
    public function preview(Request $request): StreamedResponse|JsonResponse
    {
        $path = $request->query('path');
        if (! $path || ! preg_match('#^uploads/[a-zA-Z0-9_-]+/[a-zA-Z0-9_.-]+$#', $path)) {
            return response()->json(['error' => 'Invalid path'], 400);
        }

        $candidates = [
            storage_path('app/public/' . $path),
            storage_path('app/private/' . $path),
        ];
        foreach ($candidates as $full) {
            if (File::exists($full) && File::isFile($full)) {
                return response()->streamDownload(
                    fn () => readfile($full),
                    basename($path),
                    ['Content-Type' => mime_content_type($full) ?: 'application/octet-stream'],
                );
            }
        }
        return response()->json(['error' => 'File not found'], 404);
    }
}
