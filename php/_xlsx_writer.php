<?php

declare(strict_types=1);

/**
 * Minimal XLSX (Office Open XML) writer using ZipArchive.
 *
 * Goals:
 * - No external dependencies.
 * - Enough features for schedule exports: basic styling, column widths, row heights, merges, multi-sheet.
 * - Uses inline strings to avoid sharedStrings complexity.
 */
final class SimpleXlsxWriter
{
    /** @var array<int, array{name:string, rows:array<int, array<int, scalar|null>>, colWidths:array<int, float>, rowHeights:array<int, float>, merges:array<int, string>, styleMap:array<int, array<int, int>>}> */
    private array $sheets = [];

    /** @var array<string, int> */
    private array $fillStyleCache = [];

    /** @var array<string, int> */
    private array $lectureFillStyleCache = [];

    /**
     * Style IDs (0-based) generated in buildStylesXml().
     *
     * @var array{default:int, header:int, slot:int, cell:int}
     */
    // NOTE: these IDs must match the order of <cellXfs> produced in buildStylesXml().
    // Base styles (kept stable):
    // 0 default, 1 header, 2 slot/time, 3 normal cell, 4 title, 5 zebra stripe.
    private array $baseStyleIds = ['default' => 0, 'header' => 1, 'slot' => 2, 'cell' => 3, 'title' => 4, 'stripe' => 5];

    // Compact styles (added after base styles, before dynamic fill styles):
    // 6 headerSmall, 7 cellSmall, 8 cellSmallBold, 9 cellSmallBoldLeft
    private array $compactStyleIds = ['headerSmall' => 6, 'cellSmall' => 7, 'cellSmallBold' => 8, 'cellSmallBoldLeft' => 9];

    /** @var array<string, int> */
    private array $fillBoldStyleCache = [];

    /**
     * Colors used for filled cells.
     * @var array<string, true>
     */
    private array $fillColors = [];

    public function addSheet(
        string $name,
        array $rows,
        array $options = []
    ): int {
        $colWidths = $options['colWidths'] ?? [];
        $rowHeights = $options['rowHeights'] ?? [];
        $merges = $options['merges'] ?? [];
        $styleMap = $options['styleMap'] ?? [];
        // Optional table range used for outer-border styling.
        // Format: ['top'=>int,'left'=>int,'bottom'=>int,'right'=>int] using 0-based row/col indexes.
        $table = $options['table'] ?? null;

        // Excel sheet name rules: <=31 chars, cannot contain : \ / ? * [ ]
        $name = preg_replace('/[:\\\\\/\?\*\[\]]+/', ' ', $name) ?? $name;
        $name = trim($name);
        if ($name === '') $name = 'Sheet' . (count($this->sheets) + 1);
        $name = mb_substr($name, 0, 31);

        $this->sheets[] = [
            'name' => $name,
            'rows' => $rows,
            'colWidths' => $colWidths,
            'rowHeights' => $rowHeights,
            'merges' => $merges,
            'styleMap' => $styleMap,
            'table' => $table,
        ];

        return count($this->sheets) - 1;
    }

    public function styleHeader(): int
    {
        return $this->baseStyleIds['header'];
    }

    public function styleHeaderSmall(): int
    {
        return $this->compactStyleIds['headerSmall'];
    }

    public function styleSlot(): int
    {
        return $this->baseStyleIds['slot'];
    }

    public function styleCell(): int
    {
        return $this->baseStyleIds['cell'];
    }

    public function styleCellSmall(): int
    {
        return $this->compactStyleIds['cellSmall'];
    }

    public function styleCellSmallBold(): int
    {
        return $this->compactStyleIds['cellSmallBold'];
    }

    public function styleCellSmallBoldLeft(): int
    {
        return $this->compactStyleIds['cellSmallBoldLeft'];
    }

    /**
     * Title style for the first merged row (bigger + bold).
     */
    public function styleTitle(): int
    {
        return $this->baseStyleIds['title'];
    }

    /**
     * Light zebra stripe fill for empty schedule cells.
     */
    public function styleStripe(): int
    {
        return $this->baseStyleIds['stripe'];
    }

    /**
     * Register a background fill color (RRGGBB) and return a style ID.
     */
    public function styleFill(string $hexRgb): int
    {
        $hexRgb = strtoupper(ltrim($hexRgb, '#'));
        if (!preg_match('/^[0-9A-F]{6}$/', $hexRgb)) {
            $hexRgb = 'DDDDDD';
        }

        // Normalize to 8-digit ARGB required by OOXML.
        $argb = 'FF' . $hexRgb;

        if (isset($this->fillStyleCache[$argb])) {
            return $this->fillStyleCache[$argb];
        }

        $this->fillColors[$argb] = true;

        // Style IDs are finalized later; reserve by using a negative placeholder.
        // We'll remap placeholders to real IDs in buildStylesXml().
        $placeholder = -count($this->fillStyleCache) - 1;
        $this->fillStyleCache[$argb] = $placeholder;
        return $placeholder;
    }

    /**
     * Like styleFill(), but with bold 16pt text for scheduled lecture cells.
     */
    public function styleFillBold(string $hexRgb): int
    {
        $hexRgb = strtoupper(ltrim($hexRgb, '#'));
        if (!preg_match('/^[0-9A-F]{6}$/', $hexRgb)) {
            $hexRgb = 'DDDDDD';
        }

        $argb = 'FF' . $hexRgb;

        if (isset($this->fillBoldStyleCache[$argb])) {
            return $this->fillBoldStyleCache[$argb];
        }

        $this->fillColors[$argb] = true;

        // Use a distinct placeholder space for bold fill styles.
        $placeholder = -20000 - count($this->fillBoldStyleCache) - 1;
        $this->fillBoldStyleCache[$argb] = $placeholder;
        return $placeholder;
    }

    public function styleLectureFill(string $hexRgb): int
    {
        $hexRgb = strtoupper(ltrim($hexRgb, '#'));
        if (!preg_match('/^[0-9A-F]{6}$/', $hexRgb)) {
            $hexRgb = 'DDDDDD';
        }
        $argb = 'FF' . $hexRgb;

        if (isset($this->lectureFillStyleCache[$argb])) {
            return $this->lectureFillStyleCache[$argb];
        }

        $this->fillColors[$argb] = true;

        // Use a distinct placeholder space for lecture-fill styles.
        $placeholder = -10000 - count($this->lectureFillStyleCache) - 1;
        $this->lectureFillStyleCache[$argb] = $placeholder;
        return $placeholder;
    }

    /**
     * Output the XLSX file to the browser.
     */
    public function download(string $fileName): void
    {
        $fileName = preg_replace('/[^a-zA-Z0-9\-_(). \[\]]+/', '', $fileName) ?? $fileName;
        if (!str_ends_with(strtolower($fileName), '.xlsx')) {
            $fileName .= '.xlsx';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmp === false) {
            http_response_code(500);
            header('Content-Type: text/plain');
            echo 'Failed to create temp file.';
            return;
        }

        try {
            $this->buildZip($tmp);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . (string)filesize($tmp));

            readfile($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function downloadToString(string $fileName): string
    {
        $fileName = preg_replace('/[^a-zA-Z0-9\-_(). \[\]]+/', '', $fileName) ?? $fileName;
        if (!str_ends_with(strtolower($fileName), '.xlsx')) {
            $fileName .= '.xlsx';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmp === false) {
            throw new RuntimeException('Failed to create temp file.');
        }

        try {
            $this->buildZip($tmp);
            $bytes = file_get_contents($tmp);
            if ($bytes === false) {
                throw new RuntimeException('Failed to read XLSX data.');
            }
            return $bytes;
        } finally {
            @unlink($tmp);
        }
    }

    private function buildZip(string $zipPath): void
    {
        // Styles must be built before sheet XML so we can resolve fill placeholders.
        [$stylesXml, $styleMaps] = $this->buildStylesXml();

        $files = [];

        // Root relationships.
        $files['_rels/.rels'] = $this->rootRelsXml();

        // Workbook
        $files['xl/workbook.xml'] = $this->workbookXml();
        $files['xl/_rels/workbook.xml.rels'] = $this->workbookRelsXml();

        // Styles
        $files['xl/styles.xml'] = $stylesXml;

        // Worksheets
        foreach ($this->sheets as $i => $sheet) {
            $files['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $this->sheetXml($sheet, $styleMaps);
        }

        // [Content_Types]
        $files['[Content_Types].xml'] = $this->contentTypesXml();

        $zipBytes = SimpleZipBuilder::build($files);

        $ok = file_put_contents($zipPath, $zipBytes);
        if ($ok === false) {
            throw new RuntimeException('Failed to write XLSX file.');
        }
    }

    private function contentTypesXml(): string
    {
        $overrides = "";
        foreach ($this->sheets as $i => $_) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        $sheetsXml = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheetId = $i + 1;
            $nameEsc = htmlspecialchars($sheet['name'], ENT_QUOTES | ENT_XML1);
            $sheetsXml .= '<sheet name="' . $nameEsc . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetsXml . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        $rels = '';
        foreach ($this->sheets as $i => $_) {
            $sheetId = $i + 1;
            $rels .= '<Relationship Id="rId' . $sheetId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetId . '.xml"/>';
        }

        // Reserve rId for styles after sheets.
        $styleRid = count($this->sheets) + 1;
        $rels .= '<Relationship Id="rId' . $styleRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    /**
     * Build styles.xml and resolve any placeholder fill style IDs.
     *
     * Style maps include:
     * - fill: normal filled cells
     * - lecture: filled cells with thick border + bold
     * - fillBold: filled cells with bold text
     *
     * @return array{0:string,1:array{fill:array<string,int>,lecture:array<string,int>,fillBold:array<string,int>}} [stylesXml, styleMaps]
     */
    private function buildStylesXml(): array
    {
        // Base styles: 0 default, 1 header, 2 slot (header-ish), 3 normal cell.
        // Add a style per fill color.

        $fillColors = array_keys($this->fillColors);
        sort($fillColors);

        // Fills: 0 none, 1 gray125 (required by Excel), then custom fills.
        $fillsXml = '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>';
        foreach ($fillColors as $argb) {
            $fillsXml .= '<fill><patternFill patternType="solid"><fgColor rgb="' . $argb . '"/><bgColor indexed="64"/></patternFill></fill>';
        }

        // Fonts
        $fontsXml = '';
        // 0: default
        $fontsXml .= '<font><sz val="12"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>';
        // 1: header row (days) bold white, bigger
        $fontsXml .= '<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>';
        // 2: slot/time column bold white, slightly bigger
        $fontsXml .= '<font><b/><sz val="14"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>';
        // 3: lecture cell (bold, 16pt)
        $fontsXml .= '<font><b/><sz val="16"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>';
        // 4: title row (bigger, bold, white)
        $fontsXml .= '<font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>';
        // 5: compact default
        $fontsXml .= '<font><sz val="10"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>';
        // 6: compact header (bold white, smaller)
        $fontsXml .= '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>';
        // 7: compact default (bold)
        $fontsXml .= '<font><b/><sz val="10"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>';
        // 8: default (bold)
        $fontsXml .= '<font><b/><sz val="12"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>';

        // IMPORTANT: font IDs used by styles below:
        // 0 default, 1 header, 2 slot, 3 lecture, 4 title, 5 compact default, 6 compact header, 7 compact bold, 8 default bold

        // Borders
        // Excel expects borderId=0 to be the "no border" default.
        // We use a single consistent border thickness across the table.
        // borderId=0: none (default)
        // borderId=1: thin (applied to all cells)
        $borderNone = '<border><left/><right/><top/><bottom/><diagonal/></border>';
        $borderThin = '<border>'
            . '<left style="thin"><color rgb="FFCBD5E1"/></left>'
            . '<right style="thin"><color rgb="FFCBD5E1"/></right>'
            . '<top style="thin"><color rgb="FFCBD5E1"/></top>'
            . '<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>'
            . '<diagonal/>'
            . '</border>';

        // Thick-ish black border used for lecture cells to make separators clearly visible.
        // (Excel "medium" is a good visible thickness without being too heavy.)
        $borderLecture = '<border>'
            . '<left style="medium"><color rgb="FF000000"/></left>'
            . '<right style="medium"><color rgb="FF000000"/></right>'
            . '<top style="medium"><color rgb="FF000000"/></top>'
            . '<bottom style="medium"><color rgb="FF000000"/></bottom>'
            . '<diagonal/>'
            . '</border>';

        // borderId=0 none, borderId=1 thin grid, borderId=2 lecture border
        $bordersXml = $borderNone . $borderThin . $borderLecture;

        // CellXfs
        // Helper: center + wrap.
        $centerWrap = '<alignment horizontal="center" vertical="center" wrapText="1"/>';
        $leftWrap = '<alignment horizontal="left" vertical="center" wrapText="1"/>';

        // 0 default (thin grid)
        $cellXfs = '<xf xfId="0" fontId="0" fillId="0" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
            . $centerWrap . '</xf>';

        // Pre-create base thin cellXfs for styles 1..5 after this. We'll also create thick variants later.


        // 1 header (dark fill)
        // Use a custom fill at index 2? We'll bake header fill as its own custom fill by reusing an explicit fill (not in dynamic list).
        // To keep things simple, we'll include header fill as an extra custom fill at the beginning.

        // Fixed fills used by the schedule tables.
        $titleFillArgb = 'FF0F172A'; // slate-900
        $headerFillArgb = 'FF1E40AF'; // blue-800
        $slotFillArgb = 'FF334155'; // slate-700
        $stripeFillArgb = 'FFF1F5F9'; // slate-100

        // Build fills in stable order: none, gray125, title, header, slot, stripe, then dynamic fills.
        $fillsXml = '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="' . $titleFillArgb . '"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="' . $headerFillArgb . '"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="' . $slotFillArgb . '"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="' . $stripeFillArgb . '"/><bgColor indexed="64"/></patternFill></fill>';
        foreach ($fillColors as $argb) {
            $fillsXml .= '<fill><patternFill patternType="solid"><fgColor rgb="' . $argb . '"/><bgColor indexed="64"/></patternFill></fill>';
        }

        // Base (thin-grid) styles.
        // BorderId=1 (thin)
        $baseThin = [];

        // 1 day header row
        $cellXfs .= '<xf xfId="0" fontId="1" fillId="3" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
            . $centerWrap . '</xf>';
        $baseThin[1] = 1;

        // 2 slot/time column
        $cellXfs .= '<xf xfId="0" fontId="2" fillId="4" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
            . $centerWrap . '</xf>';
        $baseThin[2] = 2;

        // 3 normal cell
        $cellXfs .= '<xf xfId="0" fontId="0" fillId="0" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
            . $centerWrap . '</xf>';
        $baseThin[3] = 3;

        // 4 title row
        $cellXfs .= '<xf xfId="0" fontId="4" fillId="2" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
            . $centerWrap . '</xf>';
        $baseThin[4] = 4;

        // 5 zebra stripe cell
        $cellXfs .= '<xf xfId="0" fontId="0" fillId="5" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
            . $centerWrap . '</xf>';
        $baseThin[5] = 5;

        // Compact styles (6..9)
        // 6 small header
        $cellXfs .= '<xf xfId="0" fontId="6" fillId="3" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
            . $centerWrap . '</xf>';

        // 7 small normal cell
        $cellXfs .= '<xf xfId="0" fontId="5" fillId="0" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
            . $centerWrap . '</xf>';

        // 8 small normal cell (bold)
        $cellXfs .= '<xf xfId="0" fontId="7" fillId="0" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
            . $centerWrap . '</xf>';

        // 9 small normal cell (bold, left-aligned)
        $cellXfs .= '<xf xfId="0" fontId="7" fillId="0" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
            . $leftWrap . '</xf>';

        // Dynamic fills: for each color, generate:
        // - normal filled
        // - lecture filled
        // - bold filled
        $fillStyleIdMap = [];
        $lectureFillStyleIdMap = [];
        $fillBoldStyleIdMap = [];

        $nextStyleId = 10; // base styles 0..5 + compact styles 6..9
        foreach ($fillColors as $index => $argb) {
            $fillId = 6 + $index;

            // Normal filled
            $cellXfs .= '<xf xfId="0" fontId="0" fillId="' . $fillId . '" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
                . $centerWrap . '</xf>';
            $fillStyleIdMap[$argb] = $nextStyleId;
            $nextStyleId++;

            // Lecture filled (use black thicker border so borders between lectures stand out)
            $cellXfs .= '<xf xfId="0" fontId="3" fillId="' . $fillId . '" borderId="2" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
                . $centerWrap . '</xf>';
            $lectureFillStyleIdMap[$argb] = $nextStyleId;
            $nextStyleId++;

            // Bold filled
            $cellXfs .= '<xf xfId="0" fontId="8" fillId="' . $fillId . '" borderId="1" numFmtId="0" applyAlignment="1" applyFont="1" applyFill="1" applyBorder="1">'
                . $centerWrap . '</xf>';
            $fillBoldStyleIdMap[$argb] = $nextStyleId;
            $nextStyleId++;
        }

        $styles = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="9">' . $fontsXml . '</fonts>'
            . '<fills count="' . (string)(2 + 4 + count($fillColors)) . '">' . $fillsXml . '</fills>'
            . '<borders count="3">' . $bordersXml . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . (string)(10 + (3 * count($fillColors))) . '">' . $cellXfs . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        return [$styles, [
            'fill' => $fillStyleIdMap,
            'lecture' => $lectureFillStyleIdMap,
            'fillBold' => $fillBoldStyleIdMap,
        ]];
    }

    /**
     * @param array{name:string, rows:array<int, array<int, scalar|null>>, colWidths:array<int, float>, rowHeights:array<int, float>, merges:array<int, string>, styleMap:array<int, array<int, int>>} $sheet
     * @param array{fill:array<string,int>,lecture:array<string,int>,fillBold:array<string,int>} $styleMaps
     */
    private function sheetXml(array $sheet, array $styleMaps): string
    {
        $colsXml = '';
        if (!empty($sheet['colWidths'])) {
            $colsXml .= '<cols>';
            foreach ($sheet['colWidths'] as $i => $w) {
                $col = $i + 1;
                $colsXml .= '<col min="' . $col . '" max="' . $col . '" width="' . (float)$w . '" customWidth="1"/>';
            }
            $colsXml .= '</cols>';
        }

        $rowsXml = '';
        foreach ($sheet['rows'] as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $heightAttr = '';
            if (isset($sheet['rowHeights'][$rIdx])) {
                $h = (float)$sheet['rowHeights'][$rIdx];
                $heightAttr = ' ht="' . $h . '" customHeight="1"';
            }

            $rowsXml .= '<row r="' . $rowNum . '"' . $heightAttr . '>';
            foreach ($row as $cIdx => $val) {
                $ref = $this->cellRef($cIdx, $rowNum);

                $styleId = $sheet['styleMap'][$rIdx][$cIdx] ?? $this->baseStyleIds['cell'];

                if ($styleId < 0) {
                    // Placeholder fill style (normal fill vs lecture fill vs bold fill).
                    if ($styleId <= -20000) {
                        $argb = array_search($styleId, $this->fillBoldStyleCache, true);
                        if (is_string($argb) && isset($styleMaps['fillBold'][$argb])) {
                            $styleId = $styleMaps['fillBold'][$argb];
                        } else {
                            $styleId = $this->baseStyleIds['cell'];
                        }
                    } elseif ($styleId <= -10000) {
                        $argb = array_search($styleId, $this->lectureFillStyleCache, true);
                        if (is_string($argb) && isset($styleMaps['lecture'][$argb])) {
                            $styleId = $styleMaps['lecture'][$argb];
                        } else {
                            $styleId = $this->baseStyleIds['cell'];
                        }
                    } else {
                        $argb = array_search($styleId, $this->fillStyleCache, true);
                        if (is_string($argb) && isset($styleMaps['fill'][$argb])) {
                            $styleId = $styleMaps['fill'][$argb];
                        } else {
                            $styleId = $this->baseStyleIds['cell'];
                        }
                    }
                }

                if ($val === null || $val === '') {
                    $rowsXml .= '<c r="' . $ref . '" s="' . (int)$styleId . '"/>';
                    continue;
                }

                if (is_int($val) || is_float($val)) {
                    $rowsXml .= '<c r="' . $ref . '" s="' . (int)$styleId . '"><v>' . $val . '</v></c>';
                    continue;
                }

                $text = (string)$val;
                // Preserve newlines.
                $textEsc = htmlspecialchars($text, ENT_QUOTES | ENT_XML1);
                $rowsXml .= '<c r="' . $ref . '" s="' . (int)$styleId . '" t="inlineStr"><is><t xml:space="preserve">' . $textEsc . '</t></is></c>';
            }
            $rowsXml .= '</row>';
        }

        $mergeXml = '';
        if (!empty($sheet['merges'])) {
            $mergeXml .= '<mergeCells count="' . count($sheet['merges']) . '">';
            foreach ($sheet['merges'] as $m) {
                $mergeXml .= '<mergeCell ref="' . htmlspecialchars($m, ENT_QUOTES | ENT_XML1) . '"/>';
            }
            $mergeXml .= '</mergeCells>';
        }

        // Cleaner look:
        // - Hide gridlines
        // - Freeze panes: always freeze first column; freeze top rows based on sheet content.
        //   If the sheet provides an option 'freezeTopRows', use it. Otherwise default to 2.
        $freezeTopRows = (int)($sheet['freezeTopRows'] ?? 2);
        if ($freezeTopRows < 0) $freezeTopRows = 0;
        $ySplit = $freezeTopRows;
        $topLeftRow = $freezeTopRows + 1;
        $topLeftCell = 'B' . $topLeftRow;

        $sheetViews = '<sheetViews><sheetView workbookViewId="0" showGridLines="0">'
            . '<pane xSplit="1" ySplit="' . $ySplit . '" topLeftCell="' . $topLeftCell . '" activePane="bottomRight" state="frozen"/>'
            . '<selection pane="bottomRight" activeCell="' . $topLeftCell . '" sqref="' . $topLeftCell . '"/>'
            . '</sheetView></sheetViews>';

        // Print/layout polish (landscape + fit to 1 page wide)
        $pageMargins = '<pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.3" footer="0.3"/>';
        $pageSetup = '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $sheetViews
            . $colsXml
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . $mergeXml
            . $pageMargins
            . $pageSetup
            . '</worksheet>';
    }

    private function cellRef(int $colIndex0, int $row1): string
    {
        $col = $colIndex0 + 1;
        $letters = '';
        while ($col > 0) {
            $mod = ($col - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $col = intdiv($col - 1, 26);
        }
        return $letters . $row1;
    }
}

final class SimpleZipBuilder
{
    /**
     * Build a ZIP archive.
     *
     * @param array<string,string> $files Map of file path => file contents.
     */
    public static function build(array $files): string
    {
        // Minimal ZIP (store only, no compression) implementation.
        // Enough for XLSX which is a ZIP container.

        // Sort by name for stability.
        ksort($files);

        $offset = 0;
        $local = '';
        $central = '';
        $count = 0;

        foreach ($files as $name => $data) {
            $name = str_replace('\\', '/', $name);
            $nameBytes = $name; // ASCII in our usage.
            $dataBytes = $data;

            $crc = crc32($dataBytes);
            if ($crc < 0) {
                $crc += 2 ** 32;
            }

            $size = strlen($dataBytes);
            $nameLen = strlen($nameBytes);

            // DOS time/date (set to 1980-01-01 00:00:00 for simplicity)
            $dosTime = 0;
            $dosDate = 0x21; // 1980-01-01

            // Local file header
            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                $nameLen,
                0
            );
            $local .= $localHeader . $nameBytes . $dataBytes;

            // Central directory header
            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                0,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                $nameLen,
                0,
                0,
                0,
                0,
                0,
                $offset
            );
            $central .= $centralHeader . $nameBytes;

            $offset += strlen($localHeader) + $nameLen + $size;
            $count++;
        }

        $centralSize = strlen($central);
        $centralOffset = strlen($local);

        // End of central directory
        $eocd = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            $centralSize,
            $centralOffset,
            0
        );

        return $local . $central . $eocd;
    }
}

final class XlsxColor
{
    /**
     * Mix a hex color with white to create a lighter pastel.
     */
    public static function pastelize(string $hexRgb, float $mixWithWhite = 0.70): string
    {
        $hexRgb = strtoupper(ltrim($hexRgb, '#'));
        if (!preg_match('/^[0-9A-F]{6}$/', $hexRgb)) {
            return 'DDDDDD';
        }
        $mixWithWhite = max(0.0, min(1.0, $mixWithWhite));

        $r = hexdec(substr($hexRgb, 0, 2));
        $g = hexdec(substr($hexRgb, 2, 2));
        $b = hexdec(substr($hexRgb, 4, 2));

        $r = (int)round($r * (1.0 - $mixWithWhite) + 255 * $mixWithWhite);
        $g = (int)round($g * (1.0 - $mixWithWhite) + 255 * $mixWithWhite);
        $b = (int)round($b * (1.0 - $mixWithWhite) + 255 * $mixWithWhite);

        return sprintf('%02X%02X%02X', $r, $g, $b);
    }
}
