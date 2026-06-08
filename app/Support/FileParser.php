<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Turns an uploaded file into something the AI assistant can consume:
 *  - images  → a base64 data URL for OpenAI vision
 *  - PDF/text → extracted plain text (length-capped to bound tokens)
 */
class FileParser
{
    private const MAX_TEXT = 12000;

    /**
     * @return array{type: string, name: string, data_url?: string, content?: string}
     */
    public static function parse(UploadedFile $file): array
    {
        $name = $file->getClientOriginalName();
        $mime = (string) $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());

        if (Str::startsWith($mime, 'image/')) {
            $data = base64_encode((string) file_get_contents($file->getRealPath()));

            return ['type' => 'image', 'name' => $name, 'data_url' => "data:{$mime};base64,{$data}"];
        }

        if ($mime === 'application/pdf' || $ext === 'pdf') {
            try {
                $text = (new PdfParser)->parseFile($file->getRealPath())->getText();
            } catch (\Throwable $e) {
                $text = '';
            }

            return ['type' => 'text', 'name' => $name, 'content' => self::clip($text ?: '(could not extract text from this PDF)')];
        }

        // Treat everything else as plain text (txt, csv, json, logs, …).
        return ['type' => 'text', 'name' => $name, 'content' => self::clip((string) file_get_contents($file->getRealPath()))];
    }

    private static function clip(string $text): string
    {
        return Str::limit(trim($text), self::MAX_TEXT, '... [truncated]');
    }
}
