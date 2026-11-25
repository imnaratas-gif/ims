<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
    body, table, td, th {
        font-family: 'Segoe UI', Calibri, Arial, Helvetica, sans-serif;
    }
    .text-title {
        font-size: 16pt;
        font-weight: bold;
        color: #1E3A8A;
        text-align: left;
    }
    .text-subtitle {
        font-size: 12pt;
        font-weight: bold;
        color: #334155;
        text-align: left;
    }
    .text-meta {
        font-size: 9.5pt;
        color: #64748B;
        text-align: left;
    }
    .kpi-box {
        border: 1pt solid #CBD5E1;
        background-color: #F8FAFC;
        padding: 8px 12px;
        text-align: center;
        vertical-align: middle;
    }
    .kpi-title {
        font-size: 9pt;
        font-weight: bold;
        color: #64748B;
        text-transform: uppercase;
    }
    .kpi-val {
        font-size: 13pt;
        font-weight: bold;
        color: #0F172A;
    }
    .kpi-val-cjp {
        font-size: 13pt;
        font-weight: bold;
        color: #059669;
    }
    .th-excel {
        background-color: #1E3A8A;
        color: #FFFFFF;
        font-weight: bold;
        font-size: 9.5pt;
        text-align: center;
        vertical-align: middle;
        border: 0.5pt solid #475569;
        padding: 8px 6px;
        height: 32px;
    }
    .td-excel {
        font-size: 9pt;
        vertical-align: middle;
        border: 0.5pt solid #CBD5E1;
        padding: 5px 7px;
    }
    .row-even {
        background-color: #F8FAFC;
    }
    .row-odd {
        background-color: #FFFFFF;
    }
    .td-total {
        background-color: #E2E8F0;
        font-weight: bold;
        font-size: 10pt;
        border-top: 1.5pt solid #1E3A8A;
        border-bottom: 2.5pt double #1E3A8A;
        border-left: 0.5pt solid #CBD5E1;
        border-right: 0.5pt solid #CBD5E1;
        padding: 6px 8px;
    }
    .badge-aktif {
        background-color: #DCFCE7;
        color: #166534;
        font-weight: bold;
        text-align: center;
    }
    /* Microsoft Excel Number Formatting Rules */
    .str {
        mso-number-format: "\@";
        text-align: left;
    }
    .str-center {
        mso-number-format: "\@";
        text-align: center;
    }
    .num {
        mso-number-format: "\#\,\#\#0";
        text-align: right;
    }
    .date {
        mso-number-format: "yyyy\-mm\-dd\ hh\:mm";
        text-align: center;
    }
</style>
</head>
<body>

@php
    $totalCols = $canSeeCjp ? 27 : 26;
@endphp

<table border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; width: 100%;">
    {{-- Header Perusahaan & Judul Laporan --}}
    <tr>
        <td colspan="{{ $totalCols }}" class="text-title" style="height: 28px;">
            PT CONNECTI JELAJAH PRIANGAN
        </td>
    </tr>
    <tr>
        <td colspan="{{ $totalCols }}" class="text-subtitle" style="height: 22px;">
            LAPORAN DATA PELANGGAN BROADBAND (STATUS: AKTIF)
        </td>
    </tr>
    <tr>
        <td colspan="{{ $totalCols }}" class="text-meta" style="height: 18px;">
            Tanggal Export: {{ date('d F Y, H:i:s') }} WIB &nbsp;|&nbsp; Dicetak Oleh: {{ $userAuth ?? 'KR7460' }}
        </td>
    </tr>
    <tr>
        <td colspan="{{ $totalCols }}" class="text-meta" style="height: 18px;">
            Filter POP: {{ !empty($appliedFilters['pop']) && $appliedFilters['pop'] !== 'ALL' ? $appliedFilters['pop'] : 'Semua POP' }} &nbsp;|&nbsp; 
            Group Layanan: {{ !empty($appliedFilters['group_layanan']) && $appliedFilters['group_layanan'] !== 'ALL' ? $appliedFilters['group_layanan'] : 'Semua Layanan' }}
            @if(!empty($appliedFilters['search']))
                &nbsp;|&nbsp; Pencarian: "{{ $appliedFilters['search'] }}"
            @endif
        </td>
    </tr>
    <tr>
        <td colspan="{{ $totalCols }}" style="height: 10px;"></td>
    </tr>

    {{-- Kartu Ringkasan Eksekutif (Summary KPIs) --}}
    <tr>
        <td colspan="4" class="kpi-box">
            <div class="kpi-title">TOTAL PELANGGAN AKTIF</div>
            <div class="kpi-val">{{ number_format(count($data), 0, ',', '.') }}</div>
        </td>
        <td colspan="5" class="kpi-box">
            <div class="kpi-title">TOTAL HARGA BANDWIDTH MASTER</div>
            <div class="kpi-val">Rp {{ number_format($totalSumMaster ?? 0, 0, ',', '.') }}</div>
        </td>
        @if($canSeeCjp)
        <td colspan="5" class="kpi-box" style="background-color: #ECFDF5; border: 1pt solid #A7F3D0;">
            <div class="kpi-title" style="color: #047857;">TOTAL HARGA CJP (25%)</div>
            <div class="kpi-val-cjp">Rp {{ number_format($totalSumCjp ?? 0, 0, ',', '.') }}</div>
        </td>
        <td colspan="{{ $totalCols - 14 }}"></td>
        @else
        <td colspan="{{ $totalCols - 9 }}"></td>
        @endif
    </tr>
    <tr>
        <td colspan="{{ $totalCols }}" style="height: 12px;"></td>
    </tr>

    {{-- Tabel Utama Data Pelanggan --}}
    <thead>
        <tr>
            <th class="th-excel" style="width: 40px;">NO</th>
            <th class="th-excel" style="width: 110px;">NO. INTERNET</th>
            <th class="th-excel" style="width: 220px;">NAMA PELANGGAN</th>
            <th class="th-excel" style="width: 140px;">NIK</th>
            <th class="th-excel" style="width: 120px;">NO. TELEPON / HP</th>
            <th class="th-excel" style="width: 180px;">EMAIL</th>
            <th class="th-excel" style="width: 90px;">BANDWIDTH</th>
            <th class="th-excel" style="width: 140px;">KATEGORI BANDWIDTH</th>
            <th class="th-excel" style="width: 150px;">HARGA BW MASTER (Rp)</th>
            @if($canSeeCjp)
                <th class="th-excel" style="width: 140px; background-color: #047857;">HARGA CJP 25% (Rp)</th>
            @endif
            <th class="th-excel" style="width: 110px;">GROUP LAYANAN</th>
            <th class="th-excel" style="width: 100px;">MEDIA AKSES</th>
            <th class="th-excel" style="width: 120px;">POP</th>
            <th class="th-excel" style="width: 110px;">JENIS BANGUNAN</th>
            <th class="th-excel" style="width: 260px;">ALAMAT PEMASANGAN</th>
            <th class="th-excel" style="width: 70px;">RT / RW</th>
            <th class="th-excel" style="width: 80px;">NO. BANGUNAN</th>
            <th class="th-excel" style="width: 130px;">KELURAHAN</th>
            <th class="th-excel" style="width: 130px;">KECAMATAN</th>
            <th class="th-excel" style="width: 140px;">KOTA / KABUPATEN</th>
            <th class="th-excel" style="width: 110px;">ONT USER</th>
            <th class="th-excel" style="width: 110px;">ONT PS</th>
            <th class="th-excel" style="width: 110px;">OLT</th>
            <th class="th-excel" style="width: 80px;">INDEX OLT</th>
            <th class="th-excel" style="width: 130px;">SALES</th>
            <th class="th-excel" style="width: 90px;">STATUS</th>
            <th class="th-excel" style="width: 130px;">TGL REGISTRASI</th>
        </tr>
    </thead>
    <tbody>
        @forelse($data as $index => $row)
            @php
                $bgClass = ($index % 2 === 0) ? 'row-odd' : 'row-even';
                $nama = $row->nama_pelanggan ?: ($row->nama_penduduk ?? '-');
                $nik = $row->nik_penduduk ?? '-';
                $telp = $row->telp_pelanggan ?? $row->nomor_hp ?? $row->no_telp ?? '-';
                $email = $row->email_pelanggan ?? $row->email ?? '-';
                $bw = $row->nominal_bandwith ?: ($row->kode_bandwith ?? '-');
                $hargaMaster = !empty($row->harga_bandwith) ? (float)$row->harga_bandwith : 0;
                $hargaCjp = $hargaMaster * 0.25;
            @endphp
            <tr class="{{ $bgClass }}">
                <td class="td-excel str-center">{{ $index + 1 }}</td>
                <td class="td-excel str-center" style="font-weight: bold; color: #1E3A8A;">{{ $row->nomor_internet ?? '-' }}</td>
                <td class="td-excel str" style="font-weight: 500;">{{ $nama }}</td>
                <td class="td-excel str">{{ $nik }}</td>
                <td class="td-excel str">{{ $telp }}</td>
                <td class="td-excel str">{{ $email }}</td>
                <td class="td-excel str-center">{{ $bw }}</td>
                <td class="td-excel str">{{ $row->nama_kategori_bandwith ?? '-' }}</td>
                <td class="td-excel num">{{ $hargaMaster }}</td>
                @if($canSeeCjp)
                    <td class="td-excel num" style="font-weight: bold; color: #047857; background-color: {{ $index % 2 === 0 ? '#F0FDF4' : '#DCFCE7' }};">
                        {{ $hargaCjp }}
                    </td>
                @endif
                <td class="td-excel str-center">{{ $row->group_layanan ?? '-' }}</td>
                <td class="td-excel str-center">{{ $row->media_akses ?? '-' }}</td>
                <td class="td-excel str">{{ $row->nama_pop ?: ($row->kode_pop ?? '-') }}</td>
                <td class="td-excel str">{{ $row->jenis_bangunan ?? '-' }}</td>
                <td class="td-excel str">{{ $row->alamat_pasang ?? '-' }}</td>
                <td class="td-excel str-center">{{ ($row->rt_pasang ?? '-') . '/' . ($row->rw_pasang ?? '-') }}</td>
                <td class="td-excel str-center">{{ $row->nomor_bangunan ?? '-' }}</td>
                <td class="td-excel str">{{ $row->nama_kelurahan ?? '-' }}</td>
                <td class="td-excel str">{{ $row->nama_kecamatan ?? '-' }}</td>
                <td class="td-excel str">{{ $row->nama_kota ?? '-' }}</td>
                <td class="td-excel str">{{ $row->ont_us ?? '-' }}</td>
                <td class="td-excel str">{{ $row->ont_ps ?? '-' }}</td>
                <td class="td-excel str">{{ $row->olt ?? '-' }}</td>
                <td class="td-excel str-center">{{ $row->index_olt ?? '-' }}</td>
                <td class="td-excel str">{{ $row->nama_sales ?? '-' }}</td>
                <td class="td-excel badge-aktif">AKTIF</td>
                <td class="td-excel date">{{ $row->date_create ? \Carbon\Carbon::parse($row->date_create)->format('Y-m-d H:i') : '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $totalCols }}" class="td-excel str-center" style="padding: 20px; color: #94A3B8; font-style: italic;">
                    Tidak ada data pelanggan broadband aktif yang memenuhi kriteria pencarian / filter.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="8" class="td-total" style="text-align: right; text-transform: uppercase;">
                TOTAL KESELURUHAN ({{ count($data) }} PELANGGAN AKTIF) :
            </td>
            <td class="td-total num" style="font-weight: bold; color: #0F172A;">
                {{ $totalSumMaster }}
            </td>
            @if($canSeeCjp)
                <td class="td-total num" style="font-weight: bold; color: #047857; background-color: #D1FAE5;">
                    {{ $totalSumCjp }}
                </td>
                <td colspan="17" class="td-total"></td>
            @else
                <td colspan="17" class="td-total"></td>
            @endif
        </tr>
    </tfoot>
</table>

</body>
</html>
