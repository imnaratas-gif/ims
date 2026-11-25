<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class BroadbandController extends Controller
{
    /**
     * Menampilkan daftar data Broadband dari database ke-2 (ims_v3)
     */
    public function index(Request $request)
    {
        $dbConnected = true;
        $connectionError = null;

        $statusCounts = [
            'semua'     => 0,
            'aktif'     => 0,
            'suspend'   => 0,
            'terminasi' => 0,
        ];
        $totalHargaCjpAktif = 0;

        $popOptions = collect();
        $groupLayananOptions = collect();
        $broadbands = new LengthAwarePaginator([], 0, 10);

        try {
            // Test ping koneksi ims_v3
            $v3 = DB::connection('ims_v3');
            $v3->getPdo();

            // Cek apakah tabel trx_batchjob_register ada
            $tableExists = true;
            try {
                $v3->table('trx_batchjob_register')->limit(1)->first();
            } catch (\Throwable $e) {
                $tableExists = false;
                $connectionError = "Tabel 'trx_batchjob_register' tidak ditemukan di database ims_v3: " . $e->getMessage();
            }

            if ($tableExists) {
                // 1. Ambil list dropdown filter dari data yang ada
                try {
                    $popOptions = $v3->table('trx_batchjob_register')
                        ->whereNotNull('kode_pop')
                        ->where('kode_pop', '!=', '')
                        ->distinct()
                        ->pluck('kode_pop')
                        ->sort()
                        ->values();

                    $groupLayananOptions = $v3->table('trx_batchjob_register')
                        ->whereNotNull('group_layanan')
                        ->where('group_layanan', '!=', '')
                        ->distinct()
                        ->pluck('group_layanan')
                        ->sort()
                        ->values();
                } catch (\Throwable $e) {
                    // Abaikan jika gagal ambil dropdown
                }

                // 2. Hitung ringkasan status count
                try {
                    $baseCountQuery = $v3->table('trx_batchjob_register')
                        ->where(function ($q) {
                            $q->where('hide', '0')->orWhereNull('hide');
                        });

                    $statusCounts['semua'] = (clone $baseCountQuery)->count();
                    $statusCounts['aktif'] = (clone $baseCountQuery)
                        ->where(function($q) {
                            $q->whereNull('is_termin')->orWhere('is_termin', '!=', '1');
                        })
                        ->where(function($q) {
                            $q->whereNull('is_suspend')->orWhere('is_suspend', '!=', '1');
                        })
                        ->count();

                    $statusCounts['suspend'] = (clone $baseCountQuery)
                        ->where('is_suspend', '1')
                        ->count();

                    $statusCounts['terminasi'] = (clone $baseCountQuery)
                        ->where('is_termin', '1')
                        ->count();

                    // Hitung total harga CJP (25% dari total harga bandwidth master untuk pelanggan aktif)
                    try {
                        $sumMasterAktif = $v3->table('trx_batchjob_register as t')
                            ->leftJoin('m_bandwith as b', 'b.kode_bandwith', '=', 't.kode_bandwith')
                            ->where(function ($q) {
                                $q->where('t.hide', '0')->orWhereNull('t.hide');
                            })
                            ->where(function($q) {
                                $q->whereNull('t.is_termin')->orWhere('t.is_termin', '!=', '1');
                            })
                            ->where(function($q) {
                                $q->whereNull('t.is_suspend')->orWhere('t.is_suspend', '!=', '1');
                            })
                            ->sum('b.harga_bandwith');

                        $totalHargaCjpAktif = (float)$sumMasterAktif * 0.25;
                    } catch (\Throwable $e) {
                        $totalHargaCjpAktif = 0;
                    }
                } catch (\Throwable $e) {
                    // Abaikan error count
                }

                // 3. Bangun query data utama
                // Kita coba select dengan left join ke master tables jika ada
                $hasMasterTables = true;
                $query = $v3->table('trx_batchjob_register as t');

                try {
                    // Cek ketersediaan tabel master m_pop, m_bandwith, m_status_registrasi, m_wilayah, m_pelanggan
                    $query->leftJoin('m_pop as p', 'p.kode_pop', '=', 't.kode_pop')
                          ->leftJoin('m_bandwith as b', 'b.kode_bandwith', '=', 't.kode_bandwith')
                          ->leftJoin('m_bandwith_kategori as bk', 'bk.kode_kategori_bandwith', '=', 'b.kode_kategori_bandwith')
                          ->leftJoin('m_status_registrasi as sr', 'sr.status_reg', '=', 't.status_reg')
                          ->leftJoin('m_pelanggan as mp', 'mp.nik_penduduk', '=', 't.nik_penduduk')
                          ->leftJoin('m_wilayah as w', 'w.kode_wilayah_kelurahan', '=', 't.kode_wilayah_kelurahan_pasang')
                          ->select(
                              't.*',
                              'p.nama_pop',
                              'b.nominal_bandwith',
                              'b.harga_bandwith',
                              'bk.nama_kategori_bandwith',
                              'sr.desc_registrasi',
                              'mp.nama_penduduk',
                              'mp.telp_pelanggan',
                              'mp.email_pelanggan',
                              'w.nama_kelurahan',
                              'w.nama_kecamatan',
                              'w.nama_kota'
                          );
                    // Cek test query cepat
                    (clone $query)->limit(1)->first();
                } catch (\Throwable $e) {
                    // Fallback jika tidak ada tabel master di ims_v3
                    $hasMasterTables = false;
                    $query = $v3->table('trx_batchjob_register as t')->select('t.*');
                }

                // Filter hide
                $query->where(function ($q) {
                    $q->where('t.hide', '0')->orWhereNull('t.hide');
                });

                // Filter Status
                $statusFilter = strtolower(trim((string) $request->input('status', '')));
                if ($statusFilter === 'aktif') {
                    $query->where(function($q) {
                        $q->whereNull('t.is_termin')->orWhere('t.is_termin', '!=', '1');
                    })->where(function($q) {
                        $q->whereNull('t.is_suspend')->orWhere('t.is_suspend', '!=', '1');
                    });
                } elseif ($statusFilter === 'suspend') {
                    $query->where('t.is_suspend', '1');
                } elseif ($statusFilter === 'terminasi') {
                    $query->where('t.is_termin', '1');
                }

                // Filter POP
                if ($request->filled('pop') && $request->pop !== 'ALL') {
                    $query->where('t.kode_pop', $request->pop);
                }

                // Filter Group Layanan
                if ($request->filled('group_layanan') && $request->group_layanan !== 'ALL') {
                    $query->where('t.group_layanan', $request->group_layanan);
                }

                // Filter Search
                if ($request->filled('search')) {
                    $search = trim($request->search);
                    $query->where(function ($q) use ($search, $hasMasterTables) {
                        $q->where('t.nomor_internet', 'LIKE', "%{$search}%")
                          ->orWhere('t.nama_pelanggan', 'LIKE', "%{$search}%")
                          ->orWhere('t.nik_penduduk', 'LIKE', "%{$search}%")
                          ->orWhere('t.alamat_pasang', 'LIKE', "%{$search}%")
                          ->orWhere('t.ont_us', 'LIKE', "%{$search}%")
                          ->orWhere('t.kode_bandwith', 'LIKE', "%{$search}%")
                          ->orWhere('t.nama_sales', 'LIKE', "%{$search}%")
                          ->orWhere('t.olt', 'LIKE', "%{$search}%");

                        if ($hasMasterTables) {
                            $q->orWhere('p.nama_pop', 'LIKE', "%{$search}%")
                              ->orWhere('mp.nama_penduduk', 'LIKE', "%{$search}%")
                              ->orWhere('mp.telp_pelanggan', 'LIKE', "%{$search}%")
                              ->orWhere('w.nama_kelurahan', 'LIKE', "%{$search}%")
                              ->orWhere('w.nama_kecamatan', 'LIKE', "%{$search}%")
                              ->orWhere('w.nama_kota', 'LIKE', "%{$search}%");
                        }
                    });
                }

                // Pagination
                $perPage = (int) ($request->entries ?? 10);
                if (!in_array($perPage, [10, 25, 50, 100], true)) {
                    $perPage = 10;
                }

                // Sorting
                $query->orderByDesc('t.date_create');

                $broadbands = $query->paginate($perPage)->withQueryString();
            }

        } catch (\Throwable $e) {
            $dbConnected = false;
            $connectionError = $e->getMessage();
        }

        return view('broadband.index', compact(
            'broadbands',
            'statusCounts',
            'totalHargaCjpAktif',
            'popOptions',
            'groupLayananOptions',
            'dbConnected',
            'connectionError'
        ));
    }

    /**
     * Export data pelanggan Broadband aktif ke format CSV/Excel
     */
    public function export(Request $request)
    {
        try {
            $v3 = DB::connection('ims_v3');
            $v3->getPdo();

            // Query hanya untuk pelanggan yang AKTIF
            $query = $v3->table('trx_batchjob_register as t')
                ->leftJoin('m_pop as p', 'p.kode_pop', '=', 't.kode_pop')
                ->leftJoin('m_bandwith as b', 'b.kode_bandwith', '=', 't.kode_bandwith')
                ->leftJoin('m_bandwith_kategori as bk', 'bk.kode_kategori_bandwith', '=', 'b.kode_kategori_bandwith')
                ->leftJoin('m_pelanggan as mp', 'mp.nik_penduduk', '=', 't.nik_penduduk')
                ->leftJoin('m_wilayah as w', 'w.kode_wilayah_kelurahan', '=', 't.kode_wilayah_kelurahan_pasang')
                ->where(function ($q) {
                    $q->where('t.hide', '0')->orWhereNull('t.hide');
                })
                ->where(function($q) {
                    $q->whereNull('t.is_termin')->orWhere('t.is_termin', '!=', '1');
                })
                ->where(function($q) {
                    $q->whereNull('t.is_suspend')->orWhere('t.is_suspend', '!=', '1');
                })
                ->select(
                    't.*',
                    'p.nama_pop',
                    'b.nominal_bandwith',
                    'b.harga_bandwith',
                    'bk.nama_kategori_bandwith',
                    'mp.nama_penduduk',
                    'mp.nomor_hp',
                    'mp.email',
                    'w.nama_kelurahan',
                    'w.nama_kecamatan',
                    'w.nama_kota'
                );

            // Filter Pencarian jika diisi
            if ($request->filled('search')) {
                $s = trim($request->search);
                $query->where(function($q) use ($s) {
                    $q->where('t.nomor_internet', 'like', "%{$s}%")
                      ->orWhere('t.nama_pelanggan', 'like', "%{$s}%")
                      ->orWhere('mp.nama_penduduk', 'like', "%{$s}%")
                      ->orWhere('t.nik_penduduk', 'like', "%{$s}%")
                      ->orWhere('t.alamat_pasang', 'like', "%{$s}%")
                      ->orWhere('t.ont_us', 'like', "%{$s}%")
                      ->orWhere('t.ont_ps', 'like', "%{$s}%")
                      ->orWhere('t.olt', 'like', "%{$s}%");
                });
            }

            // Filter POP
            if ($request->filled('pop') && $request->pop !== 'ALL') {
                $query->where('t.kode_pop', $request->pop);
            }

            // Filter Group Layanan
            if ($request->filled('group_layanan') && $request->group_layanan !== 'ALL') {
                $query->where('t.group_layanan', $request->group_layanan);
            }

            $data = $query->orderByDesc('t.date_create')->get();

            // Cek hak akses user untuk kolom Harga CJP
            $u = session('user', []);
            $userLevel    = strtoupper($u['level'] ?? '');
            $kodeLevel    = $u['kode_level'] ?? '';
            $username     = strtoupper(trim($u['username'] ?? ''));
            $kodeKaryawan = strtoupper(trim($u['kode_karyawan'] ?? ''));
            $kodePengguna = strtoupper(trim($u['kode_pengguna'] ?? ''));

            $isAdmin = ($userLevel === 'ADMIN' || $kodeLevel === 'lv00001' || str_contains($userLevel, 'ADMIN') || $username === 'ADMIN');
            $isKR7460 = in_array('KR7460', [$username, $kodeKaryawan, $kodePengguna], true);
            $canSeeCjp = $isAdmin || $isKR7460;

            if (!$canSeeCjp && !empty($username)) {
                try {
                    $uDb = DB::table('view_pengguna')->where('username', $u['username'] ?? '')->first();
                    if ($uDb) {
                        $dbUname = strtoupper(trim($uDb->username ?? ''));
                        $dbKaryawan = strtoupper(trim($uDb->kode_karyawan ?? ''));
                        $dbPengguna = strtoupper(trim($uDb->kode_pengguna ?? ''));
                        if (in_array('KR7460', [$dbUname, $dbKaryawan, $dbPengguna], true)) {
                            $canSeeCjp = true;
                        }
                    }
                } catch (\Throwable $e) {}
            }

            $filename = 'Data_Pelanggan_Broadband_Aktif_' . date('Ymd_His') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $callback = function () use ($data, $canSeeCjp) {
                $file = fopen('php://output', 'w');
                fprintf($file, "\xEF\xBB\xBF"); // UTF-8 BOM untuk Excel

                $csvHeaders = [
                    'No. Internet',
                    'Nama Pelanggan',
                    'NIK',
                    'No. HP',
                    'Email',
                    'Bandwidth',
                    'Kategori Bandwidth',
                    'Harga Bandwidth Master',
                ];

                if ($canSeeCjp) {
                    $csvHeaders[] = 'Harga CJP (25%)';
                }

                $csvHeaders = array_merge($csvHeaders, [
                    'Group Layanan',
                    'Media Akses',
                    'POP',
                    'Jenis Bangunan',
                    'Alamat Pemasangan',
                    'RT/RW',
                    'No. Bangunan',
                    'Kelurahan',
                    'Kecamatan',
                    'Kota/Kabupaten',
                    'ONT User',
                    'ONT PS',
                    'OLT',
                    'Index OLT',
                    'Sales',
                    'Status',
                    'Tanggal Registrasi'
                ]);

                fputcsv($file, $csvHeaders);

                foreach ($data as $row) {
                    $nama = $row->nama_pelanggan ?: ($row->nama_penduduk ?? '-');
                    $nik = $row->nik_penduduk ?? '-';
                    $telp = $row->telp_pelanggan ?? $row->nomor_hp ?? '-';
                    $email = $row->email_pelanggan ?? $row->email ?? '-';
                    $bw = $row->nominal_bandwith ?: ($row->kode_bandwith ?? '-');
                    $hargaMaster = !empty($row->harga_bandwith) ? (float)$row->harga_bandwith : 0;

                    $rowFields = [
                        $row->nomor_internet ?? '-',
                        $nama,
                        "'" . $nik,
                        "'" . $telp,
                        $email,
                        $bw,
                        $row->nama_kategori_bandwith ?? '-',
                        $hargaMaster,
                    ];

                    if ($canSeeCjp) {
                        $rowFields[] = $hargaMaster * 0.25;
                    }

                    $rowFields = array_merge($rowFields, [
                        $row->group_layanan ?? '-',
                        $row->media_akses ?? '-',
                        $row->nama_pop ?: ($row->kode_pop ?? '-'),
                        $row->jenis_bangunan ?? '-',
                        $row->alamat_pasang ?? '-',
                        ($row->rt_pasang ?? '-') . '/' . ($row->rw_pasang ?? '-'),
                        $row->nomor_bangunan ?? '-',
                        $row->nama_kelurahan ?? '-',
                        $row->nama_kecamatan ?? '-',
                        $row->nama_kota ?? '-',
                        $row->ont_us ?? '-',
                        $row->ont_ps ?? '-',
                        $row->olt ?? '-',
                        $row->index_olt ?? '-',
                        $row->nama_sales ?? '-',
                        'AKTIF',
                        $row->date_create ? \Carbon\Carbon::parse($row->date_create)->format('d/m/Y H:i') : '-'
                    ]);

                    fputcsv($file, $rowFields);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Throwable $e) {
            return redirect()->route('broadband.index')->withErrors(['error' => 'Gagal mengekspor data: ' . $e->getMessage()]);
        }
    }

    /**
     * Tampilan detail lengkap profil data pelanggan Broadband (Read-only)
     */
    public function show($nomor_internet)
    {
        try {
            $v3 = DB::connection('ims_v3');
            $v3->getPdo();

            $item = $v3->table('trx_batchjob_register')
                ->where('nomor_internet', $nomor_internet)
                ->first();

            if (!$item) {
                return redirect()->route('broadband.index')->withErrors(['error' => 'Data Broadband dengan Nomor Internet ' . $nomor_internet . ' tidak ditemukan di database ims_v3.']);
            }

            $item = $this->hydrateBroadbandItem($v3, $item);

            return view('broadband.detail', compact('item'));

        } catch (\Throwable $e) {
            return redirect()->route('broadband.index')->withErrors(['error' => 'Koneksi database ims_v3 gagal: ' . $e->getMessage()]);
        }
    }

    /**
     * Quick View Modal via AJAX
     */
    public function modal($nomor_internet)
    {
        try {
            $v3 = DB::connection('ims_v3');
            $item = $v3->table('trx_batchjob_register')
                ->where('nomor_internet', $nomor_internet)
                ->first();

            if (!$item) {
                return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan.'], 404);
            }

            $item = $this->hydrateBroadbandItem($v3, $item);

            return response()->json([
                'status' => 'success',
                'data' => $item,
            ]);

        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mengisi relasi dan default attributes pada stdClass Broadband agar aman dari undefined property
     */
    private function hydrateBroadbandItem($v3, $item)
    {
        $defaults = [
            'nominal_bandwith'       => null,
            'harga_bandwith'         => null,
            'nama_kategori_bandwith' => null,
            'nama_pop'               => null,
            'desc_registrasi'        => null,
            'nama_penduduk'          => null,
            'telp_pelanggan'         => null,
            'email_pelanggan'        => null,
            'alamat_ktp'             => null,
            'nama_kelurahan'         => null,
            'nama_kecamatan'         => null,
            'nama_kota'              => null,
        ];

        foreach ($defaults as $k => $v) {
            if (!property_exists($item, $k)) {
                $item->$k = $v;
            }
        }

        // 1. Ambil data Bandwidth
        if (!empty($item->kode_bandwith)) {
            try {
                $bw = $v3->table('m_bandwith as b')
                    ->leftJoin('m_bandwith_kategori as bk', 'bk.kode_kategori_bandwith', '=', 'b.kode_kategori_bandwith')
                    ->where('b.kode_bandwith', $item->kode_bandwith)
                    ->select('b.nominal_bandwith', 'b.harga_bandwith', 'bk.nama_kategori_bandwith')
                    ->first();
                if ($bw) {
                    $item->nominal_bandwith       = $bw->nominal_bandwith ?? null;
                    $item->harga_bandwith         = $bw->harga_bandwith ?? null;
                    $item->nama_kategori_bandwith = $bw->nama_kategori_bandwith ?? null;
                }
            } catch (\Throwable $e) {}
        }

        // 2. Ambil data POP
        if (!empty($item->kode_pop)) {
            try {
                $pop = $v3->table('m_pop')
                    ->where('kode_pop', $item->kode_pop)
                    ->value('nama_pop');
                if ($pop) {
                    $item->nama_pop = $pop;
                }
            } catch (\Throwable $e) {}
        }

        // 3. Ambil data Status Registrasi
        if (!empty($item->status_reg)) {
            try {
                $sr = $v3->table('m_status_registrasi')
                    ->where('status_reg', $item->status_reg)
                    ->value('desc_registrasi');
                if ($sr) {
                    $item->desc_registrasi = $sr;
                }
            } catch (\Throwable $e) {}
        }

        // 4. Ambil data Pelanggan
        if (!empty($item->nik_penduduk)) {
            try {
                $pel = $v3->table('m_pelanggan')
                    ->where('nik_penduduk', $item->nik_penduduk)
                    ->first();
                if ($pel) {
                    $item->nama_penduduk  = $pel->nama_penduduk ?? null;
                    $item->telp_pelanggan = $pel->telp_pelanggan ?? $pel->no_telp ?? $pel->telepon ?? null;
                    $item->email_pelanggan = $pel->email_pelanggan ?? $pel->email ?? null;
                    $item->alamat_ktp     = $pel->alamat_p ?? $pel->alamat_ktp ?? null;
                }
            } catch (\Throwable $e) {}
        }

        // 5. Ambil data Wilayah
        if (!empty($item->kode_wilayah_kelurahan_pasang)) {
            try {
                $wil = $v3->table('m_wilayah')
                    ->where('kode_wilayah_kelurahan', $item->kode_wilayah_kelurahan_pasang)
                    ->first();
                if ($wil) {
                    $item->nama_kelurahan = $wil->nama_kelurahan ?? null;
                    $item->nama_kecamatan = $wil->nama_kecamatan ?? null;
                    $item->nama_kota      = $wil->nama_kota ?? null;
                }
            } catch (\Throwable $e) {}
        }

        return $item;
    }
}
