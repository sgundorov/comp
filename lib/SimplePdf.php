<?php
/**
 * SimplePdf - минимальный PDF-генератор с поддержкой кириллицы.
 * Использует встроенный TTF (Arial) с Identity-H кодировкой.
 */
class SimplePdf {
    const TTF_PATH = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';

    private $pages = [];
    private $curPage = '';
    private $cursorY;
    private $margin = 36;
    private $pageW = 595;
    private $pageH = 842;
    private $tableX = 0;
    private $tableW = 0;
    private $colWidths = [];
    private $headers = [];
    private $rowH = 16;
    private $fontH = 9;
    private $fontHd = 10;
    private $headerH = 20;

    private static $ttfBytes = null;
    private static $ttfLen = 0;
    private static $cmap = [];        // unicode -> glyph id
    private static $widths = [];      // glyph id -> advance width (TTF units)
    private static $unitsPerEm = 1000;
    private static $ascender = 800;
    private static $descender = -200;
    private static $loaded = false;

    public function __construct() {
        $this->cursorY = $this->margin;
        $this->loadTtf();
    }

    public function addTitle($text, $size = 16) {
        $this->ensureSpace($size + 10);
        $textW = $this->textWidth($text, $size);
        $x = ($this->pageW - $textW) / 2;
        $this->curPage .= $this->textOp($x, $this->cursorY + $size * 0.85, $text, $size);
        $this->cursorY += $size + 8;
    }

    public function addSubtitle($text, $size = 10) {
        $this->ensureSpace($size + 6);
        $textW = $this->textWidth($text, $size);
        $x = ($this->pageW - $textW) / 2;
        $this->curPage .= $this->textOp($x, $this->cursorY + $size * 0.85, $text, $size);
        $this->cursorY += $size + 4;
    }

    public function addText($text, $size = 10) {
        $this->wrapText($text, $size, $this->pageW - 2 * $this->margin);
    }

    public function addTable(array $colWidths, array $headers, array $rows) {
        $tableX = $this->margin;
        $tableW = array_sum($colWidths);
        $this->tableX = $tableX;
        $this->tableW = $tableW;
        $this->colWidths = $colWidths;
        $this->headers = $headers;
        $this->rowH = 16;
        $this->fontH = 9;
        $this->fontHd = 10;
        $this->headerH = 20;

        $this->drawTableHeader();
        $this->cursorY += $this->headerH;

        foreach ($rows as $ri => $row) {
            if ($this->cursorY + $this->rowH + 6 > $this->pageH - $this->margin) {
                $this->flushPage();
                $this->drawTableHeader();
                $this->cursorY += $this->headerH;
            }

            if ($ri % 2 === 1) {
                $this->curPage .= sprintf("q 0.96 0.96 0.98 rg %s %s %s %s re f Q\n",
                    $this->fmt($this->tableX), $this->fmt($this->pageH - $this->cursorY - $this->rowH),
                    $this->fmt($this->tableW), $this->fmt($this->rowH));
            }

            $cx = $this->tableX;
            foreach ($row as $i => $cell) {
                $text = (string)$cell;
                $avail = $this->colWidths[$i] - 8;
                $maxChars = max(1, (int)($avail / ($this->fontH * 0.5)));
                if (mb_strlen($text, 'UTF-8') > $maxChars) {
                    $text = mb_substr($text, 0, $maxChars - 1, 'UTF-8') . '...';
                }
                $this->curPage .= $this->textOp($cx + 4, $this->cursorY + 11, $text, $this->fontH);
                $cx += $this->colWidths[$i];
            }

            $this->curPage .= $this->lineOp($this->tableX, $this->pageH - $this->cursorY, $this->tableX + $this->tableW, $this->pageH - $this->cursorY);
            $this->cursorY += $this->rowH;
        }
        $this->curPage .= $this->lineOp($this->tableX, $this->pageH - $this->cursorY, $this->tableX + $this->tableW, $this->pageH - $this->cursorY);
        $this->cursorY += 10;
    }

    private function drawTableHeader() {
        $yTop = $this->cursorY + $this->headerH;
        $this->curPage .= sprintf("q 0.85 0.85 0.88 rg %s %s %s %s re f Q\n",
            $this->fmt($this->tableX), $this->fmt($this->pageH - $yTop),
            $this->fmt($this->tableW), $this->fmt($this->headerH));

        $cx = $this->tableX;
        foreach ($this->headers as $i => $h) {
            $this->curPage .= $this->textOp($cx + 4, $this->cursorY + 13, $h, $this->fontHd);
            $cx += $this->colWidths[$i];
        }

        $this->curPage .= $this->lineOp($this->tableX, $this->pageH - $yTop, $this->tableX + $this->tableW, $this->pageH - $yTop);
        $this->curPage .= $this->lineOp($this->tableX, $this->pageH - $this->cursorY, $this->tableX + $this->tableW, $this->pageH - $this->cursorY);
    }

    public function output($filename = 'document.pdf') {
        $this->flushPage();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $pageCount = count($this->pages);
        $pageObjN    = range(3, 3 + ($pageCount - 1) * 2, 2);
        $contentObjN = range(4, 4 + ($pageCount - 1) * 2, 2);
        $fontObjN    = 3 + $pageCount * 2;
        $cidObjN     = $fontObjN + 1;
        $descObjN    = $fontObjN + 2;
        $ttfObjN     = $fontObjN + 3;
        $touniObjN   = $fontObjN + 4;
        $totalObjs   = $touniObjN;

        $objs = [];
        $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $kids = [];
        foreach ($pageObjN as $n) $kids[] = "$n 0 R";
        $objs[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $pageCount >>";
        foreach ($pageObjN as $i => $pn) {
            $cn = $contentObjN[$i];
            $objs[$pn] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $this->pageW $this->pageH] /Contents $cn 0 R /Resources << /Font << /F1 $fontObjN 0 R >> >> >>";
            $objs['s' . $cn] = $this->pages[$i];
        }
        $objs[$fontObjN]  = "<< /Type /Font /Subtype /Type0 /BaseFont /ArialMT /Encoding /Identity-H /DescendantFonts [$cidObjN 0 R] /ToUnicode $touniObjN 0 R >>";
        $wArray = $this->buildWArray();
        $objs[$cidObjN]   = "<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ArialMT /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor $descObjN 0 R /DW 500 /W $wArray /CIDToGIDMap /Identity >>";
        $objs[$descObjN]  = "<< /Type /FontDescriptor /FontName /ArialMT /Flags 32 /FontBBox [0 -200 1000 800] /ItalicAngle 0 /Ascent " . self::$ascender . " /Descent " . self::$descender . " /CapHeight 700 /StemV 80 /FontFile2 $ttfObjN 0 R >>";
        $objs['s' . $ttfObjN] = self::$ttfBytes;
        $objs['s' . $touniObjN] = $this->buildToUnicodeCMap();

        $pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [0 => 0];

        ksort($objs, SORT_NATURAL);
        $orderedKeys = [];
        for ($i = 1; $i <= $totalObjs; $i++) {
            if (isset($objs[$i])) $orderedKeys[] = $i;
        }

        foreach ($orderedKeys as $num) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "$num 0 obj\n" . $objs[$num] . "\nendobj\n";
        }

        $streamNums = [$touniObjN, $ttfObjN];
        foreach ($pageObjN as $i => $pn) {
            $streamNums[] = $contentObjN[$i];
        }
        foreach ($streamNums as $sn) {
            $offsets['s' . $sn] = strlen($pdf);
            $length = strlen($objs['s' . $sn]);
            $pdf .= "$sn 0 obj\n<< /Length $length >>\nstream\n" . $objs['s' . $sn] . "\nendstream\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $xref = "xref\n0 " . ($totalObjs + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjs; $i++) {
            $off = isset($offsets[$i]) ? $offsets[$i] : 0;
            $xref .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= $xref;
        $pdf .= "trailer\n<< /Size " . ($totalObjs + 1) . " /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";

        echo $pdf;
    }

    private function wrapText($text, $size, $maxWidth) {
        $words = preg_split('/\s+/', $text);
        $line = '';
        foreach ($words as $w) {
            $test = $line === '' ? $w : $line . ' ' . $w;
            if ($this->textWidth($test, $size) > $maxWidth) {
                if ($line !== '') {
                    $this->ensureSpace($size + 2);
                    $this->curPage .= $this->textOp($this->margin, $this->cursorY + $size * 0.85, $line, $size);
                    $this->cursorY += $size + 2;
                }
                $line = $w;
            } else {
                $line = $test;
            }
        }
        if ($line !== '') {
            $this->ensureSpace($size + 2);
            $this->curPage .= $this->textOp($this->margin, $this->cursorY + $size * 0.85, $line, $size);
            $this->cursorY += $size + 2;
        }
    }

    private function textOp($x, $y, $text, $size) {
        $bytes = $this->toGlyphCodes($text);
        $hex = bin2hex($bytes);
        return sprintf("BT /F1 %s Tf 1 0 0 1 %s %s Tm <%s> Tj ET\n",
            $this->fmt($size), $this->fmt($x), $this->fmt($this->pageH - $y), $hex);
    }

    private function lineOp($x1, $y1, $x2, $y2) {
        return sprintf("%s %s m %s %s l S\n",
            $this->fmt($x1), $this->fmt($y1), $this->fmt($x2), $this->fmt($y2));
    }

    private function toGlyphCodes($text) {
        $out = '';
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $c = mb_substr($text, $i, 1, 'UTF-8');
            $code = mb_ord($c, 'UTF-8');
            $gid = self::$cmap[$code] ?? 0;
            $out .= chr(($gid >> 8) & 0xFF) . chr($gid & 0xFF);
        }
        return $out;
    }

    private function textWidth($text, $size) {
        $w = 0;
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $c = mb_ord(mb_substr($text, $i, 1, 'UTF-8'), 'UTF-8');
            $gid = self::$cmap[$c] ?? 0;
            $adv = self::$widths[$gid] ?? 500;
            $w += $adv;
        }
        return $w * $size / self::$unitsPerEm;
    }

    private function ensureSpace($h) {
        if ($this->cursorY + $h > $this->pageH - $this->margin) {
            $this->flushPage();
        }
    }

    private function flushPage() {
        if ($this->curPage !== '') {
            $this->pages[] = $this->curPage;
            $this->curPage = '';
        }
        $this->cursorY = $this->margin;
    }

    private function fmt($n) {
        return number_format((float)$n, 2, '.', '');
    }

    private function buildWArray() {
        $parts = [];
        $sortedKeys = array_keys(self::$widths);
        sort($sortedKeys);
        $start = null;
        $prevGid = null;
        $prevW = null;
        foreach ($sortedKeys as $gid) {
            $w = self::$widths[$gid];
            if ($start === null) {
                $start = $gid;
                $prevGid = $gid;
                $prevW = $w;
                continue;
            }
            if ($gid === $prevGid + 1 && $w === $prevW) {
                $prevGid = $gid;
                continue;
            }
            if ($start === $prevGid) {
                $parts[] = "$start $prevW";
            } else {
                $parts[] = "$start " . ($prevGid - $start + 1) . " $prevW";
            }
            $start = $gid;
            $prevGid = $gid;
            $prevW = $w;
        }
        if ($start !== null) {
            if ($start === $prevGid) {
                $parts[] = "$start $prevW";
            } else {
                $parts[] = "$start " . ($prevGid - $start + 1) . " $prevW";
            }
        }
        return '[' . implode(' ', $parts) . ']';
    }

    private function buildToUnicodeCMap() {
        $cmap = "/CIDInit /ProcSet findresource begin\n";
        $cmap .= "12 dict begin\n";
        $cmap .= "begincmap\n";
        $cmap .= "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n";
        $cmap .= "/CMapName /Adobe-Identity-UCS def\n";
        $cmap .= "/CMapType 2 def\n";
        $cmap .= "1 begincodespacerange\n";
        $cmap .= "<0000> <FFFF>\n";
        $cmap .= "endcodespacerange\n";

        $entries = [];
        foreach (self::$cmap as $unicodeCode => $gid) {
            $entries[] = sprintf("<%04X> <%04X>", $gid, $unicodeCode);
        }
        $i = 0;
        while ($i < count($entries)) {
            $chunk = array_slice($entries, $i, 100);
            $cmap .= count($chunk) . " beginbfchar\n";
            foreach ($chunk as $e) $cmap .= $e . "\n";
            $cmap .= "endbfchar\n";
            $i += 100;
        }
        $cmap .= "endcmap\n";
        $cmap .= "CMapName currentdict /CMap defineresource pop\n";
        $cmap .= "end\n";
        $cmap .= "end\n";
        return $cmap;
    }

    private function loadTtf() {
        if (self::$loaded) return;
        self::$ttfBytes = file_get_contents(self::TTF_PATH);
        self::$ttfLen = strlen(self::$ttfBytes);

        $numTables = self::u16(4);
        $tables = [];
        for ($i = 0; $i < $numTables; $i++) {
            $pos = 12 + $i * 16;
            $tag = substr(self::$ttfBytes, $pos, 4);
            $tables[$tag] = self::u32($pos + 8);
        }

        if (isset($tables['head'])) {
            self::$unitsPerEm = self::u16($tables['head'] + 18);
        }
        if (isset($tables['hhea'])) {
            self::$ascender = self::s16($tables['hhea'] + 4);
            self::$descender = self::s16($tables['hhea'] + 6);
        }
        if (isset($tables['cmap'])) {
            self::$cmap = self::parseCmap($tables['cmap']);
        }
        if (isset($tables['hhea']) && isset($tables['hmtx'])) {
            $numH = self::u16($tables['hhea'] + 34);
            $off = $tables['hmtx'];
            for ($i = 0; $i < $numH; $i++) {
                self::$widths[$i] = self::u16($off + $i * 4);
            }
        }
        self::$loaded = true;
    }

    private function parseCmap($cmapOff) {
        $numSub = self::u16($cmapOff + 2);
        $best = null;
        for ($i = 0; $i < $numSub; $i++) {
            $p = $cmapOff + 4 + $i * 8;
            $plat = self::u16($p);
            $enc = self::u16($p + 2);
            $sub = self::u32($p + 4);
            if (($plat == 3 && $enc == 1) || ($plat == 0 && ($enc == 3 || $enc == 4))) {
                $best = $cmapOff + $sub;
                if ($plat == 3) break;
            }
        }
        if ($best === null) return [];

        $format = self::u16($best);
        $result = [];

        if ($format == 4) {
            $segCountX2 = self::u16($best + 6);
            $segCount = (int)($segCountX2 / 2);
            $p = $best + 14;

            $endCodes = [];
            for ($i = 0; $i < $segCount; $i++) $endCodes[] = self::u16($p + $i * 2);
            $p += $segCount * 2 + 2;

            $startCodes = [];
            for ($i = 0; $i < $segCount; $i++) $startCodes[] = self::u16($p + $i * 2);
            $p += $segCount * 2;

            $idDeltas = [];
            for ($i = 0; $i < $segCount; $i++) {
                $v = self::u16($p + $i * 2);
                $idDeltas[] = $v >= 0x8000 ? $v - 0x10000 : $v;
            }
            $p += $segCount * 2;

            $idRangeOffsets = [];
            for ($i = 0; $i < $segCount; $i++) $idRangeOffsets[] = self::u16($p + $i * 2);
            $p += $segCount * 2;

            for ($i = 0; $i < $segCount; $i++) {
                $s = $startCodes[$i];
                $e = $endCodes[$i];
                $d = $idDeltas[$i];
                $r = $idRangeOffsets[$i];
                for ($c = $s; $c <= $e; $c++) {
                    if ($r == 0) {
                        $gid = ($c + $d) & 0xFFFF;
                    } else {
                        $gidOff = $p + ($c - $s) * 2 + $r - $segCount * 2;
                        $gid = self::u16($gidOff);
                        if ($gid != 0) $gid = ($gid + $d) & 0xFFFF;
                    }
                    $result[$c] = $gid;
                }
            }
        } elseif ($format == 12) {
            $numGroups = self::u32($best + 12);
            $p = $best + 16;
            for ($i = 0; $i < $numGroups; $i++) {
                $sc = self::u32($p);
                $ec = self::u32($p + 4);
                $sg = self::u32($p + 8);
                $p += 12;
                for ($c = $sc; $c <= $ec; $c++) {
                    $result[$c] = $sg + ($c - $sc);
                }
            }
        }
        return $result;
    }

    private static function u16($pos) {
        $b = unpack('n', substr(self::$ttfBytes, $pos, 2));
        return $b[1];
    }

    private static function s16($pos) {
        $b = unpack('n', substr(self::$ttfBytes, $pos, 2));
        $v = $b[1];
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    private static function u32($pos) {
        $b = unpack('N', substr(self::$ttfBytes, $pos, 4));
        return $b[1];
    }
}
