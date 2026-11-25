<?php

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;
use ZipArchive;

class BroadbandXlsxExporter
{
    /**
     * Generate a native OOXML (.xlsx) file and return its temporary file path.
     *
     * @param iterable $data
     * @param bool     $canSeeCjp
     * @param float    $totalSumMaster
     * @param float    $totalSumCjp
     * @param string   $userAuth
     * @param array    $appliedFilters
     * @return string
     */
    public function generate(
        iterable $data,
        bool $canSeeCjp,
        float $totalSumMaster,
        float $totalSumCjp,
        string $userAuth,
        array $appliedFilters = []
    ): string {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive tidak aktif di server.');
        }

        $rows = [];
        foreach ($data as $item) {
            $rows[] = $item;
        }
        $rowCount = count($rows);

        $columns = $this->buildColumns($canSeeCjp);
        $totalCols = count($columns);
        $lastColLetter = $this->colLetter($totalCols);

        $tmpFile = tempnam(sys_get_temp_dir(), 'cjp_bb_xlsx_');
        if ($tmpFile === false) {
            throw new RuntimeException('Gagal membuat file sementara untuk export Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            @unlink($tmpFile);
            throw new RuntimeException('Gagal membuat arsip XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            $this->sheetXml($rows, $rowCount, $columns, $totalCols, $lastColLetter, $canSeeCjp, $totalSumMaster, $totalSumCjp, $userAuth, $appliedFilters)
        );

        $zip->close();

        return $tmpFile;
    }

    private function buildColumns(bool $canSeeCjp): array
    {
        $cols = [
            ['label' => 'NO', 'width' => 6],
            ['label' => 'NO. INTERNET', 'width' => 16],
            ['label' => 'NAMA PELANGGAN', 'width' => 28],
            ['label' => 'NIK', 'width' => 22],
            ['label' => 'NO. TELEPON / HP', 'width' => 18],
            ['label' => 'EMAIL', 'width' => 28],
            ['label' => 'BANDWIDTH', 'width' => 14],
            ['label' => 'KATEGORI BANDWIDTH', 'width' => 20],
            ['label' => 'HARGA BW MASTER', 'width' => 20],
        ];

        if ($canSeeCjp) {
            $cols[] = ['label' => 'HARGA CJP (25%)', 'width' => 20, 'is_cjp' => true];
        }

        return array_merge($cols, [
            ['label' => 'GROUP LAYANAN', 'width' => 16],
            ['label' => 'MEDIA AKSES', 'width' => 14],
            ['label' => 'POP', 'width' => 20],
            ['label' => 'JENIS BANGUNAN', 'width' => 18],
            ['label' => 'ALAMAT PEMASANGAN', 'width' => 44],
            ['label' => 'RT / RW', 'width' => 11],
            ['label' => 'NO. BANGUNAN', 'width' => 14],
            ['label' => 'KELURAHAN', 'width' => 20],
            ['label' => 'KECAMATAN', 'width' => 20],
            ['label' => 'KOTA / KABUPATEN', 'width' => 22],
            ['label' => 'ONT USER', 'width' => 18],
            ['label' => 'ONT PS', 'width' => 18],
            ['label' => 'OLT', 'width' => 16],
            ['label' => 'INDEX OLT', 'width' => 12],
            ['label' => 'SALES', 'width' => 22],
            ['label' => 'STATUS', 'width' => 12],
            ['label' => 'TGL REGISTRASI', 'width' => 20],
        ]);
    }

    private function sheetXml(
        array $rows,
        int $rowCount,
        array $columns,
        int $totalCols,
        string $lastColLetter,
        bool $canSeeCjp,
        float $totalSumMaster,
        float $totalSumCjp,
        string $userAuth,
        array $appliedFilters
    ): string {
        $popLabel = (!empty($appliedFilters['pop']) && $appliedFilters['pop'] !== 'ALL')
            ? $appliedFilters['pop']
            : 'Semua POP';
        $groupLabel = (!empty($appliedFilters['group_layanan']) && $appliedFilters['group_layanan'] !== 'ALL')
            ? $appliedFilters['group_layanan']
            : 'Semua Layanan';
        $filterLine = "Filter POP: {$popLabel}  |  Group Layanan: {$groupLabel}";
        if (!empty($appliedFilters['search'])) {
            $filterLine .= '  |  Pencarian: "' . $appliedFilters['search'] . '"';
        }

        $metaLine = 'Tanggal Export: ' . date('d F Y, H:i:s') . ' WIB  |  Dicetak Oleh: ' . strtoupper($userAuth);

        $merges = [
            'A1:N1',
            'A2:N2',
            'A3:N3',
            'A4:N4',
            'A6:C6',
            'A7:C7',
            'D6:F6',
            'D7:F7',
        ];
        if ($canSeeCjp) {
            $merges[] = 'G6:J6';
            $merges[] = 'G7:J7';
        }

        $lastDataRow = 9 + $rowCount;
        $totalRow = $rowCount > 0 ? ($lastDataRow + 1) : 11;

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<dimension ref="A1:' . $lastColLetter . $totalRow . '"/>';
        $xml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0">';
        $xml .= '<pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/>';
        $xml .= '<selection pane="bottomLeft" activeCell="A10" sqref="A10"/>';
        $xml .= '</sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="22"/>';

        // Column widths
        $xml .= '<cols>';
        foreach ($columns as $idx => $col) {
            $cNum = $idx + 1;
            $xml .= '<col min="' . $cNum . '" max="' . $cNum . '" width="' . $col['width'] . '" customWidth="1"/>';
        }
        $xml .= '</cols>';

        $xml .= '<sheetData>';

        // Row 1: Company Name
        $xml .= '<row r="1" ht="30" customHeight="1">'
            . $this->strCell('A1', 'PT CONNECTI JELAJAH PRIANGAN', 1)
            . '</row>';

        // Row 2: Report Subtitle
        $xml .= '<row r="2" ht="22" customHeight="1">'
            . $this->strCell('A2', 'LAPORAN DATA PELANGGAN BROADBAND (STATUS: AKTIF)', 2)
            . '</row>';

        // Row 3: Export Metadata
        $xml .= '<row r="3" ht="18" customHeight="1">'
            . $this->strCell('A3', $metaLine, 3)
            . '</row>';

        // Row 4: Applied Filters
        $xml .= '<row r="4" ht="18" customHeight="1">'
            . $this->strCell('A4', $filterLine, 3)
            . '</row>';

        // Row 5: Spacer
        $xml .= '<row r="5" ht="10" customHeight="1"/>';

        // Row 6: KPI Labels
        $xml .= '<row r="6" ht="22" customHeight="1">';
        $xml .= $this->strCell('A6', 'TOTAL PELANGGAN AKTIF', 4)
            . $this->emptyCell('B6', 4)
            . $this->emptyCell('C6', 4);
        $xml .= $this->strCell('D6', 'TOTAL HARGA BANDWIDTH MASTER', 4)
            . $this->emptyCell('E6', 4)
            . $this->emptyCell('F6', 4);
        if ($canSeeCjp) {
            $xml .= $this->strCell('G6', 'TOTAL HARGA CJP (25%)', 7)
                . $this->emptyCell('H6', 7)
                . $this->emptyCell('I6', 7)
                . $this->emptyCell('J6', 7);
        }
        $xml .= '</row>';

        // Row 7: KPI Values
        $xml .= '<row r="7" ht="30" customHeight="1">';
        $xml .= $this->numCell('A7', $rowCount, 5)
            . $this->emptyCell('B7', 5)
            . $this->emptyCell('C7', 5);
        $xml .= $this->numCell('D7', $totalSumMaster, 6)
            . $this->emptyCell('E7', 6)
            . $this->emptyCell('F7', 6);
        if ($canSeeCjp) {
            $xml .= $this->numCell('G7', $totalSumCjp, 8)
                . $this->emptyCell('H7', 8)
                . $this->emptyCell('I7', 8)
                . $this->emptyCell('J7', 8);
        }
        $xml .= '</row>';

        // Row 8: Spacer
        $xml .= '<row r="8" ht="12" customHeight="1"/>';

        // Row 9: Table Header
        $xml .= '<row r="9" ht="34" customHeight="1">';
        foreach ($columns as $idx => $col) {
            $ref = $this->colLetter($idx + 1) . '9';
            $style = !empty($col['is_cjp']) ? 10 : 9;
            $xml .= $this->strCell($ref, $col['label'], $style);
        }
        $xml .= '</row>';

        // Data Rows (starting at Row 10)
        $currentRow = 10;
        if ($rowCount > 0) {
            foreach ($rows as $idx => $row) {
                $isEven = ($idx % 2 === 1);
                $sLeft = $isEven ? 16 : 11;
                $sCenter = $isEven ? 17 : 12;
                $sBoldNavy = $isEven ? 18 : 13;
                $sCurrency = $isEven ? 19 : 14;
                $sCjp = $isEven ? 20 : 15;
                $sWrap = $isEven ? 28 : 27;

                $nama = $row->nama_pelanggan ?: ($row->nama_penduduk ?? '-');
                $nik = trim((string)($row->nik_penduduk ?? ''));
                $nik = $nik !== '' ? $nik : '-';
                $telp = trim((string)($row->telp_pelanggan ?? $row->nomor_hp ?? $row->no_telp ?? ''));
                $telp = $telp !== '' ? $telp : '-';
                $email = trim((string)($row->email_pelanggan ?? $row->email ?? ''));
                $email = $email !== '' ? $email : '-';

                $bwRaw = $row->nominal_bandwith ?: ($row->kode_bandwith ?? '-');
                $bw = is_numeric($bwRaw) ? ($bwRaw . ' Mbps') : (string)$bwRaw;

                $hargaMaster = !empty($row->harga_bandwith) ? (float)$row->harga_bandwith : 0.0;
                $hargaCjp = $hargaMaster * 0.25;

                $alamat = trim((string)($row->alamat_pasang ?? '-'));
                $alamatLines = max(1, (int)ceil(mb_strlen($alamat) / 42));
                $rowHeight = max(22, min(66, $alamatLines * 16 + 6));

                $tglReg = '-';
                if (!empty($row->date_create)) {
                    try {
                        $tglReg = Carbon::parse($row->date_create)->format('d/m/Y H:i');
                    } catch (\Throwable $e) {
                        $tglReg = (string)$row->date_create;
                    }
                }

                $c = 1;
                $xml .= '<row r="' . $currentRow . '" ht="' . $rowHeight . '" customHeight="1">';
                $xml .= $this->numCell($this->colLetter($c++) . $currentRow, $idx + 1, $sCenter);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->nomor_internet ?? '-'), $sBoldNavy);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)$nama, $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, $nik, $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, $telp, $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, $email, $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, $bw, $sCenter);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->nama_kategori_bandwith ?? '-'), $sLeft);
                $xml .= $this->numCell($this->colLetter($c++) . $currentRow, $hargaMaster, $sCurrency);

                if ($canSeeCjp) {
                    $xml .= $this->numCell($this->colLetter($c++) . $currentRow, $hargaCjp, $sCjp);
                }

                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->group_layanan ?? '-'), $sCenter);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->media_akses ?? '-'), $sCenter);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->nama_pop ?: ($row->kode_pop ?? '-')), $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->jenis_bangunan ?? '-'), $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, $alamat !== '' ? $alamat : '-', $sWrap);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, ($row->rt_pasang ?? '-') . '/' . ($row->rw_pasang ?? '-'), $sCenter);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->nomor_bangunan ?? '-'), $sCenter);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->nama_kelurahan ?? '-'), $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->nama_kecamatan ?? '-'), $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->nama_kota ?? '-'), $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->ont_us ?? '-'), $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->ont_ps ?? '-'), $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->olt ?? '-'), $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->index_olt ?? '-'), $sCenter);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, (string)($row->nama_sales ?? '-'), $sLeft);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, 'AKTIF', 21);
                $xml .= $this->strCell($this->colLetter($c++) . $currentRow, $tglReg, $sCenter);
                $xml .= '</row>';

                $currentRow++;
            }
        } else {
            $merges[] = "A10:{$lastColLetter}10";
            $xml .= '<row r="10" ht="30" customHeight="1">';
            $xml .= $this->strCell('A10', 'Tidak ada data pelanggan broadband aktif yang memenuhi kriteria pencarian / filter.', 26);
            for ($col = 2; $col <= $totalCols; $col++) {
                $xml .= $this->emptyCell($this->colLetter($col) . '10', 26);
            }
            $xml .= '</row>';
            $currentRow = 11;
        }

        // Total Row
        $totalRow = $currentRow;
        $lastDataRow = 9 + $rowCount;
        $merges[] = "A{$totalRow}:H{$totalRow}";

        $xml .= '<row r="' . $totalRow . '" ht="28" customHeight="1">';
        $xml .= $this->strCell("A{$totalRow}", 'TOTAL KESELURUHAN (' . number_format($rowCount, 0, ',', '.') . ' PELANGGAN AKTIF)', 22);
        for ($col = 2; $col <= 8; $col++) {
            $xml .= $this->emptyCell($this->colLetter($col) . $totalRow, 22);
        }

        if ($rowCount > 0) {
            $xml .= $this->formulaNumCell("I{$totalRow}", "SUM(I10:I{$lastDataRow})", $totalSumMaster, 23);
        } else {
            $xml .= $this->numCell("I{$totalRow}", 0, 23);
        }

        $trailingStart = 10;
        if ($canSeeCjp) {
            if ($rowCount > 0) {
                $xml .= $this->formulaNumCell("J{$totalRow}", "SUM(J10:J{$lastDataRow})", $totalSumCjp, 24);
            } else {
                $xml .= $this->numCell("J{$totalRow}", 0, 24);
            }
            $trailingStart = 11;
        }

        for ($col = $trailingStart; $col <= $totalCols; $col++) {
            $xml .= $this->emptyCell($this->colLetter($col) . $totalRow, 25);
        }
        $xml .= '</row>';

        $xml .= '</sheetData>';

        // AutoFilter MUST appear BEFORE mergeCells per ECMA-376 CT_Worksheet XSD
        if ($rowCount > 0) {
            $xml .= '<autoFilter ref="A9:' . $lastColLetter . $lastDataRow . '"/>';
        }

        // Merge cells
        $xml .= '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $ref) {
            $xml .= '<mergeCell ref="' . $ref . '"/>';
        }
        $xml .= '</mergeCells>';

        $xml .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>';
        $xml .= '<pageSetup orientation="landscape" paperSize="9"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    private function strCell(string $ref, string $value, int $style): string
    {
        $clean = $this->escapeXml($value);
        return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . $clean . '</t></is></c>';
    }

    private function numCell(string $ref, float|int $value, int $style): string
    {
        return '<c r="' . $ref . '" s="' . $style . '"><v>' . $this->formatXmlNumber($value) . '</v></c>';
    }

    private function formulaNumCell(string $ref, string $formula, float|int $cachedValue, int $style): string
    {
        return '<c r="' . $ref . '" s="' . $style . '"><f>' . $formula . '</f><v>' . $this->formatXmlNumber($cachedValue) . '</v></c>';
    }

    private function formatXmlNumber(float|int $value): string
    {
        if (is_int($value)) {
            return (string)$value;
        }
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
        return $formatted === '-0' ? '0' : $formatted;
    }

    private function emptyCell(string $ref, int $style): string
    {
        return '<c r="' . $ref . '" s="' . $style . '"/>';
    }

    private function escapeXml(string $value): string
    {
        // Strip invalid XML 1.0 control characters
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        if ($clean === null) {
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
        }
        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function colLetter(int $num): string
    {
        $letter = '';
        while ($num > 0) {
            $mod = ($num - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $num = intdiv($num - $mod, 26);
        }
        return $letter;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Pelanggan Broadband Aktif" sheetId="1" r:id="rId1"/>'
            . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2">'
            . '<numFmt numFmtId="164" formatCode="&quot;Rp &quot;#,##0"/>'
            . '<numFmt numFmtId="165" formatCode="#,##0"/>'
            . '</numFmts>'
            . '<fonts count="14">'
            . '<font><sz val="10"/><color rgb="FF1E293B"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><color rgb="FF1E3A8A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="12"/><color rgb="FF334155"/><name val="Calibri"/></font>'
            . '<font><i/><sz val="9.5"/><color rgb="FF64748B"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="9"/><color rgb="FF475569"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="9"/><color rgb="FF047857"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FF059669"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FF1E3A8A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FF047857"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="9.5"/><color rgb="FF166534"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'
            . '<font><i/><sz val="10"/><color rgb="FF94A3B8"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="11">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFECFDF5"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF047857"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="6">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFCBD5E1"/></left>'
            . '<right style="thin"><color rgb="FFCBD5E1"/></right>'
            . '<top style="thin"><color rgb="FFCBD5E1"/></top>'
            . '<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFCBD5E1"/></left>'
            . '<right style="thin"><color rgb="FFCBD5E1"/></right>'
            . '<top style="thin"><color rgb="FFCBD5E1"/></top>'
            . '<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFA7F3D0"/></left>'
            . '<right style="thin"><color rgb="FFA7F3D0"/></right>'
            . '<top style="thin"><color rgb="FFA7F3D0"/></top>'
            . '<bottom style="thin"><color rgb="FFA7F3D0"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '<border>'
            . '<left style="thin"><color rgb="FF334155"/></left>'
            . '<right style="thin"><color rgb="FF334155"/></right>'
            . '<top style="thin"><color rgb="FF334155"/></top>'
            . '<bottom style="thin"><color rgb="FF334155"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFCBD5E1"/></left>'
            . '<right style="thin"><color rgb="FFCBD5E1"/></right>'
            . '<top style="medium"><color rgb="FF1E3A8A"/></top>'
            . '<bottom style="double"><color rgb="FF1E3A8A"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="29">'
            // 0: Default
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            // 1: Title
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            // 2: Subtitle
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            // 3: Meta
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            // 4: KPI Label Neutral
            . '<xf numFmtId="0" fontId="4" fillId="2" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 5: KPI Value Count Neutral
            . '<xf numFmtId="165" fontId="5" fillId="3" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 6: KPI Value Currency Neutral
            . '<xf numFmtId="164" fontId="5" fillId="3" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 7: KPI Label Emerald
            . '<xf numFmtId="0" fontId="6" fillId="4" borderId="3" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 8: KPI Value Currency Emerald
            . '<xf numFmtId="164" fontId="7" fillId="4" borderId="3" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 9: Table Header Navy
            . '<xf numFmtId="0" fontId="8" fillId="5" borderId="4" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            // 10: Table Header Emerald
            . '<xf numFmtId="0" fontId="8" fillId="6" borderId="4" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            // 11: Data Odd Left
            . '<xf numFmtId="0" fontId="0" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            // 12: Data Odd Center
            . '<xf numFmtId="0" fontId="0" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 13: Data Odd Bold Navy Center
            . '<xf numFmtId="0" fontId="9" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 14: Data Odd Currency Right
            . '<xf numFmtId="164" fontId="0" fillId="7" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            // 15: Data Odd CJP Currency Right
            . '<xf numFmtId="164" fontId="10" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            // 16: Data Even Left
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            // 17: Data Even Center
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 18: Data Even Bold Navy Center
            . '<xf numFmtId="0" fontId="9" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 19: Data Even Currency Right
            . '<xf numFmtId="164" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            // 20: Data Even CJP Currency Right
            . '<xf numFmtId="164" fontId="10" fillId="8" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            // 21: Status AKTIF Badge
            . '<xf numFmtId="0" fontId="11" fillId="8" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 22: Total Label Right
            . '<xf numFmtId="0" fontId="12" fillId="9" borderId="5" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            // 23: Total Master Currency Right
            . '<xf numFmtId="164" fontId="12" fillId="9" borderId="5" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            // 24: Total CJP Currency Right
            . '<xf numFmtId="164" fontId="10" fillId="10" borderId="5" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            // 25: Total Trailing Blank
            . '<xf numFmtId="0" fontId="12" fillId="9" borderId="5" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            // 26: Empty State
            . '<xf numFmtId="0" fontId="13" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 27: Data Odd Wrap Left
            . '<xf numFmtId="0" fontId="0" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            // 28: Data Even Wrap Left
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
