# Nexus Bell: aplikasi bel sekolah untuk PC bel

Aplikasi ini berjalan di **satu PC Windows** di sekolah. PC itu punya dua kartu suara:

- **Lantai 2**: suara dari kartu suara onboard, masuk ke mixer lantai 2.
- **Lantai 3**: suara dari kartu suara tambahan, masuk ke mixer lantai 3.

Setiap lantai punya **jadwal sendiri**: profil sendiri (Normal, Jumat, Ujian, Pendek),
hari libur sendiri, dan volume sendiri. Mengubah satu lantai tidak pernah
mengubah lantai yang lain. Dua lantai yang berbunyi pada detik yang sama
dimainkan **bersamaan**.

## Cara kerjanya (singkat)

1. Jadwal diatur di website Nexus, menu **Bell** (bisa dari HP atau laptop).
2. Aplikasi di PC bel mengunduh jadwal itu setiap 30 detik dan **menyimpannya**.
3. Bel dibunyikan dari salinan yang tersimpan. **Kalau internet mati, bel tetap berbunyi.**

## 1. Pasang di PC bel

1. Salin file **`NexusBell.exe`** ke PC bel, misalnya ke `C:\NexusBell\`
   (satu file saja, tidak perlu install Python).
2. Klik dua kali `NexusBell.exe`. Jendela aplikasi terbuka.
3. Folder `C:\NexusBell\` akan berisi `pengaturan.json` (pengaturan), folder `suara`
   (suara yang diunduh) dan folder `log` (catatan harian). Jangan hapus folder ini.

Membuat `NexusBell.exe` sendiri (sekali saja, di PC yang ada Python 3.11+):

```
powershell -ExecutionPolicy Bypass -File build.ps1
```

Hasilnya ada di `dist\NexusBell.exe`. Tes otomatis dijalankan dulu; kalau ada tes
yang gagal, file `.exe` tidak dibuat.

## 2. Sambungkan ke Nexus

1. Di website Nexus: **Bell > Bell PC > Buat kunci koneksi**. Salin kuncinya
   (hanya tampil satu kali).
2. Di aplikasi, tekan **Koneksi**. Isi **Alamat Nexus**
   (`https://nexus.pribadidepok.sch.id`) dan **Kunci koneksi**, lalu tekan
   **Simpan & sambungkan**.
3. Tulisan di bagian atas berubah menjadi *Tersambung ke Nexus*. Jadwal kedua
   lantai muncul di jendela. Di website, halaman **Bell PC** menunjukkan **Online**.

## 3. Pilih perangkat suara untuk tiap lantai

1. Di panel **Lantai 2**, buka kotak **Perangkat suara** dan pilih kartu suara
   onboard (biasanya *Speakers (Realtek ...)*).
2. Di panel **Lantai 3**, pilih kartu suara tambahan (misalnya *Speakers (USB Audio Device)*).
3. Tekan **Tes perangkat** di setiap panel. Pastikan suara keluar di lantai yang benar.
   Kalau terbalik, tukar pilihan perangkat kedua panel.

Perangkat disimpan dengan **nama**, bukan nomor, jadi tetap benar walaupun
nomor perangkat berubah setelah PC dinyalakan ulang. Kalau satu perangkat
tidak ditemukan, muncul peringatan kuning di atas, dan lantai yang lain tetap
berjalan.

## 4. Tes tiap lantai

- **Tes bel terpilih**: pilih satu baris jadwal lalu tekan tombol ini (atau klik dua kali barisnya).
- **Bunyikan sekarang**: membunyikan bel biasa di lantai itu saat ini juga.
- Tes tidak mengubah jadwal dan tidak dihitung sebagai bel terjadwal.

## 5. Mulai otomatis bersama Windows

1. Buka menu **Pengaturan** > centang **Mulai bersama Windows**.
2. Mulai sekarang, setiap kali Windows (pengguna ini) masuk, Nexus Bell
   langsung berjalan **diam-diam di baris tugas** (ikon lonceng di dekat jam).
3. Klik ikon lonceng untuk membuka jendela. Menutup jendela (X) **tidak**
   menghentikan bel. Untuk benar-benar keluar: klik kanan ikon > **Keluar**.

Supaya bel tidak terlewat: PC tidak akan tidur selama aplikasi berjalan.
Di Windows, atur juga **Pengaturan > Daya > Layar dan tidur** menjadi *Tidak
pernah* dan nyalakan login otomatis ke akun yang sama (atau pakai akun yang
selalu masuk). Matikan pembaruan Windows yang menyalakan ulang PC pada jam sekolah.

## Aturan keandalan

- **Jam dicek setiap detik.** Setiap lantai dimainkan di jalur sendiri, jadi
  suara yang panjang di satu lantai tidak menunda lantai lain maupun bel berikutnya
  (bel baru di lantai yang sama langsung menggantikan suara yang masih berjalan).
- **Tidak pernah berbunyi dua kali.** Bel yang sudah berbunyi dicatat, bahkan
  setelah aplikasi dimulai ulang.
- **Batas keterlambatan 30 detik.** Kalau aplikasi sempat macet, bel hanya
  dibunyikan bila terlambat kurang dari 30 detik. Lebih dari itu bel **dilewati**
  dan dicatat di log (`DILEWATI`).
- **Tidak crash.** File suara rusak atau perangkat hilang hanya menghasilkan
  catatan di log dan pita peringatan kuning; aplikasi terus berjalan.
- **Satu salinan saja.** Membuka aplikasi kedua ditolak, supaya bel tidak berbunyi dobel.

## Log

Folder `log` berisi `bell.log` (dan satu file per hari sebelumnya, 60 hari terakhir).
Setiap baris memuat jam, jenis (`PLAYED` berbunyi, `SKIPPED` dilewati, `FAILED` gagal),
nama lantai dan bel. Ringkasan terbaru juga tampil di halaman **Bell PC** di Nexus.
Menu **Pengaturan > Buka folder log** membuka folder ini.

## Jika ada masalah

| Gejala | Yang dilakukan |
|---|---|
| *Nexus menolak kunci koneksi* | Buat kunci baru di Nexus (Bell > Bell PC) dan isi lagi di **Koneksi**. |
| *Tidak bisa tersambung ke Nexus* | Cek internet PC. Bel tetap berbunyi dari jadwal tersimpan. |
| *Perangkat tidak ditemukan* | Cek kabel/driver kartu suara, lalu pilih ulang perangkatnya. |
| Suara terbalik lantai | Tukar pilihan perangkat antara Lantai 2 dan Lantai 3. |
| Bel tidak berbunyi | Cek: profil yang dipakai benar, baris **Aktif**, hari dan jamnya, bukan hari libur, dan perangkat siap. |

## Untuk pengembang

```
pip install -r requirements.txt
python main.py                       # menjalankan dari kode sumber
python -m unittest discover -s tests # tes (jam palsu, tanpa kartu suara)
```

Semua teks tampilan ada di `nexusbell/strings.py`. Logika bel (tanpa suara dan
jaringan) ada di `nexusbell/engine.py`.
