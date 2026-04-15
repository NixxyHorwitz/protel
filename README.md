# 📡 ProTel Broadcast System — SaaS Edition

Sistem broadcast Telegram berbasis **SaaS (Software as a Service)** — Admin kelola bot via Web Panel, User kelola broadcast langsung dari bot Telegram-nya.

---

## 🏗️ Arsitektur

```
┌─────────────────────────────────────────────────────────┐
│  👑 SUPER ADMIN (Web Panel)                              │
│  http://localhost/protel/dashboard.php                   │
│  • Tambah / Hapus Token Bot Telegram                     │
│  • Pantau semua pengguna dan campaign secara global      │
└─────────────────────┬───────────────────────────────────┘
                      │ mendaftarkan webhook
                      ▼
┌─────────────────────────────────────────────────────────┐
│  🤖 BOT TELEGRAM (User Interface)                        │
│  Setiap user chat ke bot → data mereka TERISOLASI        │
│  • 📱 Login akun Telegram (OTP + 2FA)                   │
│  • 📋 Tambah/Import kontak (CSV)                         │
│  • 📢 Buat & kirim broadcast                             │
│  • 📊 Pantau riwayat campaign                            │
└─────────────────────────────────────────────────────────┘
```

---

## ⚙️ Persyaratan

- PHP 8.1+
- MySQL / MariaDB
- Composer
- Extension PHP: `curl`, `pdo_mysql`, `openssl`, `mbstring`

---

## 🚀 Cara Setup

### 1. Install Dependencies
```bash
composer install
```

### 2. Konfigurasi
Edit `config/app.php`:
```php
define('TG_API_ID',   '12345678');   // Dari my.telegram.org/apps
define('TG_API_HASH', 'abcdef...');  // Dari my.telegram.org/apps
define('APP_URL',     'https://domain-kamu.com/protel');
```

Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'protel_broadcast');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Import Database
```sql
-- Via phpMyAdmin / CLI:
mysql -u root protel_broadcast < database/schema.sql
```

Atau jalankan migration SaaS (jika upgrade dari versi lama):
```php
php migrate_saas.php
```

### 4. Akses Web Admin
- Buka: `http://localhost/protel/`
- Login: **admin** / **admin123**
- Ganti password via `gen_password.php`

### 5. Tambah Bot Telegram
1. Buat bot baru di **@BotFather** → `/newbot`
2. Salin **API Token**
3. Buka Web Admin → Tab **Kelola Bot** → **+ Tambah Bot**
4. Paste Token + isi APP_URL → Klik **Simpan & Set Webhook**
5. ✅ Bot langsung aktif dan siap digunakan

---

## 🤖 Cara Penggunaan Bot (Untuk User)

1. Cari dan start bot yang sudah didaftarkan Admin
2. Ketik `/start` → tampil Menu Utama
3. Alur penggunaan:
   - 📱 **Akun** → Login akun Telegram (OTP → verifikasi code → (2FA jika perlu))
   - 📋 **Kontak** → Ketik manual atau kirim file `.csv` ke bot
   - 📢 **Broadcast** → Tulis pesan → pilih target → pilih akun pengirim → delay → kirim!
   - 📊 **Riwayat** → Pantau progress campaign, pause/resume

---

## 🔧 Development Local (Tanpa Webhook)

Untuk testing di lokal (Windows/Laragon):
```bash
php polling.php "TOKEN_BOT_DARI_BOTFATHER"
```
> ⚠️ Jalankan terpisah dari web server. Ctrl+C untuk berhenti.

---

## 📁 Struktur File

```
protel/
├── api/handler.php          # API AJAX untuk Web Admin
├── assets/
│   ├── dashboard.css        # Styling Super Admin Panel
│   └── dashboard.js         # Logic Super Admin Panel
├── bot/
│   ├── BotHelper.php        # Helper functions (DB queries, text builders)
│   ├── Keyboards.php        # Inline keyboard builder
│   └── Conversations/
│       ├── AddAccountConversation.php   # Alur login OTP/2FA
│       ├── AddContactConversation.php   # Alur tambah kontak manual
│       └── BroadcastConversation.php    # Alur kirim broadcast
├── bot.php                  # Definisi semua handler bot
├── config/
│   ├── app.php              # ⚠️ WAJIB DIISI: API_ID, API_HASH, APP_URL
│   └── database.php         # ⚠️ WAJIB DIISI: kredensial DB
├── database/schema.sql      # Schema database lengkap
├── dashboard.php            # Halaman Super Admin Panel (web)
├── index.php                # Halaman login admin
├── polling.php              # Runner untuk development lokal
├── scripts/
│   ├── broadcast_worker.php # Background worker pengiriman pesan
│   ├── request_otp.php      # CLI: minta OTP ke Telegram
│   ├── verify_otp.php       # CLI: verifikasi kode OTP
│   └── verify_2fa.php       # CLI: verifikasi password 2FA
├── sessions/                # Folder session MadelineProto (gitignored)
├── storage/                 # Folder cache Nutgram (gitignored)
└── webhook.php              # Webhook handler (production)
```

---

## 🔐 Keamanan

- Folder `sessions/` dilindungi `.htaccess` (Deny from all)
- Password admin harus diganti setelah install via `gen_password.php`
- Token bot disimpan di database, tidak di config file
- Data tiap pengguna bot terisolasi via `owner_tg_id`

---

## ⚠️ Catatan Penting

- **Flood Limit**: Atur delay minimal **3-5 detik** antar pesan
- **Windows**: MadelineProto berjalan lebih lambat, gunakan Linux di production
- **Sessions**: Jangan pernah commit folder `sessions/` ke Git
- **TG_API_ID**: Satu API ID bisa dipakai untuk banyak nomor akun
