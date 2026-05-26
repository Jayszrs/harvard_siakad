# 🎓 Harvard University SIAKAD
**Sistem Informasi Akademik — Academic Information System**

---

## 📁 Struktur File

```
harvard_siakad/
├── config.php          ← Konfigurasi database & helper functions
├── index.php           ← Dashboard utama
├── mahasiswa.php       ← CRUD Data Mahasiswa
├── matakuliah.php      ← CRUD Data Mata Kuliah
├── nilai.php           ← CRUD Data Nilai (dengan kalkulasi otomatis)
├── header.php          ← Template header (shared)
├── footer.php          ← Template footer (shared)
├── style.css           ← Stylesheet utama (Harvard crimson theme)
├── script.js           ← JavaScript (search, modal, kalkulasi)
└── database.sql        ← Schema & sample data MySQL
```

---

## ⚙️ Cara Instalasi

### Prasyarat
- PHP 8.0+
- MySQL 8.0+ atau MariaDB 10.5+
- Web server: Apache/Nginx (XAMPP/WAMP/Laragon)
- PDO extension aktif

### Langkah-langkah

**1. Copy file ke htdocs/www**
```
Salin folder `harvard_siakad/` ke:
XAMPP  → C:/xampp/htdocs/harvard_siakad/
WAMP   → C:/wamp64/www/harvard_siakad/
Linux  → /var/www/html/harvard_siakad/
```

**2. Import database**
```sql
-- Buka phpMyAdmin atau MySQL CLI, lalu jalankan:
source /path/to/harvard_siakad/database.sql

-- Atau import via phpMyAdmin:
-- Database → New → Nama: harvard_siakad → Import → database.sql
```

**3. Edit konfigurasi database**
Buka `config.php` dan sesuaikan:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // username MySQL Anda
define('DB_PASS', '');           // password MySQL Anda
define('DB_NAME', 'harvard_siakad');
```

**4. Buka di browser**
```
http://localhost/harvard_siakad/
```

---

## 🗂️ Fitur Sistem

### 📊 Dashboard
- Statistik total mahasiswa, mata kuliah, nilai, dan grade A
- Tabel distribusi grade (A–E)
- 5 data mahasiswa & nilai terbaru

### 👨‍🎓 Manajemen Mahasiswa
- Tambah, Edit, Hapus data mahasiswa
- Field: NPM (14 char), Nama (30 char), Alamat (40 char)
- Validasi format & duplikasi NPM
- Cek jumlah mata kuliah yang diambil

### 📚 Manajemen Mata Kuliah
- Tambah, Edit, Hapus mata kuliah
- Field: Kode MK (5 char), Nama MK (20 char), SKS (1–8)
- Referensi panduan SKS

### 📈 Manajemen Nilai
- Input, Edit, Hapus nilai
- **Kalkulasi otomatis** (real-time di browser):
  - RATA-RATA = (Tugas + UTS + UAS) / 3
  - HURUF ditentukan otomatis:
    - A = RATA > 90
    - B = RATA > 70
    - C = RATA > 60
    - D = RATA > 50
    - E = selain itu
- Cegah duplikasi (1 mahasiswa, 1 MK, 1 semester = 1 nilai)
- Tampilan lengkap dengan nama mahasiswa & mata kuliah

---

## 🎨 Teknologi

| Teknologi | Kegunaan |
|-----------|---------|
| PHP 8+    | Backend logic & template |
| MySQL/PDO | Database |
| HTML5     | Markup |
| CSS3      | Styling (custom properties, grid, flexbox) |
| JavaScript (Vanilla) | Live search, modal, kalkulasi |
| Google Fonts | Playfair Display, Source Serif 4, JetBrains Mono |

---

## 🔒 Catatan Keamanan

- Input disanitasi dengan `htmlspecialchars()` 
- Query menggunakan PDO Prepared Statements
- Flash messages via `$_SESSION`

Untuk produksi, tambahkan:
- Sistem login/autentikasi
- CSRF token pada form
- Rate limiting

---

**© Harvard University SIAKAD · Cambridge, Massachusetts**
