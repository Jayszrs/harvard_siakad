<?php
// ============================================================
// config.php - Database connection, schema migration, helpers
// Harvard University SIAKAD
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'harvard_siakad');

define('SITE_NAME', 'Harvard University');
define('SITE_SUB',  'Sistem Informasi Akademik');
define('SITE_LOGO', 'assets/harvard-mark.svg');

define('PROFILE_UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles');
define('PROFILE_UPLOAD_URL', 'uploads/profiles');
define('PROFILE_MAX_BYTES', 2 * 1024 * 1024);

define('SESSION_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions');

if (!is_dir(SESSION_DIR)) {
    mkdir(SESSION_DIR, 0775, true);
}
session_save_path(SESSION_DIR);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $serverDsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
            $server = new PDO($serverDsn, DB_USER, DB_PASS, $options);
            $server->exec(
                'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` ' .
                'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );

            $dbDsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dbDsn, DB_USER, DB_PASS, $options);
            ensureDatabaseSchema($pdo);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;color:#A51C30;background:#fff0f0;border:2px solid #A51C30;border-radius:8px;max-width:680px;margin:50px auto;">
                    <h2>Koneksi Database Bermasalah</h2>
                    <p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>
                    <p style="color:#555;font-size:14px;">Periksa DB_HOST, DB_USER, DB_PASS, dan DB_NAME di <strong>config.php</strong>.</p>
                 </div>');
        }
    }

    return $pdo;
}

function ensureDatabaseSchema(PDO $pdo): void {
    static $done = false;
    if ($done) return;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS mahasiswa (
            NPM VARCHAR(30) NOT NULL PRIMARY KEY,
            NAMA VARCHAR(80) NOT NULL,
            ALAMAT VARCHAR(160) NOT NULL,
            FAKULTAS VARCHAR(100) NOT NULL DEFAULT 'Fakultas Teknologi Informasi & Digital',
            PRODI VARCHAR(80) NOT NULL DEFAULT 'Teknik Informatika',
            FOTO_PROFILE VARCHAR(255) NULL,
            CREATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS matakuliah (
            KDMK CHAR(5) NOT NULL PRIMARY KEY,
            NAMAMK VARCHAR(80) NOT NULL,
            SKS INT NOT NULL,
            FAKULTAS VARCHAR(100) NOT NULL DEFAULT 'Fakultas Teknologi Informasi & Digital',
            PRODI VARCHAR(80) NOT NULL DEFAULT 'Teknik Informatika'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS nilai (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            NPM VARCHAR(30) NOT NULL,
            KDMK CHAR(5) NOT NULL,
            SEMESTER CHAR(2) NOT NULL,
            TUGAS DECIMAL(5,2) NOT NULL DEFAULT 0,
            UTS DECIMAL(5,2) NOT NULL DEFAULT 0,
            UAS DECIMAL(5,2) NOT NULL DEFAULT 0,
            RATA DECIMAL(5,2) GENERATED ALWAYS AS ((TUGAS + UTS + UAS) / 3) STORED,
            HURUF CHAR(1) NOT NULL DEFAULT 'E',
            UNIQUE KEY uk_npm_kdmk_semester (NPM, KDMK, SEMESTER)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    migrateExistingSchema($pdo);
    seedStarterData($pdo);
    translateStarterCourseData($pdo);
    ensureForeignKeys($pdo);

    $done = true;
}

function migrateExistingSchema(PDO $pdo): void {
    dropNilaiForeignKeys($pdo);

    try { $pdo->exec("ALTER TABLE mahasiswa MODIFY NPM VARCHAR(30) NOT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE nilai MODIFY NPM VARCHAR(30) NOT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE mahasiswa MODIFY NAMA VARCHAR(80) NOT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE mahasiswa MODIFY ALAMAT VARCHAR(160) NOT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE matakuliah MODIFY NAMAMK VARCHAR(80) NOT NULL"); } catch (PDOException $e) {}

    if (!columnExists($pdo, 'mahasiswa', 'FOTO_PROFILE')) {
        $pdo->exec("ALTER TABLE mahasiswa ADD FOTO_PROFILE VARCHAR(255) NULL AFTER ALAMAT");
    }

    if (!columnExists($pdo, 'mahasiswa', 'FAKULTAS')) {
        $pdo->exec("ALTER TABLE mahasiswa ADD FAKULTAS VARCHAR(100) NOT NULL DEFAULT 'Fakultas Teknologi Informasi & Digital' AFTER ALAMAT");
    }

    if (!columnExists($pdo, 'mahasiswa', 'PRODI')) {
        $pdo->exec("ALTER TABLE mahasiswa ADD PRODI VARCHAR(80) NOT NULL DEFAULT 'Teknik Informatika' AFTER FAKULTAS");
    }

    if (!columnExists($pdo, 'mahasiswa', 'CREATED_AT')) {
        $pdo->exec("ALTER TABLE mahasiswa ADD CREATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    if (!columnExists($pdo, 'matakuliah', 'FAKULTAS')) {
        $pdo->exec("ALTER TABLE matakuliah ADD FAKULTAS VARCHAR(100) NOT NULL DEFAULT 'Fakultas Teknologi Informasi & Digital' AFTER SKS");
    }

    if (!columnExists($pdo, 'matakuliah', 'PRODI')) {
        $pdo->exec("ALTER TABLE matakuliah ADD PRODI VARCHAR(80) NOT NULL DEFAULT 'Teknik Informatika' AFTER FAKULTAS");
    }
}

function dropNilaiForeignKeys(PDO $pdo): void {
    $stmt = $pdo->prepare(
        "SELECT CONSTRAINT_NAME
         FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = 'nilai'"
    );
    $stmt->execute([DB_NAME]);

    foreach ($stmt->fetchAll() as $row) {
        $constraint = str_replace('`', '', $row['CONSTRAINT_NAME']);
        try {
            $pdo->exec("ALTER TABLE nilai DROP FOREIGN KEY `$constraint`");
        } catch (PDOException $e) {}
    }
}

function ensureForeignKeys(PDO $pdo): void {
    if (!foreignKeyExists($pdo, 'nilai', 'fk_nilai_mahasiswa')) {
        try {
            $pdo->exec(
                "ALTER TABLE nilai
                 ADD CONSTRAINT fk_nilai_mahasiswa
                 FOREIGN KEY (NPM) REFERENCES mahasiswa(NPM)
                 ON UPDATE CASCADE ON DELETE CASCADE"
            );
        } catch (PDOException $e) {}
    }

    if (!foreignKeyExists($pdo, 'nilai', 'fk_nilai_matakuliah')) {
        try {
            $pdo->exec(
                "ALTER TABLE nilai
                 ADD CONSTRAINT fk_nilai_matakuliah
                 FOREIGN KEY (KDMK) REFERENCES matakuliah(KDMK)
                 ON UPDATE CASCADE ON DELETE CASCADE"
            );
        } catch (PDOException $e) {}
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([DB_NAME, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function foreignKeyExists(PDO $pdo, string $table, string $constraint): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?"
    );
    $stmt->execute([DB_NAME, $table, $constraint]);
    return (int)$stmt->fetchColumn() > 0;
}

function seedStarterData(PDO $pdo): void {
    $hasMahasiswa = (int)$pdo->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn() > 0;
    $hasMatakuliah = (int)$pdo->query("SELECT COUNT(*) FROM matakuliah")->fetchColumn() > 0;
    $hasNilai = (int)$pdo->query("SELECT COUNT(*) FROM nilai")->fetchColumn() > 0;

    if ($hasMahasiswa || $hasMatakuliah || $hasNilai) {
        return;
    }

    $pdo->exec(
        "INSERT IGNORE INTO mahasiswa (NPM, NAMA, ALAMAT, FAKULTAS, PRODI) VALUES
            ('20230001000001', 'John Alexander Smith', '123 Harvard Yard, Cambridge MA', 'Fakultas Teknologi Informasi & Digital', 'Teknik Informatika'),
            ('20230001000002', 'Emily Rose Johnson', '45 Oxford Street, Cambridge MA', 'Fakultas Teknologi Informasi & Digital', 'Sistem Informasi'),
            ('20230001000003', 'Michael David Brown', '89 Brattle Street, Cambridge MA', 'Fakultas Ilmu Pendidikan', 'PGSD'),
            ('20230001000004', 'Sarah Elizabeth Davis', '12 Massachusetts Ave, Cambridge MA', 'Fakultas Ilmu Pendidikan', 'PGPAUD'),
            ('20230001000005', 'James William Wilson', '67 Garden Street, Cambridge MA', 'Fakultas Teknologi Informasi & Digital', 'Komputer Akuntansi')"
    );

    $pdo->exec(
        "INSERT IGNORE INTO matakuliah (KDMK, NAMAMK, SKS, FAKULTAS, PRODI) VALUES
            ('CS101', 'Intro to CS', 3, 'Fakultas Teknologi Informasi & Digital', 'Teknik Informatika'),
            ('MA201', 'Calculus II', 4, 'Fakultas Teknologi Informasi & Digital', 'Teknik Informatika'),
            ('SI101', 'Sistem Informasi Manajemen', 3, 'Fakultas Teknologi Informasi & Digital', 'Sistem Informasi'),
            ('TK101', 'Organisasi Komputer', 3, 'Fakultas Teknologi Informasi & Digital', 'Teknik Komputer'),
            ('KA101', 'Akuntansi Komputer', 3, 'Fakultas Teknologi Informasi & Digital', 'Komputer Akuntansi'),
            ('SD101', 'Konsep Dasar Pendidikan SD', 3, 'Fakultas Ilmu Pendidikan', 'PGSD'),
            ('PA101', 'Perkembangan Anak Usia Dini', 3, 'Fakultas Ilmu Pendidikan', 'PGPAUD')"
    );

    $pdo->exec(
        "INSERT IGNORE INTO nilai (NPM, KDMK, SEMESTER, TUGAS, UTS, UAS, HURUF) VALUES
            ('20230001000001', 'CS101', '1', 85, 90, 88, 'A'),
            ('20230001000001', 'MA201', '1', 72, 75, 70, 'B'),
            ('20230001000002', 'SI101', '1', 60, 65, 62, 'C'),
            ('20230001000003', 'SD101', '2', 91, 93, 95, 'A'),
            ('20230001000004', 'PA101', '1', 50, 52, 55, 'D'),
            ('20230001000005', 'KA101', '2', 40, 35, 45, 'E')"
    );
}

function academicPrograms(): array {
    return [
        'Fakultas Teknologi Informasi & Digital' => [
            'Teknik Informatika',
            'Sistem Informasi',
            'Teknik Komputer',
            'Komputer Akuntansi',
        ],
        'Fakultas Ilmu Pendidikan' => [
            'PGSD',
            'PGPAUD',
        ],
    ];
}

function isValidFacultyProgram(string $fakultas, string $prodi): bool {
    $programs = academicPrograms();
    return isset($programs[$fakultas]) && in_array($prodi, $programs[$fakultas], true);
}

function translateStarterCourseData(PDO $pdo): void {
    $updates = [
        ['CS101', 'Intro to CS', 'Pengantar Ilmu Komputer'],
        ['MA201', 'Calculus II', 'Kalkulus II'],
        ['EN301', 'Academic Writing', 'Penulisan Akademik'],
        ['PH401', 'Philosophy 101', 'Filsafat 101'],
        ['EC101', 'Economics', 'Ekonomi'],
    ];

    $stmt = $pdo->prepare("UPDATE matakuliah SET NAMAMK = ? WHERE KDMK = ? AND NAMAMK = ?");
    foreach ($updates as [$kode, $lama, $baru]) {
        $stmt->execute([$baru, $kode, $lama]);
    }
}

function hitungHuruf(float $rata): string {
    if ($rata > 90) return 'A';
    if ($rata > 70) return 'B';
    if ($rata > 60) return 'C';
    if ($rata > 50) return 'D';
    return 'E';
}

function gradeClass(string $huruf): string {
    return match ($huruf) {
        'A' => 'grade-a',
        'B' => 'grade-b',
        'C' => 'grade-c',
        'D' => 'grade-d',
        default => 'grade-e',
    };
}

function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (!isset($_SESSION['flash'])) return null;

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function clean(mixed $value): string {
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

function isActive(string $page): string {
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}

function tanggalIndonesia(?int $timestamp = null): string {
    $timestamp ??= time();
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    return $hari[(int)date('w', $timestamp)] . ', ' .
        date('j', $timestamp) . ' ' .
        $bulan[(int)date('n', $timestamp)] . ' ' .
        date('Y', $timestamp);
}

function studentInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $first = $parts[0][0] ?? 'S';
    $last = count($parts) > 1 ? ($parts[count($parts) - 1][0] ?? '') : '';
    return strtoupper($first . $last);
}

function profilePhotoUrl(?string $path): string {
    if (!$path) return '';

    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    return is_file($fullPath) ? $path : '';
}

function removeProfilePhoto(?string $path): void {
    if (!$path) return;

    $base = realpath(PROFILE_UPLOAD_DIR);
    $full = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));

    if ($base && $full && str_starts_with($full, $base) && is_file($full)) {
        @unlink($full);
    }
}

function saveProfilePhoto(?array $file, ?string $currentPath = null): array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [$currentPath, null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [$currentPath, 'Upload foto gagal. Coba pilih file lain.'];
    }

    if (($file['size'] ?? 0) > PROFILE_MAX_BYTES) {
        return [$currentPath, 'Ukuran foto maksimal 2 MB.'];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return [$currentPath, 'Format foto harus JPG, PNG, WEBP, atau GIF.'];
    }

    if (!is_dir(PROFILE_UPLOAD_DIR)) {
        mkdir(PROFILE_UPLOAD_DIR, 0775, true);
    }

    $name = 'profile_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    $dest = PROFILE_UPLOAD_DIR . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return [$currentPath, 'Foto tidak bisa disimpan ke folder uploads/profiles.'];
    }

    removeProfilePhoto($currentPath);
    return [PROFILE_UPLOAD_URL . '/' . $name, null];
}
