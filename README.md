# Warung Kelontong Microservices

Sistem pengelolaan warung kelontong berbasis arsitektur
Microservices menggunakan Laravel, MySQL, Docker, dan RabbitMQ.

## 1. Deskripsi Proyek

Warung Kelontong Microservices merupakan sistem yang memisahkan
pengelolaan transaksi kasir dan pengelolaan stok gudang ke dalam
layanan yang berbeda.

Pemisahan layanan bertujuan agar setiap layanan dapat menjalankan
tanggung jawabnya secara mandiri dan berkomunikasi melalui API
serta message broker.

## 2. Arsitektur Sistem

Sistem terdiri dari dua layanan utama:

### A. Kasir Warung

Berfungsi untuk:
- Mengelola data produk.
- Memproses transaksi penjualan.
- Mengelola pesanan tunai dan utang.
- Menerima pesanan melalui WhatsApp API.
- Melakukan validasi ketersediaan stok kasir.
- Mencatat transaksi melalui mekanisme Outbox.
- Menerbitkan event transaksi ke RabbitMQ.

### B. Layanan Stok Gudang

Berfungsi untuk:
- Mengelola data stok fisik gudang.
- Memproses pergerakan stok masuk dan keluar.
- Memperbarui stok berdasarkan transaksi.
- Mencatat riwayat pergerakan stok.
- Membuat rekap transaksi.
- Mengelola pesan yang gagal melalui Dead Letter Queue.

## 3. Teknologi yang Digunakan

| Teknologi | Fungsi |
|---|---|
| PHP | Bahasa pemrograman |
| Laravel | Framework backend |
| MySQL | Database |
| RabbitMQ | Message broker |
| Docker | Containerization |
| REST API | Komunikasi antarlayanan |
| OpenAPI | Dokumentasi API |
| Spectral | Validasi spesifikasi API |
| Git & GitHub | Version control |

## 4. Arsitektur Komunikasi

Sistem menggunakan dua pola komunikasi:

### Synchronous Communication

Digunakan ketika layanan membutuhkan respons
langsung dari layanan lain, seperti validasi stok
melalui InventoryClient.

### Asynchronous Communication

Digunakan untuk menyampaikan informasi transaksi
dari layanan kasir menuju layanan gudang melalui RabbitMQ.

Alur komunikasi:

1. Kasir mencatat transaksi.
2. Transaksi disimpan bersama pesan Outbox.
3. Outbox Publisher menerbitkan event TransaksiTercatat.
4. Event dikirim melalui exchange warung.transaksi.
5. RabbitMQ meneruskan pesan ke antrean konsumen.
6. Konsumen stok memperbarui stok gudang.
7. Konsumen rekap mencatat transaksi.
8. Pesan gagal diproses dapat diteruskan ke Dead Letter Queue.

### Konfigurasi RabbitMQ

| Komponen | Nama |
|---|---|
| Exchange | warung.transaksi |
| Exchange Type | Topic |
| Routing Key Tunai | transaksi.tunai |
| Routing Key Utang | transaksi.utang |
| Queue Stok | gudang.stok |
| Queue Rekap | gudang.rekap |
| Dead Letter Queue | gudang.dlq |

Sistem menerapkan message acknowledgment dan
idempotency untuk membantu mencegah pemrosesan
pesan yang sama secara berulang.

## 5. Struktur Repository

```text
warung-kelontong/
├── openapi/
│   └── kasir-warung.yaml
├── services/
│   ├── kasir-warung/
│   │   ├── app/
│   │   ├── config/
│   │   ├── database/
│   │   ├── routes/
│   │   └── tests/
│   └── layanan-stok-gudang/
│       ├── app/
│       ├── config/
│       ├── database/
│       ├── routes/
│       └── tests/
├── .gitignore
├── .spectral.yaml
├── package.json
└── README.md
```

## 6. Endpoint API Kasir Warung

Base URL: http://localhost:8082

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | /api/v1/produk | Melihat produk |
| POST | /api/v1/produk | Menambahkan produk |
| POST | /api/v1/penjualan | Mencatat penjualan |
| GET | /api/v1/pesanan | Melihat pesanan |
| POST | /api/v1/pesanan-whatsapp | Menerima pesanan WhatsApp |

## 7. Port Layanan

| Layanan | Port |
|---|---:|
| Kasir Warung | 8082 |
| Layanan Stok Gudang | 8083 |
| RabbitMQ AMQP | 5673 |
| RabbitMQ Management | 15673 |

## 8. Dokumentasi API

Spesifikasi API menggunakan OpenAPI 3.0.3
dan tersedia pada:

`openapi/kasir-warung.yaml`

Validasi spesifikasi API menggunakan Spectral.

## 9. Pengujian

Pengujian dilakukan secara terpisah pada masing-masing layanan.

Kasir Warung:

```bash
cd services/kasir-warung
./vendor/bin/sail artisan test
```

Layanan Stok Gudang:

```bash
cd services/layanan-stok-gudang
./vendor/bin/sail artisan test
```

## 10. Repository

GitHub:
https://github.com/zaky573/warung-kelontong
