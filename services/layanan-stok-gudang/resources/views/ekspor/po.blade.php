<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $nomor }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .muted { color: #555; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #444; padding: 6px 8px; text-align: left; }
        th { background: #eee; }
        td.angka, th.angka { text-align: right; }
        .tanda-tangan { margin-top: 40px; width: 45%; float: right; text-align: center; }
        .tanda-tangan p { margin-top: 70px; border-top: 1px solid #111; padding-top: 4px; }
        .clear { clear: both; }
    </style>
</head>
<body>
    <h1>{{ $judul }}</h1>
    <p class="muted">Nomor: {{ $nomor }} &nbsp;|&nbsp; Tanggal: {{ $tanggal }} &nbsp;|&nbsp; Alamat: Warung Kelontong</p>

    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Nama Barang</th>
                <th class="angka">Stok Sekarang</th>
                <th class="angka">Stok Minimum</th>
                <th class="angka">Kurang</th>
                <th class="angka">Usul Kulakan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $baris)
                <tr>
                    <td>{{ $baris['sku'] }}</td>
                    <td>{{ $baris['nama'] }}</td>
                    <td class="angka">{{ $baris['stok_sekarang'] }}</td>
                    <td class="angka">{{ $baris['stok_minimum'] }}</td>
                    <td class="angka">{{ $baris['kurang'] }}</td>
                    <td class="angka">{{ $baris['usul_kulakan'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="tanda-tangan">
        <p>Pemilik Warung</p>
    </div>
    <div class="clear"></div>
</body>
</html>
