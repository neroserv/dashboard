<?php

namespace App\Support;

use App\Models\TicketMessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TicketAttachmentPreview
{
    /**
     * @return 'image'|'pdf'|null
     */
    public static function previewKind(TicketMessageAttachment $attachment): ?string
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($attachment->path)) {
            return null;
        }
        $mime = $disk->mimeType($attachment->path) ?? '';

        if (self::isInlineImageMime($mime)) {
            return 'image';
        }

        if ($mime === 'application/pdf') {
            return 'pdf';
        }

        return null;
    }

    public static function downloadResponse(TicketMessageAttachment $attachment, Request $request): BinaryFileResponse|StreamedResponse
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        $mime = $disk->mimeType($attachment->path) ?? 'application/octet-stream';
        $allowInline = $request->boolean('inline') && (self::isInlineImageMime($mime) || $mime === 'application/pdf');

        if ($allowInline) {
            $fallback = self::asciiBasename($attachment->name);

            return response()->file($disk->path($attachment->path), [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.$fallback.'"',
            ]);
        }

        return $disk->download($attachment->path, $attachment->name);
    }

    private static function isInlineImageMime(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/pjpeg'], true);
    }

    private static function asciiBasename(string $filename): string
    {
        $base = basename($filename);
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $base) ?: 'file';

        return str_replace(['"', '\\'], '_', $ascii);
    }
}
