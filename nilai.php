<?php
// nilai.php - CRUD Nilai dengan kalkulasi otomatis
require_once 'config.php';
$pageTitle = 'Nilai';
$db = getDB();

$action = $_GET['action'] ?? 'list';
$errors = [];
$old = [];

$mahasiswaList = $db->query("SELECT NPM, NAMA, FAKULTAS, PRODI FROM mahasiswa ORDER BY NAMA")->fetchAll();
$matakuliahList = $db->query("SELECT KDMK, NAMAMK, SKS, FAKULTAS, PRODI FROM matakuliah ORDER BY FAKULTAS, PRODI, NAMAMK")->fetchAll();
$semesterOptions = [
    '1' => 'Semester 1',
    '2' => 'Semester 2',
    '3' => 'Semester 3',
    '4' => 'Semester 4',
    '5' => 'Semester 5',
    '6' => 'Semester 6',
    '7' => 'Semester 7',
    '8' => 'Semester 8',
];

if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db->prepare("DELETE FROM nilai WHERE ID = ?")->execute([$id]);
    setFlash('success', 'Data nilai berhasil dihapus.');
    header('Location: nilai.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $npm = trim($_POST['NPM'] ?? '');
    $kdmk = strtoupper(trim($_POST['KDMK'] ?? ''));
    $semester = trim($_POST['SEMESTER'] ?? '');
    $tugas = (float)($_POST['TUGAS'] ?? 0);
    $uts = (float)($_POST['UTS'] ?? 0);
    $uas = (float)($_POST['UAS'] ?? 0);
    $id = (int)($_POST['id'] ?? 0);
    $old = [
        'ID' => $id,
        'NPM' => $npm,
        'KDMK' => $kdmk,
        'SEMESTER' => $semester,
        'TUGAS' => $tugas,
        'UTS' => $uts,
        'UAS' => $uas,
    ];

    $rata = ($tugas + $uts + $uas) / 3;
    $huruf = hitungHuruf($rata);
    $old['HURUF'] = $huruf;

    $studentMap = array_column($mahasiswaList, null, 'NPM');
    $courseMap = array_column($matakuliahList, null, 'KDMK');
    $selectedStudent = $studentMap[$npm] ?? null;
    $selectedCourse = $courseMap[$kdmk] ?? null;

    if ($npm === '') {
        $errors['NPM'] = 'Pilih mahasiswa terlebih dahulu.';
    } elseif (!$selectedStudent) {
        $errors['NPM'] = 'Mahasiswa tidak ditemukan.';
    }
    if ($kdmk === '') {
        $errors['KDMK'] = 'Pilih mata kuliah terlebih dahulu.';
    } elseif (!$selectedCourse) {
        $errors['KDMK'] = 'Mata kuliah tidak ditemukan.';
    }
    if ($selectedStudent && $selectedCourse && (
        $selectedStudent['FAKULTAS'] !== $selectedCourse['FAKULTAS'] ||
        $selectedStudent['PRODI'] !== $selectedCourse['PRODI']
    )) {
        $errors['KDMK'] = 'Mata kuliah harus sesuai dengan fakultas dan prodi mahasiswa.';
    }
    if ($semester === '') {
        $errors['SEMESTER'] = 'Pilih semester terlebih dahulu.';
    }
    if ($tugas < 0 || $tugas > 100) {
        $errors['TUGAS'] = 'Nilai harus berada di rentang 0 sampai 100.';
    }
    if ($uts < 0 || $uts > 100) {
        $errors['UTS'] = 'Nilai harus berada di rentang 0 sampai 100.';
    }
    if ($uas < 0 || $uas > 100) {
        $errors['UAS'] = 'Nilai harus berada di rentang 0 sampai 100.';
    }

    if (empty($errors)) {
        try {
            if ($action === 'add') {
                $ck = $db->prepare("SELECT COUNT(*) FROM nilai WHERE NPM = ? AND KDMK = ? AND SEMESTER = ?");
                $ck->execute([$npm, $kdmk, $semester]);

                if ((int)$ck->fetchColumn() > 0) {
                    $errors['_db'] = 'Nilai untuk mahasiswa, mata kuliah, dan semester ini sudah tersedia.';
                } else {
                    $st = $db->prepare(
                        "INSERT INTO nilai (NPM, KDMK, SEMESTER, TUGAS, UTS, UAS, HURUF)
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );
                    $st->execute([$npm, $kdmk, $semester, $tugas, $uts, $uas, $huruf]);
                    setFlash('success', 'Nilai berhasil disimpan. Huruf akhir: ' . $huruf . ' (Rata-rata: ' . number_format($rata, 2) . ')');
                    header('Location: nilai.php');
                    exit;
                }
            } elseif ($action === 'edit') {
                $ck = $db->prepare("SELECT COUNT(*) FROM nilai WHERE NPM = ? AND KDMK = ? AND SEMESTER = ? AND ID <> ?");
                $ck->execute([$npm, $kdmk, $semester, $id]);

                if ((int)$ck->fetchColumn() > 0) {
                    $errors['_db'] = 'Nilai untuk mahasiswa, mata kuliah, dan semester ini sudah tersedia.';
                }

                if (!$errors) {
                $st = $db->prepare(
                    "UPDATE nilai
                     SET NPM = ?, KDMK = ?, SEMESTER = ?, TUGAS = ?, UTS = ?, UAS = ?, HURUF = ?
                     WHERE ID = ?"
                );
                $st->execute([$npm, $kdmk, $semester, $tugas, $uts, $uas, $huruf, $id]);
                setFlash('success', 'Nilai berhasil diperbarui. Huruf akhir: ' . $huruf . ' (Rata-rata: ' . number_format($rata, 2) . ')');
                header('Location: nilai.php');
                exit;
                }
            }
        } catch (PDOException $e) {
            $errors['_db'] = 'Kesalahan database: ' . $e->getMessage();
        }
    }

    if ($errors) {
        $action = $action === 'edit' ? 'edit' : 'add';
    }
}

$editRow = null;
if ($action === 'edit' && isset($_GET['id']) && empty($old)) {
    $st = $db->prepare("SELECT * FROM nilai WHERE ID = ?");
    $st->execute([(int)$_GET['id']]);
    $editRow = $st->fetch();

    if (!$editRow) {
        setFlash('error', 'Data nilai tidak ditemukan.');
        header('Location: nilai.php');
        exit;
    }

    $old = $editRow;
}

$filterNPM = $_GET['npm'] ?? '';
$listQuery = "SELECT n.*, m.NAMA, m.FAKULTAS AS MHS_FAKULTAS, m.PRODI AS MHS_PRODI,
                     mk.NAMAMK, mk.SKS, mk.FAKULTAS AS MK_FAKULTAS, mk.PRODI AS MK_PRODI
              FROM nilai n
              JOIN mahasiswa m ON n.NPM = m.NPM
              JOIN matakuliah mk ON n.KDMK = mk.KDMK";
if ($filterNPM) {
    $listQuery .= " WHERE n.NPM = " . $db->quote($filterNPM);
}
$listQuery .= " ORDER BY m.NAMA, n.SEMESTER, mk.NAMAMK";
$list = $db->query($listQuery)->fetchAll();

require 'header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Data Nilai</h1>
        <p class="page-subtitle">
            <?php if ($filterNPM): ?>
                Menampilkan nilai untuk NPM: <?= clean($filterNPM) ?> -
                <a href="nilai.php" style="color:var(--crimson)">Tampilkan Semua</a>
            <?php else: ?>
                Kelola nilai tugas, UTS, UAS, rata-rata, dan huruf mutu mahasiswa.
            <?php endif; ?>
        </p>
        <div class="page-accent-line"></div>
    </div>
    <?php if ($action === 'list'): ?>
    <a href="nilai.php?action=add" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Input Nilai
    </a>
    <?php else: ?>
    <a href="nilai.php" class="btn btn-secondary">Kembali ke Daftar</a>
    <?php endif; ?>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="form-card">
    <div class="form-card-header">
        <div class="form-card-header-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div>
            <h2><?= $action === 'add' ? 'Input Nilai Mahasiswa' : 'Edit Nilai Mahasiswa' ?></h2>
            <p>Huruf mutu dihitung otomatis dari rata-rata nilai tugas, UTS, dan UAS.</p>
        </div>
    </div>

    <?php if (!empty($errors['_db'])): ?>
    <div class="flash flash-error" style="margin:16px 28px 0;border-radius:8px;">! <?= clean($errors['_db']) ?></div>
    <?php endif; ?>

    <form method="POST" action="nilai.php?action=<?= clean($action) ?>" novalidate>
        <?php if ($action === 'edit'): ?>
        <input type="hidden" name="id" value="<?= (int)($old['ID'] ?? 0) ?>">
        <?php endif; ?>
        <input type="hidden" name="HURUF" id="HURUF" value="<?= clean($old['HURUF'] ?? 'E') ?>">

        <div class="form-body">
            <div class="form-grid cols-3" style="margin-bottom:20px;">
                <div class="form-group">
                    <label for="NPM">Mahasiswa <span class="label-req">*</span></label>
                    <select id="NPM" name="NPM"
                            class="form-control <?= isset($errors['NPM']) ? 'error' : '' ?>">
                        <option value="">-- Pilih Mahasiswa --</option>
                        <?php foreach ($mahasiswaList as $mhs): ?>
                        <option value="<?= clean($mhs['NPM']) ?>"
                            data-fakultas="<?= clean($mhs['FAKULTAS']) ?>"
                            data-prodi="<?= clean($mhs['PRODI']) ?>"
                            <?= ($old['NPM'] ?? '') === $mhs['NPM'] ? 'selected' : '' ?>>
                            <?= clean($mhs['NAMA']) ?> - <?= clean($mhs['PRODI']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint <?= isset($errors['NPM']) ? 'error' : '' ?>">
                        <?= $errors['NPM'] ?? 'Pilih mahasiswa dari daftar' ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="KDMK">Mata Kuliah <span class="label-req">*</span></label>
                    <select id="KDMK" name="KDMK"
                            class="form-control <?= isset($errors['KDMK']) ? 'error' : '' ?>">
                        <option value="">-- Pilih Mata Kuliah --</option>
                        <?php foreach ($matakuliahList as $mk): ?>
                        <option value="<?= clean($mk['KDMK']) ?>"
                            data-fakultas="<?= clean($mk['FAKULTAS']) ?>"
                            data-prodi="<?= clean($mk['PRODI']) ?>"
                            <?= ($old['KDMK'] ?? '') === $mk['KDMK'] ? 'selected' : '' ?>>
                            <?= clean($mk['KDMK']) ?> - <?= clean($mk['NAMAMK']) ?> (<?= clean($mk['PRODI']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint <?= isset($errors['KDMK']) ? 'error' : '' ?>">
                        <?= $errors['KDMK'] ?? 'Daftar akan mengikuti prodi mahasiswa' ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="SEMESTER">Semester <span class="label-req">*</span></label>
                    <select id="SEMESTER" name="SEMESTER"
                            class="form-control <?= isset($errors['SEMESTER']) ? 'error' : '' ?>">
                        <option value="">-- Pilih Semester --</option>
                        <?php foreach ($semesterOptions as $val => $lbl): ?>
                        <option value="<?= $val ?>"
                            <?= ($old['SEMESTER'] ?? '') === (string)$val ? 'selected' : '' ?>>
                            <?= $lbl ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint <?= isset($errors['SEMESTER']) ? 'error' : '' ?>">
                        <?= $errors['SEMESTER'] ?? 'Semester aktif mahasiswa' ?>
                    </span>
                </div>
            </div>

            <div class="form-grid cols-3" style="margin-bottom:24px;">
                <div class="form-group">
                    <label for="TUGAS">Nilai Tugas <span class="label-req">*</span></label>
                    <input type="number" id="TUGAS" name="TUGAS"
                           min="0" max="100" step="0.01"
                           class="form-control <?= isset($errors['TUGAS']) ? 'error' : '' ?>"
                           value="<?= $old['TUGAS'] ?? '' ?>"
                           placeholder="0 - 100">
                    <span class="form-hint <?= isset($errors['TUGAS']) ? 'error' : '' ?>">
                        <?= $errors['TUGAS'] ?? 'Nilai tugas 0 sampai 100' ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="UTS">Nilai UTS <span class="label-req">*</span></label>
                    <input type="number" id="UTS" name="UTS"
                           min="0" max="100" step="0.01"
                           class="form-control <?= isset($errors['UTS']) ? 'error' : '' ?>"
                           value="<?= $old['UTS'] ?? '' ?>"
                           placeholder="0 - 100">
                    <span class="form-hint <?= isset($errors['UTS']) ? 'error' : '' ?>">
                        <?= $errors['UTS'] ?? 'Ujian Tengah Semester 0 sampai 100' ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="UAS">Nilai UAS <span class="label-req">*</span></label>
                    <input type="number" id="UAS" name="UAS"
                           min="0" max="100" step="0.01"
                           class="form-control <?= isset($errors['UAS']) ? 'error' : '' ?>"
                           value="<?= $old['UAS'] ?? '' ?>"
                           placeholder="0 - 100">
                    <span class="form-hint <?= isset($errors['UAS']) ? 'error' : '' ?>">
                        <?= $errors['UAS'] ?? 'Ujian Akhir Semester 0 sampai 100' ?>
                    </span>
                </div>
            </div>

            <div class="rata-display">
                <div>
                    <div class="rata-label">Rata-rata</div>
                    <div class="rata-val" id="displayRata">
                        <?php
                        if (!empty($old['TUGAS']) || !empty($old['UTS']) || !empty($old['UAS'])) {
                            $r = ((float)($old['TUGAS'] ?? 0) + (float)($old['UTS'] ?? 0) + (float)($old['UAS'] ?? 0)) / 3;
                            echo number_format($r, 2);
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>
                <div style="width:1px;height:48px;background:#ddd;"></div>
                <div>
                    <div class="rata-label">Huruf Akhir</div>
                    <div class="huruf-val <?= gradeClass($old['HURUF'] ?? 'E') ?>" id="displayHuruf">
                        <?= clean($old['HURUF'] ?? '-') ?>
                    </div>
                </div>
                <div style="margin-left:auto;text-align:right;">
                    <div class="rata-label">Rumus</div>
                    <div style="font-family:var(--font-mono);font-size:.8rem;color:#666;margin-top:4px;">
                        (Tugas + UTS + UAS) / 3
                    </div>
                    <div style="font-size:.72rem;color:#aaa;margin-top:6px;line-height:1.8;">
                        A &gt;90 - B 71-90 - C 61-70 - D 51-60 - E &le;50
                    </div>
                </div>
            </div>
        </div>

        <div class="form-footer">
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <?= $action === 'add' ? 'Simpan Nilai' : 'Update Nilai' ?>
            </button>
            <a href="nilai.php" class="btn btn-secondary">Batal</a>
            <span class="text-muted" style="margin-left:auto;font-size:.78rem;">
                Huruf mutu dihitung otomatis oleh sistem
            </span>
        </div>
    </form>
</div>

<?php else: ?>
<div class="table-card">
    <div class="table-card-header">
        <div>
            <div class="table-card-title">Daftar Nilai</div>
            <div class="table-card-sub">Total <?= count($list) ?> rekaman nilai</div>
        </div>
        <div class="table-card-actions">
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchNilai" placeholder="Cari nama atau mata kuliah...">
            </div>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table" id="tblNilai">
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Mahasiswa</th>
                <th>Mata Kuliah</th>
                <th>Prodi</th>
                <th class="text-center">Sem</th>
                <th class="text-center">SKS</th>
                <th class="text-center">Tugas</th>
                <th class="text-center">UTS</th>
                <th class="text-center">UAS</th>
                <th class="text-center">Rata-rata</th>
                <th class="text-center">Huruf</th>
                <th class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($list): ?>
            <?php foreach ($list as $i => $n): ?>
            <?php
            $deleteUrl = 'nilai.php?action=delete&id=' . (int)$n['ID'];
            $deleteLabel = $n['NAMA'] . ' - ' . $n['NAMAMK'];
            ?>
            <tr>
                <td class="row-no text-center"><?= $i + 1 ?></td>
                <td>
                    <div class="fw-600" style="font-size:.84rem"><?= clean($n['NAMA']) ?></div>
                    <div class="mono" style="font-size:.7rem;color:#aaa"><?= clean($n['NPM']) ?></div>
                </td>
                <td>
                    <div style="font-weight:500"><?= clean($n['NAMAMK']) ?></div>
                    <div class="mono" style="font-size:.7rem;color:#aaa"><?= clean($n['KDMK']) ?></div>
                </td>
                <td>
                    <div class="fw-600" style="font-size:.8rem"><?= clean($n['MHS_PRODI']) ?></div>
                    <div class="text-muted" style="font-size:.7rem"><?= clean($n['MHS_FAKULTAS']) ?></div>
                </td>
                <td class="text-center"><span class="badge badge-navy">Sem <?= clean($n['SEMESTER']) ?></span></td>
                <td class="text-center"><span class="badge badge-sks"><?= (int)$n['SKS'] ?> SKS</span></td>
                <td class="text-center mono"><?= number_format($n['TUGAS'], 1) ?></td>
                <td class="text-center mono"><?= number_format($n['UTS'], 1) ?></td>
                <td class="text-center mono"><?= number_format($n['UAS'], 1) ?></td>
                <td class="text-center">
                    <span style="font-family:var(--font-mono);font-weight:700;color:var(--navy)">
                        <?= number_format($n['RATA'], 2) ?>
                    </span>
                </td>
                <td class="text-center">
                    <span class="grade-badge <?= gradeClass($n['HURUF']) ?>"><?= clean($n['HURUF']) ?></span>
                </td>
                <td>
                    <div class="action-group" style="justify-content:center">
                        <a href="nilai.php?action=edit&id=<?= (int)$n['ID'] ?>"
                           class="btn-icon edit" title="Edit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <a href="<?= clean($deleteUrl) ?>"
                           class="btn-icon del"
                           title="Hapus"
                           onclick="return confirm('Yakin hapus data nilai ini?')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr class="empty-row"><td colspan="12">Belum ada data nilai. <a href="nilai.php?action=add" style="color:var(--crimson)">Input nilai pertama</a></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="table-card mt-6">
    <div class="table-card-header">
        <div class="table-card-title">Referensi Skala Nilai</div>
    </div>
    <div style="padding:16px 24px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <?php
        $grades = [
            'A' => ['range'=>'&gt; 90', 'label'=>'Sangat Baik', 'cls'=>'grade-a'],
            'B' => ['range'=>'71 - 90', 'label'=>'Baik', 'cls'=>'grade-b'],
            'C' => ['range'=>'61 - 70', 'label'=>'Cukup', 'cls'=>'grade-c'],
            'D' => ['range'=>'51 - 60', 'label'=>'Kurang', 'cls'=>'grade-d'],
            'E' => ['range'=>'&le; 50', 'label'=>'Tidak Lulus', 'cls'=>'grade-e'],
        ];
        foreach ($grades as $h => $g):
        ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--cream);border-radius:8px;">
            <span class="grade-badge <?= $g['cls'] ?>"><?= $h ?></span>
            <div>
                <div style="font-size:.8rem;font-weight:600;color:var(--navy)"><?= $g['label'] ?></div>
                <div style="font-size:.7rem;color:#aaa;font-family:var(--font-mono)"><?= $g['range'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <div style="margin-left:auto;text-align:right;font-size:.75rem;color:#aaa;">
            <div>Rumus: RATA = (Tugas + UTS + UAS) / 3</div>
            <div style="margin-top:3px;">Huruf mutu ditentukan otomatis oleh sistem</div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="confirm-modal" id="confirmModal">
    <div class="modal-icon">!</div>
    <h3>Hapus Data Nilai?</h3>
    <p>Anda akan menghapus nilai untuk <strong id="deleteTargetName"></strong>.<br>Tindakan ini tidak dapat dibatalkan.</p>
    <div class="modal-actions">
        <button class="btn btn-danger" onclick="executeDelete()">Ya, Hapus</button>
        <button class="btn btn-secondary" onclick="closeModal()">Batal</button>
    </div>
</div>

<?php require 'footer.php'; ?>
