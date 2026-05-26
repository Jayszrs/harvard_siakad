-- ============================================================
-- HARVARD UNIVERSITY - SIAKAD DATABASE
-- ============================================================

CREATE DATABASE IF NOT EXISTS harvard_siakad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE harvard_siakad;

CREATE TABLE IF NOT EXISTS mahasiswa (
    NPM           VARCHAR(30)  NOT NULL PRIMARY KEY,
    NAMA          VARCHAR(80)  NOT NULL,
    ALAMAT        VARCHAR(160) NOT NULL,
    FAKULTAS      VARCHAR(100) NOT NULL DEFAULT 'Fakultas Teknologi Informasi & Digital',
    PRODI         VARCHAR(80)  NOT NULL DEFAULT 'Teknik Informatika',
    FOTO_PROFILE  VARCHAR(255) NULL,
    CREATED_AT    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS matakuliah (
    KDMK    CHAR(5)     NOT NULL PRIMARY KEY,
    NAMAMK  VARCHAR(80) NOT NULL,
    SKS     INT         NOT NULL,
    FAKULTAS VARCHAR(100) NOT NULL DEFAULT 'Fakultas Teknologi Informasi & Digital',
    PRODI    VARCHAR(80)  NOT NULL DEFAULT 'Teknik Informatika'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nilai (
    ID          INT AUTO_INCREMENT PRIMARY KEY,
    NPM         VARCHAR(30)  NOT NULL,
    KDMK        CHAR(5)      NOT NULL,
    SEMESTER    CHAR(2)      NOT NULL,
    TUGAS       DECIMAL(5,2) NOT NULL DEFAULT 0,
    UTS         DECIMAL(5,2) NOT NULL DEFAULT 0,
    UAS         DECIMAL(5,2) NOT NULL DEFAULT 0,
    RATA        DECIMAL(5,2) GENERATED ALWAYS AS ((TUGAS + UTS + UAS) / 3) STORED,
    HURUF       CHAR(1)      NOT NULL DEFAULT 'E',
    CONSTRAINT fk_nilai_mahasiswa FOREIGN KEY (NPM)
        REFERENCES mahasiswa(NPM) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_nilai_matakuliah FOREIGN KEY (KDMK)
        REFERENCES matakuliah(KDMK) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uk_npm_kdmk_semester (NPM, KDMK, SEMESTER)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mahasiswa (NPM, NAMA, ALAMAT, FAKULTAS, PRODI) VALUES
('20230001000001', 'John Alexander Smith', '123 Harvard Yard, Cambridge MA', 'Fakultas Teknologi Informasi & Digital', 'Teknik Informatika'),
('20230001000002', 'Emily Rose Johnson', '45 Oxford Street, Cambridge MA', 'Fakultas Teknologi Informasi & Digital', 'Sistem Informasi'),
('20230001000003', 'Michael David Brown', '89 Brattle Street, Cambridge MA', 'Fakultas Ilmu Pendidikan', 'PGSD'),
('20230001000004', 'Sarah Elizabeth Davis', '12 Massachusetts Ave, Cambridge MA', 'Fakultas Ilmu Pendidikan', 'PGPAUD'),
('20230001000005', 'James William Wilson', '67 Garden Street, Cambridge MA', 'Fakultas Teknologi Informasi & Digital', 'Komputer Akuntansi')
ON DUPLICATE KEY UPDATE NAMA = VALUES(NAMA), ALAMAT = VALUES(ALAMAT), FAKULTAS = VALUES(FAKULTAS), PRODI = VALUES(PRODI);

INSERT INTO matakuliah (KDMK, NAMAMK, SKS, FAKULTAS, PRODI) VALUES
('CS101', 'Pengantar Ilmu Komputer', 3, 'Fakultas Teknologi Informasi & Digital', 'Teknik Informatika'),
('MA201', 'Kalkulus II', 4, 'Fakultas Teknologi Informasi & Digital', 'Teknik Informatika'),
('SI101', 'Sistem Informasi Manajemen', 3, 'Fakultas Teknologi Informasi & Digital', 'Sistem Informasi'),
('TK101', 'Organisasi Komputer', 3, 'Fakultas Teknologi Informasi & Digital', 'Teknik Komputer'),
('KA101', 'Akuntansi Komputer', 3, 'Fakultas Teknologi Informasi & Digital', 'Komputer Akuntansi'),
('SD101', 'Konsep Dasar Pendidikan SD', 3, 'Fakultas Ilmu Pendidikan', 'PGSD'),
('PA101', 'Perkembangan Anak Usia Dini', 3, 'Fakultas Ilmu Pendidikan', 'PGPAUD')
ON DUPLICATE KEY UPDATE NAMAMK = VALUES(NAMAMK), SKS = VALUES(SKS), FAKULTAS = VALUES(FAKULTAS), PRODI = VALUES(PRODI);

INSERT INTO nilai (NPM, KDMK, SEMESTER, TUGAS, UTS, UAS, HURUF) VALUES
('20230001000001', 'CS101', '1', 85, 90, 88, 'A'),
('20230001000001', 'MA201', '1', 72, 75, 70, 'B'),
('20230001000002', 'SI101', '1', 60, 65, 62, 'C'),
('20230001000003', 'SD101', '2', 91, 93, 95, 'A'),
('20230001000004', 'PA101', '1', 50, 52, 55, 'D'),
('20230001000005', 'KA101', '2', 40, 35, 45, 'E')
ON DUPLICATE KEY UPDATE
    TUGAS = VALUES(TUGAS),
    UTS = VALUES(UTS),
    UAS = VALUES(UAS),
    HURUF = VALUES(HURUF);
