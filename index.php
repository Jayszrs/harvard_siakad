<?php
// index.php - Dashboard
require_once 'config.php';
$pageTitle = 'Beranda';

$db = getDB();

$totalMhs = $db->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn();
$totalMk  = $db->query("SELECT COUNT(*) FROM matakuliah")->fetchColumn();
$totalNil = $db->query("SELECT COUNT(*) FROM nilai")->fetchColumn();
$totalA   = $db->query("SELECT COUNT(*) FROM nilai WHERE HURUF='A'")->fetchColumn();

$recentMhs = $db->query("SELECT * FROM mahasiswa ORDER BY CREATED_AT DESC, NPM DESC LIMIT 5")->fetchAll();

$gradeDist = $db->query(
    "SELECT HURUF, COUNT(*) AS jml FROM nilai GROUP BY HURUF ORDER BY HURUF"
)->fetchAll();

$recentNilai = $db->query(
    "SELECT n.*, m.NAMA, mk.NAMAMK
     FROM nilai n
     JOIN mahasiswa m ON n.NPM = m.NPM
     JOIN matakuliah mk ON n.KDMK = mk.KDMK
     ORDER BY n.ID DESC LIMIT 5"
)->fetchAll();

require 'header.php';
?>

<div class="welcome-banner">
    <h2>Selamat Datang di <span class="wb-gold">Harvard University</span> SIAKAD</h2>
    <p>Sistem Informasi Akademik untuk mengelola data mahasiswa, katalog mata kuliah, nilai, dan rekap akademik dalam satu panel yang rapi.</p>
    <div class="welcome-accent">
        <div class="welcome-accent-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Tahun Akademik 2024/2025
        </div>
        <div class="welcome-accent-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8a16 16 0 0 0 6 6l.92-.92a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.73 16z"/></svg>
            Cambridge, MA 02138
        </div>
        <div class="welcome-accent-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            Berdiri sejak 1636
        </div>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card crimson">
        <div class="stat-card-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-label">Total Mahasiswa</div>
        <div class="stat-value" data-target="<?= $totalMhs ?>"><?= $totalMhs ?></div>
        <div class="stat-sub">Mahasiswa aktif terdaftar</div>
        <div class="stat-bg-letter">M</div>
    </div>
    <div class="stat-card navy">
        <div class="stat-card-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        </div>
        <div class="stat-label">Mata Kuliah</div>
        <div class="stat-value" data-target="<?= $totalMk ?>"><?= $totalMk ?></div>
        <div class="stat-sub">Katalog mata kuliah tersedia</div>
        <div class="stat-bg-letter">K</div>
    </div>
    <div class="stat-card gold">
        <div class="stat-card-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div class="stat-label">Total Nilai</div>
        <div class="stat-value" data-target="<?= $totalNil ?>"><?= $totalNil ?></div>
        <div class="stat-sub">Rekaman nilai akademik</div>
        <div class="stat-bg-letter">N</div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-label">Nilai A</div>
        <div class="stat-value" data-target="<?= $totalA ?>"><?= $totalA ?></div>
        <div class="stat-sub">Capaian nilai terbaik</div>
        <div class="stat-bg-letter">A</div>
    </div>
</div>

<div class="section-grid">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Mahasiswa Terbaru</div>
                <div class="table-card-sub">Lima data mahasiswa terakhir yang masuk sistem</div>
            </div>
            <a href="mahasiswa.php" class="btn btn-secondary" style="font-size:.78rem;padding:7px 14px;">
                Lihat Semua
            </a>
        </div>
        <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>NPM</th>
                    <th>Nama</th>
                    <th>Prodi</th>
                    <th>Alamat</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recentMhs): ?>
                <?php foreach ($recentMhs as $m): ?>
                <tr>
                    <td class="mono"><?= clean($m['NPM']) ?></td>
                    <td class="fw-600"><?= clean($m['NAMA']) ?></td>
                    <td>
                        <div class="fw-600" style="font-size:.78rem"><?= clean($m['PRODI'] ?? '-') ?></div>
                        <div class="text-muted" style="font-size:.68rem"><?= clean($m['FAKULTAS'] ?? '-') ?></div>
                    </td>
                    <td class="text-muted"><?= clean($m['ALAMAT']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr class="empty-row"><td colspan="4">Belum ada data mahasiswa.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Distribusi Nilai</div>
                <div class="table-card-sub">Rekap jumlah nilai berdasarkan huruf mutu</div>
            </div>
            <a href="nilai.php" class="btn btn-secondary" style="font-size:.78rem;padding:7px 14px;">
                Lihat Semua
            </a>
        </div>
        <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Huruf</th><th>Jumlah</th><th>Rentang</th><th>Keterangan</th></tr>
            </thead>
            <tbody>
            <?php
            $gradeInfo = [
                'A' => ['range'=>'> 90', 'desc'=>'Sangat Baik', 'class'=>'grade-a'],
                'B' => ['range'=>'71-90','desc'=>'Baik', 'class'=>'grade-b'],
                'C' => ['range'=>'61-70','desc'=>'Cukup', 'class'=>'grade-c'],
                'D' => ['range'=>'51-60','desc'=>'Kurang', 'class'=>'grade-d'],
                'E' => ['range'=>'<= 50', 'desc'=>'Tidak Lulus', 'class'=>'grade-e'],
            ];
            $distMap = [];
            foreach ($gradeDist as $g) $distMap[$g['HURUF']] = $g['jml'];

            foreach ($gradeInfo as $h => $info):
                $cnt = $distMap[$h] ?? 0;
            ?>
            <tr>
                <td><span class="grade-badge <?= $info['class'] ?>"><?= $h ?></span></td>
                <td class="fw-600"><?= $cnt ?></td>
                <td class="text-muted mono" style="font-size:.78rem"><?= $info['range'] ?></td>
                <td class="text-muted"><?= $info['desc'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="table-card mt-6">
    <div class="table-card-header">
        <div>
            <div class="table-card-title">Input Nilai Terbaru</div>
            <div class="table-card-sub">Lima rekaman nilai akademik terakhir</div>
        </div>
        <a href="nilai.php?action=add" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Input Nilai
        </a>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Mahasiswa</th>
                <th>Mata Kuliah</th>
                <th>Sem</th>
                <th>Tugas</th>
                <th>UTS</th>
                <th>UAS</th>
                <th>Rata-rata</th>
                <th>Huruf</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($recentNilai): ?>
            <?php foreach ($recentNilai as $n): ?>
            <tr>
                <td>
                    <div class="fw-600" style="font-size:.84rem"><?= clean($n['NAMA']) ?></div>
                    <div class="mono" style="font-size:.72rem;color:#aaa"><?= clean($n['NPM']) ?></div>
                </td>
                <td><?= clean($n['NAMAMK']) ?></td>
                <td class="text-center"><span class="badge badge-navy"><?= clean($n['SEMESTER']) ?></span></td>
                <td class="mono"><?= number_format($n['TUGAS'],1) ?></td>
                <td class="mono"><?= number_format($n['UTS'],1) ?></td>
                <td class="mono"><?= number_format($n['UAS'],1) ?></td>
                <td class="mono fw-600"><?= number_format($n['RATA'],2) ?></td>
                <td><span class="grade-badge <?= gradeClass($n['HURUF']) ?>"><?= clean($n['HURUF']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr class="empty-row"><td colspan="8">Belum ada data nilai.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require 'footer.php'; ?>
