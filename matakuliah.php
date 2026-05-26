<?php
// matakuliah.php - CRUD Mata Kuliah
require_once 'config.php';
$pageTitle = 'Mata Kuliah';
$db = getDB();

$action = $_GET['action'] ?? 'list';
$errors = [];
$old = [];
$academicPrograms = academicPrograms();

if ($action === 'delete' && isset($_GET['kdmk'])) {
    $kdmk = $_GET['kdmk'];

    try {
        $db->beginTransaction();

        $nameStmt = $db->prepare("SELECT NAMAMK FROM matakuliah WHERE KDMK = ?");
        $nameStmt->execute([$kdmk]);
        $namaMk = $nameStmt->fetchColumn();

        if (!$namaMk) {
            $db->rollBack();
            setFlash('error', 'Data mata kuliah tidak ditemukan.');
            header('Location: matakuliah.php');
            exit;
        }

        $db->prepare("DELETE FROM nilai WHERE KDMK = ?")->execute([$kdmk]);

        $stmt = $db->prepare("DELETE FROM matakuliah WHERE KDMK = ?");
        $stmt->execute([$kdmk]);

        $db->commit();
        setFlash('success', 'Mata kuliah "' . $namaMk . '" berhasil dihapus beserta nilai terkait.');
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlash('error', 'Mata kuliah gagal dihapus: ' . $e->getMessage());
    }

    header('Location: matakuliah.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $kdmk = strtoupper(trim($_POST['KDMK'] ?? ''));
    $namamk = trim($_POST['NAMAMK'] ?? '');
    $sks = (int)($_POST['SKS'] ?? 0);
    $fakultas = trim($_POST['FAKULTAS'] ?? '');
    $prodi = trim($_POST['PRODI'] ?? '');
    $oldKdmk = trim($_POST['old_kdmk'] ?? '');
    $old = [
        'KDMK' => $kdmk,
        'NAMAMK' => $namamk,
        'SKS' => $sks,
        'FAKULTAS' => $fakultas,
        'PRODI' => $prodi,
    ];

    if (strlen($kdmk) < 2 || strlen($kdmk) > 5) {
        $errors['KDMK'] = 'Kode mata kuliah harus 2 sampai 5 karakter.';
    }
    if (strlen($namamk) < 3) {
        $errors['NAMAMK'] = 'Nama mata kuliah minimal 3 karakter.';
    }
    if (strlen($namamk) > 80) {
        $errors['NAMAMK'] = 'Nama mata kuliah maksimal 80 karakter.';
    }
    if ($sks < 1 || $sks > 8) {
        $errors['SKS'] = 'SKS harus berada di antara 1 sampai 8.';
    }
    if (!isValidFacultyProgram($fakultas, $prodi)) {
        $errors['PRODI'] = 'Pilih fakultas dan prodi yang sesuai.';
    }

    if (empty($errors)) {
        try {
            if ($action === 'add') {
                $ck = $db->prepare("SELECT COUNT(*) FROM matakuliah WHERE KDMK = ?");
                $ck->execute([$kdmk]);

                if ((int)$ck->fetchColumn() > 0) {
                    $errors['KDMK'] = 'Kode mata kuliah sudah terdaftar.';
                } else {
                    $st = $db->prepare("INSERT INTO matakuliah (KDMK, NAMAMK, SKS, FAKULTAS, PRODI) VALUES (?, ?, ?, ?, ?)");
                    $st->execute([$kdmk, $namamk, $sks, $fakultas, $prodi]);
                    setFlash('success', "Mata kuliah \"$namamk\" berhasil ditambahkan.");
                    header('Location: matakuliah.php');
                    exit;
                }
            } elseif ($action === 'edit') {
                if ($kdmk !== $oldKdmk) {
                    $ck = $db->prepare("SELECT COUNT(*) FROM matakuliah WHERE KDMK = ?");
                    $ck->execute([$kdmk]);
                    if ((int)$ck->fetchColumn() > 0) {
                        $errors['KDMK'] = 'Kode mata kuliah sudah digunakan.';
                    }
                }

                $relasiCek = $db->prepare(
                    "SELECT COUNT(*)
                     FROM nilai n
                     JOIN mahasiswa m ON m.NPM = n.NPM
                     WHERE n.KDMK = ? AND (m.FAKULTAS <> ? OR m.PRODI <> ?)"
                );
                $relasiCek->execute([$oldKdmk, $fakultas, $prodi]);
                if ((int)$relasiCek->fetchColumn() > 0) {
                    $errors['PRODI'] = 'Prodi tidak bisa diubah karena ada nilai mahasiswa dari prodi lama.';
                }

                if (!$errors) {
                    $st = $db->prepare("UPDATE matakuliah SET KDMK = ?, NAMAMK = ?, SKS = ?, FAKULTAS = ?, PRODI = ? WHERE KDMK = ?");
                    $st->execute([$kdmk, $namamk, $sks, $fakultas, $prodi, $oldKdmk]);
                setFlash('success', "Data mata kuliah \"$namamk\" berhasil diperbarui.");
                header('Location: matakuliah.php');
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

if ($action === 'edit' && isset($_GET['kdmk']) && empty($old)) {
    $st = $db->prepare("SELECT * FROM matakuliah WHERE KDMK = ?");
    $st->execute([$_GET['kdmk']]);
    $editRow = $st->fetch();

    if (!$editRow) {
        setFlash('error', 'Data mata kuliah tidak ditemukan.');
        header('Location: matakuliah.php');
        exit;
    }

    $old = $editRow;
}

$list = $db->query(
    "SELECT mk.*,
        (SELECT COUNT(*) FROM nilai n WHERE n.KDMK = mk.KDMK) AS jml_nilai
     FROM matakuliah mk
     ORDER BY mk.KDMK"
)->fetchAll();

require 'header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Data Mata Kuliah</h1>
        <p class="page-subtitle">Kelola katalog mata kuliah, jumlah SKS, dan keterkaitan nilai mahasiswa.</p>
        <div class="page-accent-line"></div>
    </div>
    <?php if ($action === 'list'): ?>
    <a href="matakuliah.php?action=add" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tambah Mata Kuliah
    </a>
    <?php else: ?>
    <a href="matakuliah.php" class="btn btn-secondary">Kembali ke Daftar</a>
    <?php endif; ?>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="form-card">
    <div class="form-card-header">
        <div class="form-card-header-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        </div>
        <div>
            <h2><?= $action === 'add' ? 'Tambah Mata Kuliah Baru' : 'Edit Data Mata Kuliah' ?></h2>
            <p><?= $action === 'add' ? 'Tambahkan mata kuliah baru sesuai fakultas dan prodi.' : 'Perbarui kode, nama, SKS, fakultas, atau prodi mata kuliah.' ?></p>
        </div>
    </div>

    <?php if (!empty($errors['_db'])): ?>
    <div class="flash flash-error" style="margin:16px 28px 0;border-radius:8px;">! <?= clean($errors['_db']) ?></div>
    <?php endif; ?>

    <form method="POST" action="matakuliah.php?action=<?= clean($action) ?>" novalidate>
        <?php if ($action === 'edit'): ?>
        <input type="hidden" name="old_kdmk" value="<?= clean($old['KDMK'] ?? '') ?>">
        <?php endif; ?>
        <div class="form-body">
            <div class="form-grid cols-3">
                <div class="form-group">
                    <label for="KDMK">Kode MK <span class="label-req">*</span></label>
                    <input type="text" id="KDMK" name="KDMK" maxlength="5"
                           class="form-control <?= isset($errors['KDMK']) ? 'error' : '' ?>"
                           value="<?= clean($old['KDMK'] ?? '') ?>"
                           placeholder="Contoh: CS101"
                           style="text-transform:uppercase">
                    <span class="form-hint <?= isset($errors['KDMK']) ? 'error' : '' ?>">
                        <?= $errors['KDMK'] ?? 'Kode unik 2 sampai 5 karakter' ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="NAMAMK">Nama Mata Kuliah <span class="label-req">*</span></label>
                    <input type="text" id="NAMAMK" name="NAMAMK" maxlength="80"
                           class="form-control <?= isset($errors['NAMAMK']) ? 'error' : '' ?>"
                           value="<?= clean($old['NAMAMK'] ?? '') ?>"
                           placeholder="Contoh: Pemrograman Dasar">
                    <span class="form-hint <?= isset($errors['NAMAMK']) ? 'error' : '' ?>">
                        <?= $errors['NAMAMK'] ?? 'Nama mata kuliah maksimal 80 karakter' ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="SKS">SKS <span class="label-req">*</span></label>
                    <select id="SKS" name="SKS"
                            class="form-control <?= isset($errors['SKS']) ? 'error' : '' ?>">
                        <option value="">-- Pilih SKS --</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>" <?= (isset($old['SKS']) && (int)$old['SKS'] === $i) ? 'selected' : '' ?>>
                            <?= $i ?> SKS
                        </option>
                        <?php endfor; ?>
                    </select>
                    <span class="form-hint <?= isset($errors['SKS']) ? 'error' : '' ?>">
                        <?= $errors['SKS'] ?? 'Satuan Kredit Semester, 1 sampai 8' ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="FAKULTAS">Fakultas <span class="label-req">*</span></label>
                    <select id="FAKULTAS" name="FAKULTAS"
                            class="form-control <?= isset($errors['PRODI']) ? 'error' : '' ?>">
                        <option value="">-- Pilih Fakultas --</option>
                        <?php foreach ($academicPrograms as $facultyName => $programs): ?>
                        <option value="<?= clean($facultyName) ?>"
                            <?= ($old['FAKULTAS'] ?? '') === $facultyName ? 'selected' : '' ?>>
                            <?= clean($facultyName) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">Fakultas pemilik mata kuliah</span>
                </div>

                <div class="form-group">
                    <label for="PRODI">Program Studi <span class="label-req">*</span></label>
                    <select id="PRODI" name="PRODI"
                            class="form-control <?= isset($errors['PRODI']) ? 'error' : '' ?>">
                        <option value="">-- Pilih Prodi --</option>
                        <?php foreach ($academicPrograms as $facultyName => $programs): ?>
                            <?php foreach ($programs as $programName): ?>
                            <option value="<?= clean($programName) ?>" data-fakultas="<?= clean($facultyName) ?>"
                                <?= ($old['PRODI'] ?? '') === $programName ? 'selected' : '' ?>>
                                <?= clean($programName) ?>
                            </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint <?= isset($errors['PRODI']) ? 'error' : '' ?>">
                        <?= $errors['PRODI'] ?? 'Prodi otomatis mengikuti fakultas' ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="form-footer">
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <?= $action === 'add' ? 'Simpan Mata Kuliah' : 'Update Data' ?>
            </button>
            <a href="matakuliah.php" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="table-card">
    <div class="table-card-header">
        <div>
            <div class="table-card-title">Daftar Mata Kuliah</div>
            <div class="table-card-sub">Total <?= count($list) ?> mata kuliah dalam katalog</div>
        </div>
        <div class="table-card-actions">
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchMatakuliah" placeholder="Cari mata kuliah...">
            </div>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table" id="tblMatakuliah">
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Kode MK</th>
                <th>Nama Mata Kuliah</th>
                <th>Fakultas / Prodi</th>
                <th class="text-center">SKS</th>
                <th class="text-center">Rekaman Nilai</th>
                <th class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($list): ?>
            <?php foreach ($list as $i => $mk): ?>
            <?php
            $deleteUrl = 'matakuliah.php?action=delete&kdmk=' . urlencode($mk['KDMK']);
            $deleteLabel = $mk['NAMAMK'];
            ?>
            <tr>
                <td class="row-no text-center"><?= $i + 1 ?></td>
                <td class="mono fw-600" style="color:var(--crimson)"><?= clean($mk['KDMK']) ?></td>
                <td class="fw-600"><?= clean($mk['NAMAMK']) ?></td>
                <td>
                    <div class="fw-600" style="font-size:.82rem"><?= clean($mk['PRODI'] ?? '-') ?></div>
                    <div class="text-muted" style="font-size:.72rem"><?= clean($mk['FAKULTAS'] ?? '-') ?></div>
                </td>
                <td class="text-center"><span class="badge badge-sks"><?= (int)$mk['SKS'] ?> SKS</span></td>
                <td class="text-center"><span class="badge badge-navy"><?= (int)$mk['jml_nilai'] ?> data</span></td>
                <td>
                    <div class="action-group" style="justify-content:center">
                        <a href="matakuliah.php?action=edit&kdmk=<?= urlencode($mk['KDMK']) ?>"
                           class="btn-icon edit" title="Edit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <a href="<?= clean($deleteUrl) ?>"
                           class="btn-icon del"
                           title="Hapus"
                           onclick="return confirm('Yakin hapus mata kuliah ini? Semua nilai terkait juga akan ikut terhapus.')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr class="empty-row"><td colspan="7">Belum ada mata kuliah. <a href="matakuliah.php?action=add" style="color:var(--crimson)">Tambah data pertama</a></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="table-card mt-6" style="overflow:visible;">
    <div class="table-card-header">
        <div class="table-card-title">Panduan Beban SKS</div>
    </div>
    <div style="padding:20px 24px;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;">
        <?php
        $sksDesc = [
            1 => 'Ringan',
            2 => 'Praktikum',
            3 => 'Standar',
            4 => 'Inti Prodi',
            5 => 'Mayor',
            6 => 'Intensif',
            7 => 'Pra Skripsi',
            8 => 'Skripsi',
        ];
        for ($i = 1; $i <= 8; $i++):
        ?>
        <div style="background:var(--cream);border-radius:8px;padding:12px;text-align:center;">
            <div style="font-family:var(--font-display);font-size:1.5rem;color:var(--navy);font-weight:700"><?= $i ?></div>
            <div style="font-size:.68rem;color:#888;letter-spacing:.06em;text-transform:uppercase;margin-top:3px"><?= $sksDesc[$i] ?></div>
        </div>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<div class="confirm-modal" id="confirmModal">
    <div class="modal-icon">!</div>
    <h3>Hapus Mata Kuliah?</h3>
    <p>Anda akan menghapus <strong id="deleteTargetName"></strong>.<br>Data nilai yang terhubung juga akan ikut terhapus.</p>
    <div class="modal-actions">
        <button class="btn btn-danger" onclick="executeDelete()">Ya, Hapus</button>
        <button class="btn btn-secondary" onclick="closeModal()">Batal</button>
    </div>
</div>

<?php require 'footer.php'; ?>
