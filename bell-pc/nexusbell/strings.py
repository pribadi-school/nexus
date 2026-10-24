"""Semua teks yang tampil di aplikasi ada di sini, supaya mudah diubah.
(All user-facing text lives here.)"""

APP_TITLE = "Nexus Bell - Bel Sekolah"
TRAY_TITLE = "Nexus Bell"

DAYS_SHORT = {1: "Sen", 2: "Sel", 3: "Rab", 4: "Kam", 5: "Jum", 6: "Sab", 7: "Min"}
DAYS_LONG = {1: "Senin", 2: "Selasa", 3: "Rabu", 4: "Kamis", 5: "Jumat", 6: "Sabtu", 7: "Minggu"}
MONTHS = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus",
          "September", "Oktober", "November", "Desember"]
EVERY_DAY = "Setiap hari"

# Panel setiap area
ZONE_PROFILE = "Profil"
ZONE_VOLUME = "Volume"
ZONE_DEVICE = "Perangkat suara"
ZONE_NO_DEVICE = "(pilih perangkat)"
ZONE_TEST_DEVICE = "Tes perangkat"
ZONE_RING_NOW = "Bunyikan sekarang"
ZONE_TEST_ROW = "Tes bel terpilih"
ZONE_NEXT = "Bel berikutnya"
ZONE_NEXT_NONE = "tidak ada"
ZONE_NO_SCHEDULE = "Belum ada jadwal. Atur jadwal di website Nexus lalu tekan Sambungkan."
ZONE_DEVICE_OK = "Perangkat siap"
ZONE_DEVICE_MISSING = "Perangkat tidak ditemukan"
ZONE_DEVICE_UNSET = "Perangkat belum dipilih"
ZONE_HOLIDAY_TODAY = "Hari ini libur"

COLUMNS = {"time": "Waktu", "days": "Hari", "label": "Label", "sound": "Suara", "on": "Aktif"}
SHARED_FROM = "dari %s"

# Koneksi ke Nexus
CONNECTION = "Koneksi"
EDIT_IN_NEXUS = "Edit di Nexus"
CONNECTION_TITLE = "Koneksi ke Nexus"
SERVER_ADDRESS = "Alamat Nexus"
CONNECTION_KEY = "Kunci koneksi"
SAVE_CONNECT = "Simpan & sambungkan"
SYNC_NOW = "Sambungkan sekarang"
KEY_HELP = ("Buat kunci di website Nexus: menu Bell > Bell PC > Buat kunci koneksi. "
            "Salin alamat Nexus dan kuncinya ke sini.")
SYNC_OK = "Tersambung ke Nexus. Jadwal terbaru: %s"
SYNC_NEVER = "Belum tersambung ke Nexus. Memakai jadwal yang tersimpan."
SYNC_NOT_SET = "Koneksi Nexus belum diatur (menu Koneksi). Memakai jadwal yang tersimpan."
SYNC_FAILED = "Tidak bisa tersambung ke Nexus (%s). Bel tetap berbunyi sesuai jadwal terakhir."
SYNC_REJECTED = "Nexus menolak kunci koneksi ini. Buat kunci baru di website Nexus."
SYNC_PROBLEMS = "Sebagian jadwal tidak terbaca: %s"

# Menu
MENU_OPTIONS = "Pengaturan"
MENU_AUTOSTART = "Mulai bersama Windows"
MENU_NIGHT = "Mode malam"
MENU_OPEN_LOG = "Buka folder log"
MENU_QUIT = "Keluar"
MENU_SHOW = "Buka jendela"
TRAY_HIDE_HINT = "Nexus Bell tetap berjalan di baris tugas (system tray)."

# Log
LOG_TITLE = "Catatan hari ini"
LOG_PLAYED = "%s: %s dibunyikan (%s)"
LOG_SKIPPED = "%s: %s"
LOG_FAILED = "%s: GAGAL membunyikan %s: %s"
LOG_CUT = "%s: bel sebelumnya dihentikan karena bel baru dimulai"
LOG_FALLBACK = "%s: file suara belum diunduh, memakai suara bawaan"
LOG_TEST = "%s: tes %s"
LOG_MANUAL = "%s: dibunyikan manual (%s)"

# Banner kesalahan
BANNER_DEVICE_MISSING = "%s: perangkat suara \"%s\" tidak ditemukan. Area lain tetap berjalan."
BANNER_PLAY_FAILED = "%s: tidak bisa memutar suara (%s)"
BANNER_AUDIO_LIB = "Pustaka suara tidak bisa dimuat (%s). Bel tidak akan berbunyi."
BANNER_ALREADY_RUNNING = "Nexus Bell sudah berjalan di PC ini."

BUILTIN_NAMES = {"bel-sekolah": "Bel sekolah", "denting": "Denting", "peringatan": "Peringatan"}
SOUND_MISSING = "(suara tidak ada)"
