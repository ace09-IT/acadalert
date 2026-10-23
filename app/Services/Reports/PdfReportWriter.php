<?php

namespace App\Services\Reports;

use DateTimeInterface;

class PdfReportWriter
{
    public const PAGE_WIDTH = 595;
    public const PAGE_HEIGHT = 842;
    public const MARGIN_LEFT = 48;
    public const MARGIN_RIGHT = 48;
    public const MARGIN_TOP = 56;
    public const MARGIN_BOTTOM = 60;

    protected const HEADER_CONTENT_TOP = 124;

    protected const FONT_REGULAR = 'F1';
    protected const FONT_BOLD = 'F2';

    protected const WIDTHS_REGULAR = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    protected const WIDTHS_BOLD = [
        278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
        975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
        333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
        611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584,
    ];

    protected const WIDTHS_EXTENDED = [
        0x91 => 222, 0x92 => 222,
        0x93 => 333, 0x94 => 333,
        0x96 => 556, 0x97 => 1000,
        0xA0 => 278,
        0xB0 => 400,
    ];

    protected array $pages = [];

    protected array $images = [];

    protected int $currentPage = -1;

    protected float $y = 0.0;

    protected string $footerText = '';

    protected array $meta = [];

    /** Deterministic creation timestamp (never "now" — callers pass it in). */
    protected string $timestamp = '';

    public function __construct(string $title, string $author = 'AcadAlert', string $subject = '', ?DateTimeInterface $createdAt = null)
    {
        $created = $createdAt ?? new \DateTimeImmutable('2000-01-01 00:00:00', new \DateTimeZone('UTC'));

        $this->timestamp = 'D:' . $created->format('YmdHis') . "+00'00'";
        $this->meta = [
            'Title' => $title,
            'Author' => $author,
            'Subject' => $subject,
            'Creator' => 'AcadAlert Deterministic Report Engine',
            'Producer' => 'AcadAlert PDFReportWriter (no external libraries)',
            'CreationDate' => $this->timestamp,
            'ModDate' => $this->timestamp,
        ];

        $this->addPage();
    }


    protected function addPage(): void
    {
        $this->pages[] = [];
        $this->currentPage = count($this->pages) - 1;
        $this->y = self::PAGE_HEIGHT - ($this->images === [] ? self::MARGIN_TOP : self::HEADER_CONTENT_TOP);

        if ($this->images !== []) {
            $this->drawHeader();
        }
    }

    public static function contentWidth(): float
    {
        return self::PAGE_WIDTH - self::MARGIN_LEFT - self::MARGIN_RIGHT;
    }

    /** Ensure the given vertical space is available, otherwise start a new page. */
    public function reserve(float $height): void
    {
        if ($this->y - $height < self::MARGIN_BOTTOM) {
            $this->addPage();
        }
    }

    protected function push(string $operators): void
    {
        $this->pages[$this->currentPage][] = $operators;
    }

    public function setFooter(string $text): static
    {
        $this->footerText = $text;

        return $this;
    }

    /* CHANGED: configure existing transparent PNG logos for the repeated report header. */
    public function setHeaderLogos(string $uddLogoPath, string $acadAlertLogoPath): static
    {
        $this->images = [
            'UddLogo' => $this->loadPng($uddLogoPath),
            'AcadAlertLogo' => $this->loadPng($acadAlertLogoPath),
        ];

        $this->y = self::PAGE_HEIGHT - self::HEADER_CONTENT_TOP;
        $this->drawHeader();

        return $this;
    }

    protected function drawHeader(): void
    {
        $udd = $this->images['UddLogo'];
        $acadAlert = $this->images['AcadAlertLogo'];
        $uddHeight = 54.0;
        $uddWidth = $uddHeight * $udd['width'] / $udd['height'];
        $acadAlertHeight = 34.0;
        $acadAlertWidth = $acadAlertHeight * $acadAlert['width'] / $acadAlert['height'];
        $title = 'UNIVERSIDAD DE DAGUPAN';
        $titleSize = 15.0;
        $titleWidth = $this->measure($title, true, $titleSize);
        $gap = 12.0;
        $groupWidth = $uddWidth + $gap + $titleWidth + $gap + $acadAlertWidth;
        $groupX = (self::PAGE_WIDTH - $groupWidth) / 2;
        $rowCenter = self::PAGE_HEIGHT - 54.0;

        $this->push(sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /UddLogo Do Q',
            $uddWidth,
            $uddHeight,
            $groupX,
            $rowCenter - ($uddHeight / 2)
        ));

        $titleX = $groupX + $uddWidth + $gap;
        $this->pages[$this->currentPage][] = $this->textOperator(
            $title,
            $titleSize,
            true,
            $titleX,
            $rowCenter - ($titleSize * 0.35)
        );

        $acadAlertX = $titleX + $titleWidth + $gap;
        $this->push(sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /AcadAlertLogo Do Q',
            $acadAlertWidth,
            $acadAlertHeight,
            $acadAlertX,
            $rowCenter - ($acadAlertHeight / 2)
        ));

        $this->push(sprintf(
            '0.8 w %.2F %.2F m %.2F %.2F l S',
            self::MARGIN_LEFT,
            self::PAGE_HEIGHT - 94.0,
            self::PAGE_WIDTH - self::MARGIN_RIGHT,
            self::PAGE_HEIGHT - 94.0
        ));
    }

    protected function loadPng(string $path): array
    {
        $png = file_get_contents($path);
        if ($png === false || substr($png, 0, 8) !== "\x89PNG\r\n\x1A\n") {
            throw new \RuntimeException('Unable to read report header PNG: ' . $path);
        }

        $offset = 8;
        $idat = '';
        $palette = '';
        $transparency = '';
        $header = null;

        while ($offset + 12 <= strlen($png)) {
            $chunkLength = unpack('Nlength', substr($png, $offset, 4))['length'];
            $chunkType = substr($png, $offset + 4, 4);
            $chunkStart = $offset + 8;

            if ($chunkLength < 0 || $chunkStart + $chunkLength + 4 > strlen($png)) {
                throw new \RuntimeException('Invalid PNG chunk in report header image: ' . $path);
            }

            $chunkData = substr($png, $chunkStart, $chunkLength);
            if ($chunkType === 'IHDR') {
                $header = unpack(
                    'Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace',
                    $chunkData
                );
            } elseif ($chunkType === 'IDAT') {
                $idat .= $chunkData;
            } elseif ($chunkType === 'PLTE') {
                $palette = $chunkData;
            } elseif ($chunkType === 'tRNS') {
                $transparency = $chunkData;
            }

            $offset = $chunkStart + $chunkLength + 4;
            if ($chunkType === 'IEND') {
                break;
            }
        }

        if (
            $header === null
            || $header['width'] < 1
            || $header['height'] < 1
            || $header['bitDepth'] !== 8
            || $header['compression'] !== 0
            || $header['filter'] !== 0
            || $header['interlace'] !== 0
        ) {
            throw new \RuntimeException('Unsupported PNG format for report header image: ' . $path);
        }

        $channels = match ($header['colorType']) {
            0 => 1,
            2 => 3,
            3 => 1,
            4 => 2,
            6 => 4,
            default => throw new \RuntimeException('Unsupported PNG color type for report header image: ' . $path),
        };
        $inflated = gzuncompress($idat);
        $rowLength = $header['width'] * $channels;
        if ($inflated === false || strlen($inflated) !== ($rowLength + 1) * $header['height']) {
            throw new \RuntimeException('Unable to decompress report header PNG: ' . $path);
        }

        $rgb = '';
        $alpha = '';
        $previousRow = str_repeat("\0", $rowLength);
        $offset = 0;

        for ($rowIndex = 0; $rowIndex < $header['height']; $rowIndex++) {
            $filterType = ord($inflated[$offset]);
            $row = substr($inflated, $offset + 1, $rowLength);
            $offset += $rowLength + 1;

            for ($index = 0; $index < $rowLength; $index++) {
                $left = $index >= $channels ? ord($row[$index - $channels]) : 0;
                $above = ord($previousRow[$index]);
                $upperLeft = $index >= $channels ? ord($previousRow[$index - $channels]) : 0;
                $value = ord($row[$index]);

                $predictor = match ($filterType) {
                    0 => 0,
                    1 => $left,
                    2 => $above,
                    3 => intdiv($left + $above, 2),
                    4 => $this->paethPredictor($left, $above, $upperLeft),
                    default => throw new \RuntimeException('Unsupported PNG filter for report header image: ' . $path),
                };

                $row[$index] = chr(($value + $predictor) & 0xFF);
            }

            $previousRow = $row;

            for ($pixel = 0; $pixel < $header['width']; $pixel++) {
                $pixelOffset = $pixel * $channels;
                $pixelAlpha = 255;

                switch ($header['colorType']) {
                    case 0:
                        $gray = ord($row[$pixelOffset]);
                        $red = $green = $blue = $gray;
                        if (strlen($transparency) === 2 && $gray === unpack('n', $transparency)[1]) {
                            $pixelAlpha = 0;
                        }
                        break;
                    case 2:
                        $red = ord($row[$pixelOffset]);
                        $green = ord($row[$pixelOffset + 1]);
                        $blue = ord($row[$pixelOffset + 2]);
                        if (
                            strlen($transparency) === 6
                            && $red === unpack('n', substr($transparency, 0, 2))[1]
                            && $green === unpack('n', substr($transparency, 2, 2))[1]
                            && $blue === unpack('n', substr($transparency, 4, 2))[1]
                        ) {
                            $pixelAlpha = 0;
                        }
                        break;
                    case 3:
                        $paletteOffset = ord($row[$pixelOffset]) * 3;
                        if ($paletteOffset + 2 >= strlen($palette)) {
                            throw new \RuntimeException('Invalid PNG palette in report header image: ' . $path);
                        }
                        $red = ord($palette[$paletteOffset]);
                        $green = ord($palette[$paletteOffset + 1]);
                        $blue = ord($palette[$paletteOffset + 2]);
                        $pixelAlpha = ord($transparency[ord($row[$pixelOffset])] ?? "\xFF");
                        break;
                    case 4:
                        $gray = ord($row[$pixelOffset]);
                        $red = $green = $blue = $gray;
                        $pixelAlpha = ord($row[$pixelOffset + 1]);
                        break;
                    case 6:
                        $red = ord($row[$pixelOffset]);
                        $green = ord($row[$pixelOffset + 1]);
                        $blue = ord($row[$pixelOffset + 2]);
                        $pixelAlpha = ord($row[$pixelOffset + 3]);
                        break;
                }

                $rgb .= chr($red) . chr($green) . chr($blue);
                $alpha .= chr($pixelAlpha);
            }
        }

        $hasTransparency = strspn($alpha, "\xFF") !== strlen($alpha);
        $compressedRgb = gzcompress($rgb, 9);
        $compressedAlpha = $hasTransparency ? gzcompress($alpha, 9) : null;
        if ($compressedRgb === false || ($hasTransparency && $compressedAlpha === false)) {
            throw new \RuntimeException('Unable to encode report header PNG: ' . $path);
        }

        return [
            'width' => $header['width'],
            'height' => $header['height'],
            'rgb' => $compressedRgb,
            'alpha' => $compressedAlpha,
        ];
    }

    protected function paethPredictor(int $left, int $above, int $upperLeft): int
    {
        $estimate = $left + $above - $upperLeft;
        $leftDistance = abs($estimate - $left);
        $aboveDistance = abs($estimate - $above);
        $upperLeftDistance = abs($estimate - $upperLeft);

        if ($leftDistance <= $aboveDistance && $leftDistance <= $upperLeftDistance) {
            return $left;
        }

        return $aboveDistance <= $upperLeftDistance ? $above : $upperLeft;
    }

    public function measure(string $text, bool $bold = false, float $size = 10.0): float
    {
        $bytes = $this->toWinAnsi($text);
        $units = 0;
        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $code = ord($bytes[$i]);

            if ($code >= 32 && $code <= 126) {
                $table = $bold ? self::WIDTHS_BOLD : self::WIDTHS_REGULAR;
                $units += $table[$code - 32];
            } elseif (isset(self::WIDTHS_EXTENDED[$code])) {
                $units += self::WIDTHS_EXTENDED[$code];
            } else {
                $units += 556;
            }
        }

        return $units * $size / 1000;
    }

    public function toWinAnsi(string $text): string
    {
        $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $converted) ?? '';
    }


    public function text(string $value, float $size = 10.0, bool $bold = false, float $x = 0.0, string $align = 'left', float $width = 0.0): void
    {
        $font = $bold ? self::FONT_BOLD : self::FONT_REGULAR;
        $width = $width > 0 ? $width : self::contentWidth();
        $textWidth = $this->measure($value, $bold, $size);

        $offset = match ($align) {
            'right' => $width - $textWidth,
            'center' => ($width - $textWidth) / 2,
            default => 0.0,
        };

        $absoluteX = self::MARGIN_LEFT + $x + max(0.0, $offset);
        $encoded = $this->escape($this->toWinAnsi($value));

        $this->push(sprintf(
            'BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET',
            $font,
            $size,
            $absoluteX,
            $this->y,
            $encoded
        ));
    }

    public function heading(string $value, float $size = 14.0): static
    {
        $this->reserve($size + 16);
        $this->y -= $size;
        $this->text($value, $size, true);
        $this->y -= 5;
        $this->rule();
        $this->y -= 6;

        return $this;
    }

    public function subheading(string $value, float $size = 11.0): static
    {
        $this->reserve($size + 10);
        $this->y -= $size;
        $this->text($value, $size, true);
        $this->y -= 7;

        return $this;
    }

    public function paragraph(string $value, float $size = 10.0, bool $bold = false): static
    {
        foreach ($this->wrap($value, self::contentWidth(), $bold, $size) as $line) {
            $this->reserve($size + 6);
            $this->y -= $size + 2;
            $this->text($line, $size, $bold);
        }

        $this->y -= 4;

        return $this;
    }

    public function rule(float $thickness = 0.6): static
    {
        $this->reserve($thickness + 2);
        $this->push(sprintf(
            '%.2F w %.2F %.2F m %.2F %.2F l S',
            $thickness,
            self::MARGIN_LEFT,
            $this->y,
            self::PAGE_WIDTH - self::MARGIN_RIGHT,
            $this->y
        ));

        return $this;
    }

    public function spacer(float $height = 8.0): static
    {
        $this->reserve($height);
        $this->y -= $height;

        return $this;
    }

    public function keyValues(array $pairs, float $labelWidth = 160.0, float $size = 10.0): static
    {
        foreach ($pairs as $label => $value) {
            $this->reserve($size + 6);
            $this->y -= $size + 3;

            $this->text((string) $label, $size, true, 0.0);
            $this->text((string) $value, $size, false, $labelWidth, 'left', self::contentWidth() - $labelWidth);
        }

        $this->y -= 4;

        return $this;
    }

    public function wrap(string $value, float $width, bool $bold = false, float $size = 10.0): array
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($value === '') {
            return [''];
        }

        $lines = [];
        $current = '';

        foreach (explode(' ', $value) as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if ($current === '' || $this->measure($candidate, $bold, $size) <= $width) {
                $current = $candidate;
                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /** Escape a WinAnsi byte string for use inside a PDF literal string. */
    protected function escape(string $bytes): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $bytes);
    }


    public function table(array $headers, array $rows, ?array $weights = null, ?array $aligns = null, float $size = 9.5): static
    {
        $columns = count($headers);

        if ($columns === 0) {
            return $this;
        }

        $weights = (is_array($weights) && count($weights) === $columns)
            ? array_values($weights)
            : array_fill(0, $columns, 1.0);

        $aligns = (is_array($aligns) && count($aligns) === $columns)
            ? array_values($aligns)
            : array_fill(0, $columns, 'left');

        $totalWeight = array_sum($weights) ?: 1.0;

        $widths = [];
        $xs = [];
        $cursor = 0.0;
        foreach ($weights as $i => $weight) {
            $widths[$i] = self::contentWidth() * ($weight / $totalWeight);
            $xs[$i] = $cursor;
            $cursor += $widths[$i];
        }

        $pad = 5.0;
        $lineHeight = $size + 3.0;

        $this->drawTableHeader($headers, $xs, $widths, $aligns, $size, $lineHeight, $pad);

        if ($rows === []) {
            $this->y -= $lineHeight;
            $this->text('No data available for this section.', $size, false, 0.0);
            $this->y -= 8;

            return $this;
        }

        foreach ($rows as $row) {
            $cells = array_values($row);
            $wrapped = [];
            $maxLines = 1;

            for ($i = 0; $i < $columns; $i++) {
                $wrapped[$i] = $this->wrap((string) ($cells[$i] ?? ''), $widths[$i] - (2 * $pad), false, $size);
                $maxLines = max($maxLines, count($wrapped[$i]));
            }

            $rowHeight = $maxLines * $lineHeight;

            if ($this->y - $rowHeight < self::MARGIN_BOTTOM) {
                $this->addPage();
                $this->drawTableHeader($headers, $xs, $widths, $aligns, $size, $lineHeight, $pad);
            }

            for ($line = 0; $line < $maxLines; $line++) {
                $this->y -= $lineHeight;

                for ($i = 0; $i < $columns; $i++) {
                    $cellText = $wrapped[$i][$line] ?? '';

                    if ($cellText === '') {
                        continue;
                    }

                    $this->text($cellText, $size, false, $xs[$i] + $pad, $aligns[$i], $widths[$i] - (2 * $pad));
                }
            }
        }

        $this->y -= 6;

        return $this;
    }

    protected function drawTableHeader(array $headers, array $xs, array $widths, array $aligns, float $size, float $lineHeight, float $pad): void
    {
        $this->reserve($lineHeight + 8);
        $this->y -= $lineHeight;

        foreach (array_values($headers) as $i => $header) {
            $this->text((string) $header, $size, true, $xs[$i] + $pad, $aligns[$i], $widths[$i] - (2 * $pad));
        }

        $this->y -= 3;
        $this->rule(0.5);
        $this->y -= 3;
    }


    protected function textOperator(string $value, float $size, bool $bold, float $x, float $y): string
    {
        return sprintf(
            'BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET',
            $bold ? self::FONT_BOLD : self::FONT_REGULAR,
            $size,
            $x,
            $y,
            $this->escape($this->toWinAnsi($value))
        );
    }

    public function output(): string
    {
        $total = count($this->pages);
        $footerTop = self::MARGIN_BOTTOM - 24;

        foreach (array_keys($this->pages) as $index) {
            $pageNumber = 'Page ' . ($index + 1) . ' of ' . $total;
            $rightWidth = $this->measure($pageNumber, false, 8.0);

            $this->pages[$index][] = sprintf(
                '0.4 w %.2F %.2F m %.2F %.2F l S',
                self::MARGIN_LEFT,
                $footerTop + 12,
                self::PAGE_WIDTH - self::MARGIN_RIGHT,
                $footerTop + 12
            );

            if ($this->footerText !== '') {
                $this->pages[$index][] = $this->textOperator($this->footerText, 8.0, false, self::MARGIN_LEFT, $footerTop);
            }

            $this->pages[$index][] = $this->textOperator(
                $pageNumber,
                8.0,
                false,
                self::PAGE_WIDTH - self::MARGIN_RIGHT - $rightWidth,
                $footerTop
            );
        }

        return $this->assemble();
    }

    protected function assemble(): string
    {
        $objects = [];
        $pageCount = count($this->pages);
        $firstPageObject = 6;
        $nextObject = $firstPageObject + ($pageCount * 2);
        $imageResources = [];
        $imageObjects = [];

        $kids = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[5] = $this->infoDictionary();

        foreach ($this->images as $name => $image) {
            $softMaskReference = '';
            if ($image['alpha'] !== null) {
                $softMaskObject = $nextObject++;
                $objects[$softMaskObject] = sprintf(
                    '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray '
                    . '/BitsPerComponent 8 /Filter /FlateDecode /Length %d >>' . "\nstream\n%s\nendstream",
                    $image['width'],
                    $image['height'],
                    strlen($image['alpha']),
                    $image['alpha']
                );
                $softMaskReference = ' /SMask ' . $softMaskObject . ' 0 R';
            }

            $imageObject = $nextObject++;
            $imageResources[$name] = $imageObject . ' 0 R';
            $imageObjects[$imageObject] = sprintf(
                '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB '
                . '/BitsPerComponent 8 /Filter /FlateDecode /Length %d%s >>' . "\nstream\n%s\nendstream",
                $image['width'],
                $image['height'],
                strlen($image['rgb']),
                $softMaskReference,
                $image['rgb']
            );
        }

        foreach ($this->pages as $index => $streamLines) {
            $pageObject = $firstPageObject + ($index * 2);
            $contentObject = $pageObject + 1;

            $kids[] = $pageObject . ' 0 R';

            $xObjectResource = $imageResources === []
                ? ''
                : ' /XObject << ' . implode(' ', array_map(
                    static fn ($name, $reference) => '/' . $name . ' ' . $reference,
                    array_keys($imageResources),
                    array_values($imageResources)
                )) . ' >>';

            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >>%s >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $xObjectResource,
                $contentObject
            );

            $stream = implode("\n", $streamLines);

            $objects[$contentObject] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }

        foreach ($imageObjects as $imageObject => $imageBody) {
            $objects[$imageObject] = $imageBody;
        }

        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), $pageCount);

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $objectCount = count($objects);
        $xrefOffset = strlen($pdf);

        $pdf .= "xref\n0 " . ($objectCount + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $objectCount; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . ($objectCount + 1) . " /Root 1 0 R /Info 5 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    protected function infoDictionary(): string
    {
        $parts = [];

        foreach ($this->meta as $key => $value) {
            $parts[] = '/' . $key . ' (' . $this->escape($this->toWinAnsi($value)) . ')';
        }

        return '<< ' . implode(' ', $parts) . ' >>';
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }
}