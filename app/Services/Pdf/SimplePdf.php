<?php

namespace App\Services\Pdf;

use Illuminate\Support\Str;

class SimplePdf
{
    protected int $width = 612;

    protected int $height = 792;

    protected int $margin = 40;

    public function fromLines(array $lines): string
    {
        $pages = $this->paginate($lines);
        $objects = [];

        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageObjectNumbers = [];
        $fontObjectNumber = 3 + (count($pages) * 2);

        foreach ($pages as $index => $page) {
            $pageObjectNumbers[] = 3 + ($index * 2);
        }

        $objects[] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', array_map(fn ($number) => "{$number} 0 R", $pageObjectNumbers)),
            count($pages)
        );

        foreach ($pages as $index => $page) {
            $pageObjectNumber = 3 + ($index * 2);
            $contentObjectNumber = $pageObjectNumber + 1;

            $objects[] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 %d 0 R >> >> /Contents %d 0 R >>',
                $this->width,
                $this->height,
                $fontObjectNumber,
                $contentObjectNumber
            );

            $stream = $this->pageStream($page);
            $objects[] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($stream), $stream);
        }

        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        return $this->document($objects);
    }

    public function fromPages(array $pages): string
    {
        [$pages, $images] = $this->prepareImages($pages);
        $objects = [];

        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageObjectNumbers = [];
        $fontObjectNumber = 3 + (count($pages) * 2);
        $imageObjectNumber = $fontObjectNumber + 1;

        foreach ($images as $hash => $image) {
            $images[$hash]['object_number'] = $imageObjectNumber++;
        }

        foreach ($pages as $index => $page) {
            $pageObjectNumbers[] = 3 + ($index * 2);
        }

        $objects[] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', array_map(fn ($number) => "{$number} 0 R", $pageObjectNumbers)),
            count($pages)
        );

        foreach ($pages as $index => $page) {
            $pageObjectNumber = 3 + ($index * 2);
            $contentObjectNumber = $pageObjectNumber + 1;

            $objects[] = $this->pageObject($page, $fontObjectNumber, $contentObjectNumber, $images);

            $stream = $this->elementsStream($page);
            $objects[] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($stream), $stream);
        }

        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        foreach ($images as $image) {
            $objects[] = $this->imageObject($image);
        }

        return $this->document($objects);
    }

    protected function prepareImages(array $pages): array
    {
        $images = [];
        $preparedPages = [];

        foreach ($pages as $page) {
            $preparedPage = [];

            foreach ($page as $element) {
                if (($element['type'] ?? null) !== 'image' || empty($element['data'])) {
                    $preparedPage[] = $element;
                    continue;
                }

                $hash = sha1($element['data']);

                if (! isset($images[$hash])) {
                    $images[$hash] = [
                        'name' => 'Im' . (count($images) + 1),
                        'data' => $element['data'],
                        'width' => (int) ($element['image_width'] ?? 1),
                        'height' => (int) ($element['image_height'] ?? 1),
                    ];
                }

                $element['image_name'] = $images[$hash]['name'];
                $preparedPage[] = $element;
            }

            $preparedPages[] = $preparedPage;
        }

        return [$preparedPages, $images];
    }

    protected function pageObject(array $page, int $fontObjectNumber, int $contentObjectNumber, array $images): string
    {
        $xObjects = [];

        foreach ($page as $element) {
            if (($element['type'] ?? null) !== 'image' || empty($element['image_name'])) {
                continue;
            }

            foreach ($images as $image) {
                if ($image['name'] === $element['image_name']) {
                    $xObjects[$image['name']] = $image['object_number'];
                    break;
                }
            }
        }

        $xObjectResource = '';

        if ($xObjects) {
            $refs = collect($xObjects)
                ->map(fn ($objectNumber, $name) => '/' . $name . ' ' . $objectNumber . ' 0 R')
                ->implode(' ');
            $xObjectResource = ' /XObject << ' . $refs . ' >>';
        }

        return sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 %d 0 R >>%s >> /Contents %d 0 R >>',
            $this->width,
            $this->height,
            $fontObjectNumber,
            $xObjectResource,
            $contentObjectNumber
        );
    }

    protected function paginate(array $lines): array
    {
        $pages = [[]];
        $currentPage = 0;
        $y = $this->height - $this->margin;

        foreach ($lines as $line) {
            $text = is_array($line) ? (string) ($line['text'] ?? '') : (string) $line;
            $size = is_array($line) ? (int) ($line['size'] ?? 10) : 10;
            $gap = is_array($line) ? (int) ($line['gap'] ?? 5) : 5;
            $lineHeight = max(10, $size + $gap);
            $wrappedLines = $this->wrap($text, $size);

            if ($text === '') {
                $wrappedLines = [''];
            }

            foreach ($wrappedLines as $wrappedLine) {
                if ($y < $this->margin + $lineHeight) {
                    $pages[] = [];
                    $currentPage++;
                    $y = $this->height - $this->margin;
                }

                $pages[$currentPage][] = [
                    'text' => $wrappedLine,
                    'size' => $size,
                    'x' => $this->margin,
                    'y' => $y,
                ];

                $y -= $lineHeight;
            }
        }

        return $pages;
    }

    protected function wrap(string $text, int $size): array
    {
        $text = trim(preg_replace('/\s+/', ' ', Str::ascii($text)));

        if ($text === '') {
            return [''];
        }

        $maxChars = max(25, (int) floor(($this->width - ($this->margin * 2)) / max(5, $size * 0.52)));

        return explode("\n", wordwrap($text, $maxChars, "\n", false));
    }

    protected function pageStream(array $lines): string
    {
        $stream = '';

        foreach ($lines as $line) {
            $rgb = $this->rgb($line['color'] ?? '#111827');

            $stream .= sprintf(
                "%.3F %.3F %.3F rg\nBT /F1 %d Tf %d %d Td (%s) Tj ET\n",
                $rgb[0],
                $rgb[1],
                $rgb[2],
                $line['size'],
                $line['x'],
                $line['y'],
                $this->escape($line['text'])
            );
        }

        return rtrim($stream);
    }

    protected function elementsStream(array $elements): string
    {
        $stream = '';

        foreach ($elements as $element) {
            $type = $element['type'] ?? 'text';

            if ($type === 'rect') {
                $stream .= $this->rectStream($element);
                continue;
            }

            if ($type === 'line') {
                $stream .= $this->lineStream($element);
                continue;
            }

            if ($type === 'image') {
                $stream .= $this->imageStream($element);
                continue;
            }

            $stream .= $this->textStream($element);
        }

        return rtrim($stream);
    }

    protected function textStream(array $element): string
    {
        $text = Str::ascii((string) ($element['text'] ?? ''));
        $size = (int) ($element['size'] ?? 10);
        $x = (float) ($element['x'] ?? $this->margin);
        $y = (float) ($element['y'] ?? $this->height - $this->margin);
        $rgb = $this->rgb($element['color'] ?? '#111827');

        return sprintf(
            "%.3F %.3F %.3F rg\nBT /F1 %d Tf %.2F %.2F Td (%s) Tj ET\n",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $size,
            $x,
            $y,
            $this->escape($text)
        );
    }

    protected function rectStream(array $element): string
    {
        $x = (float) ($element['x'] ?? 0);
        $y = (float) ($element['y'] ?? 0);
        $w = (float) ($element['w'] ?? 0);
        $h = (float) ($element['h'] ?? 0);
        $rgb = $this->rgb($element['color'] ?? '#ffffff');
        $stroke = ! empty($element['stroke']);
        $strokeRgb = $this->rgb($element['stroke_color'] ?? '#e5e7eb');

        $stream = sprintf("%.3F %.3F %.3F rg\n", $rgb[0], $rgb[1], $rgb[2]);

        if ($stroke) {
            $stream .= sprintf("%.3F %.3F %.3F RG\n", $strokeRgb[0], $strokeRgb[1], $strokeRgb[2]);
            $stream .= sprintf("%.2F %.2F %.2F %.2F re B\n", $x, $y, $w, $h);
        } else {
            $stream .= sprintf("%.2F %.2F %.2F %.2F re f\n", $x, $y, $w, $h);
        }

        return $stream;
    }

    protected function lineStream(array $element): string
    {
        $rgb = $this->rgb($element['color'] ?? '#e5e7eb');

        return sprintf(
            "%.3F %.3F %.3F RG\n%.2F w\n%.2F %.2F m %.2F %.2F l S\n",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            (float) ($element['width'] ?? 1),
            (float) ($element['x1'] ?? 0),
            (float) ($element['y1'] ?? 0),
            (float) ($element['x2'] ?? 0),
            (float) ($element['y2'] ?? 0)
        );
    }

    protected function imageStream(array $element): string
    {
        if (empty($element['image_name'])) {
            return '';
        }

        return sprintf(
            "q\n%.2F 0 0 %.2F %.2F %.2F cm\n/%s Do\nQ\n",
            (float) ($element['w'] ?? 0),
            (float) ($element['h'] ?? 0),
            (float) ($element['x'] ?? 0),
            (float) ($element['y'] ?? 0),
            $element['image_name']
        );
    }

    protected function imageObject(array $image): string
    {
        $data = (string) $image['data'];

        return sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
            max(1, (int) $image['width']),
            max(1, (int) $image['height']),
            strlen($data),
            $data
        );
    }

    protected function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '000000';
        }

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    protected function document(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n";
        $pdf .= '<< /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    protected function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
    }
}
