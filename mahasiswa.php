<?php
// mahasiswa.php - CRUD Mahasiswa
require_once 'config.php';
$pageTitle = 'Mahasiswa';
$db = getDB();

$action = $_GET['action'] ?? 'list';
$errors = [];
$old = [];
$academicPrograms = academicPrograms();

if ($action === 'delete' && isset($_GET['npm'])) {
    $npm = $_GET['npm'];

    try {
        $db->beginTransaction();

        $photoStmt = $db->prepare("SELECT NAMA, FOTO_PROFILE FROM mahasiswa WHERE NPM = ?");
        $photoStmt->execute([$npm]);
        $target = $photoStmt->fetch();

        if (!$target) {
            $db->rollBack();
            setFlash('error', 'Data mahasiswa tidak ditemukan.');
            header('Location: mahasiswa.php');
            exit;
        }

        $db->prepare("DELETE FROM nilai WHERE NPM = ?")->execute([$npm]);

        $stmt = $db->prepare("DELETE FROM mahasiswa WHERE NPM = ?");
        $stmt->execute([$npm]);

        $db->commit();
        removeProfilePhoto($target['FOTO_PROFILE'] ?: null);
        setFlash('success', 'Data mahasiswa "' . $target['NAMA'] . '" berhasil dihapus beserta nilai terkait.');
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlash('error', 'Data mahasiswa gagal dihapus: ' . $e->getMessage());
    }

    header('Location: mahasiswa.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $npm = strtoupper(trim($_POST['NPM'] ?? ''));
    $nama = trim($_POST['NAMA'] ?? '');
    $alamat = trim($_POST['ALAMAT'] ?? '');
    $fakultas = trim($_POST['FAKULTAS'] ?? '');
    $prodi = trim($_POST['PRODI'] ?? '');
    $oldNpm = trim($_POST['old_npm'] ?? '');
    $currentPhoto = trim($_POST['current_photo'] ?? '');

    $old = [
        'NPM' => $npm,
        'NAMA' => $nama,
        'ALAMAT' => $alamat,
        'FAKULTAS' => $fakultas,
        'PRODI' => $prodi,
        'FOTO_PROFILE' => $currentPhoto,
    ];

    if ($npm === '') {
        $errors['NPM'] = 'NPM wajib diisi.';
    } elseif (strlen($npm) > 30) {
        $errors['NPM'] = 'NPM maksimal 30 karakter.';
    }

    if (strlen($nama) < 3) {
        $errors['NAMA'] = 'Nama lengkap minimal 3 karakter.';
    }

    if (strlen($nama) > 80) {
        $errors['NAMA'] = 'Nama lengkap maksimal 80 karakter.';
    }

    if (strlen($alamat) < 5) {
        $errors['ALAMAT'] = 'Alamat minimal 5 karakter.';
    }

    if (strlen($alamat) > 160) {
        $errors['ALAMAT'] = 'Alamat maksimal 160 karakter.';
    }

    if (!isValidFacultyProgram($fakultas, $prodi)) {
        $errors['PRODI'] = 'Pilih fakultas dan prodi yang sesuai.';
    }

    if (empty($errors)) {
        try {
            if ($action === 'add') {
                $ck = $db->prepare("SELECT COUNT(*) FROM mahasiswa WHERE NPM = ?");
                $ck->execute([$npm]);

                if ((int)$ck->fetchColumn() > 0) {
                    $errors['NPM'] = 'NPM sudah terdaftar.';
                } else {
                    [$photoPath, $photoError] = saveProfilePhoto($_FILES['FOTO_PROFILE'] ?? null, null);
                    if ($photoError) {
                        $errors['FOTO_PROFILE'] = $photoError;
                    } else {
                        $st = $db->prepare(
                            "INSERT INTO mahasiswa (NPM, NAMA, ALAMAT, FAKULTAS, PRODI, FOTO_PROFILE)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $st->execute([$npm, $nama, $alamat, $fakultas, $prodi, $photoPath]);
                        setFlash('success', "Mahasiswa \"$nama\" berhasil ditambahkan.");
                        header('Location: mahasiswa.php');
                        exit;
                    }
                }
            } elseif ($action === 'edit') {
                $photoStmt = $db->prepare("SELECT FOTO_PROFILE FROM mahasiswa WHERE NPM = ?");
                $photoStmt->execute([$oldNpm]);
                $existingPhoto = $photoStmt->fetchColumn() ?: null;
                $photoPath = $existingPhoto;

                if (!empty($_POST['remove_photo'])) {
                    removeProfilePhoto($photoPath);
                    $photoPath = null;
                }

                [$photoPath, $photoError] = saveProfilePhoto($_FILES['FOTO_PROFILE'] ?? null, $photoPath);
                if ($photoError) {
                    $errors['FOTO_PROFILE'] = $photoError;
                    $old['FOTO_PROFILE'] = $photoPath;
                } else {
                    if ($npm !== $oldNpm) {
                        $ck = $db->prepare("SELECT COUNT(*) FROM mahasiswa WHERE NPM = ?");
                        $ck->execute([$npm]);
                        if ((int)$ck->fetchColumn() > 0) {
                            $errors['NPM'] = 'NPM sudah digunakan mahasiswa lain.';
                        }
                    }

                    $relasiCek = $db->prepare(
                        "SELECT COUNT(*)
                         FROM nilai n
                         JOIN matakuliah mk ON mk.KDMK = n.KDMK
                         WHERE n.NPM = ? AND (mk.FAKULTAS <> ? OR mk.PRODI <> ?)"
                    );
                    $relasiCek->execute([$oldNpm, $fakultas, $prodi]);
                    if ((int)$relasiCek->fetchColumn() > 0) {
                        $errors['PRODI'] = 'Prodi tidak bisa diubah karena ada nilai pada mata kuliah dari prodi lama.';
                    }

                    if ($errors) {
                        $old['FOTO_PROFILE'] = $photoPath;
                    } else {
                    $st = $db->prepare(
                        "UPDATE mahasiswa
                         SET NPM = ?, NAMA = ?, ALAMAT = ?, FAKULTAS = ?, PRODI = ?, FOTO_PROFILE = ?
                         WHERE NPM = ?"
                    );
                    $st->execute([$npm, $nama, $alamat, $fakultas, $prodi, $photoPath, $oldNpm]);
                    setFlash('success', "Data mahasiswa \"$nama\" berhasil diperbarui.");
                    header('Location: mahasiswa.php');
                    exit;
                    }
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

if ($action === 'edit' && isset($_GET['npm']) && empty($old)) {
    $st = $db->prepare("SELECT * FROM mahasiswa WHERE NPM = ?");
    $st->execute([$_GET['npm']]);
    $editRow = $st->fetch();

    if (!$editRow) {
        setFlash('error', 'Data mahasiswa tidak ditemukan.');
        header('Location: mahasiswa.php');
        exit;
    }

    $old = $editRow;
}

$list = $db->query(
    "SELECT m.*,
        (SELECT COUNT(*) FROM nilai n WHERE n.NPM = m.NPM) AS jml_nilai
     FROM mahasiswa m
     ORDER BY m.CREATED_AT DESC, m.NPM"
)->fetchAll();

require 'header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Data Mahasiswa</h1>
        <p class="page-subtitle">Kelola profil, identitas, dan riwayat akademik mahasiswa.</p>
        <div class="page-accent-line"></div>
    </div>
    <?php if ($action === 'list'): ?>
    <a href="mahasiswa.php?action=add" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tambah Mahasiswa
    </a>
    <?php else: ?>
    <a href="mahasiswa.php" class="btn btn-secondary">Kembali ke Daftar</a>
    <?php endif; ?>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="form-card">
    <div class="form-card-header">
        <div class="form-card-header-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <?php if ($action === 'add'): ?><line x1="21" y1="15" x2="21" y2="21"/><line x1="18" y1="18" x2="24" y2="18"/><?php endif; ?>
            </svg>
        </div>
        <div>
            <h2><?= $action === 'add' ? 'Tambah Mahasiswa Baru' : 'Edit Profil Mahasiswa' ?></h2>
            <p>Lengkapi identitas, fakultas, prodi, dan foto profil mahasiswa.</p>
        </div>
    </div>

    <?php if (!empty($errors['_db'])): ?>
    <div class="flash flash-error" style="margin:16px 28px 0;border-radius:8px;">! <?= clean($errors['_db']) ?></div>
    <?php endif; ?>

    <form method="POST" action="mahasiswa.php?action=<?= clean($action) ?>" enctype="multipart/form-data" novalidate>
        <?php if ($action === 'edit'): ?>
        <input type="hidden" name="old_npm" value="<?= clean($old['NPM'] ?? '') ?>">
        <input type="hidden" name="current_photo" value="<?= clean($old['FOTO_PROFILE'] ?? '') ?>">
        <?php endif; ?>

        <div class="form-body">
            <div class="student-form-layout">
                <div class="profile-upload-panel">
                    <?php
                    $photo = profilePhotoUrl($old['FOTO_PROFILE'] ?? null);
                    $initials = studentInitials($old['NAMA'] ?? 'Mahasiswa');
                    ?>
                    <div class="profile-preview" id="profilePreview">
                        <?php if ($photo): ?>
                            <img src="<?= clean($photo) ?>" alt="Foto <?= clean($old['NAMA'] ?? 'Mahasiswa') ?>">
                        <?php else: ?>
                            <span><?= clean($initials) ?></span>
                        <?php endif; ?>
                    </div>
                    <label class="file-control" for="FOTO_PROFILE">
                        <input type="file" id="FOTO_PROFILE" name="FOTO_PROFILE" accept="image/jpeg,image/png,image/webp,image/gif">
                        <span>Pilih Foto Profil</span>
                    </label>
                    <span class="form-hint <?= isset($errors['FOTO_PROFILE']) ? 'error' : '' ?>">
                        <?= $errors['FOTO_PROFILE'] ?? 'JPG, PNG, WEBP, GIF. Maksimal 2 MB.' ?>
                    </span>
                    <?php if ($action === 'edit' && $photo): ?>
                    <label class="remove-photo-option">
                        <input type="checkbox" name="remove_photo" value="1">
                        Hapus foto saat disimpan
                    </label>
                    <?php endif; ?>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="NPM">NPM <span class="label-req">*</span></label>
                        <input type="text" id="NPM" name="NPM" maxlength="30"
                               class="form-control <?= isset($errors['NPM']) ? 'error' : '' ?>"
                               value="<?= clean($old['NPM'] ?? '') ?>"
                               placeholder="Contoh: 20230001000001">
                        <span class="form-hint <?= isset($errors['NPM']) ? 'error' : '' ?>">
                            <?= $errors['NPM'] ?? 'Maksimal 30 karakter' ?>
                        </span>
                    </div>

                    <div class="form-group">
                        <label for="NAMA">Nama Lengkap <span class="label-req">*</span></label>
                        <input type="text" id="NAMA" name="NAMA" maxlength="80"
                               class="form-control <?= isset($errors['NAMA']) ? 'error' : '' ?>"
                               value="<?= clean($old['NAMA'] ?? '') ?>"
                               placeholder="Nama lengkap mahasiswa">
                        <span class="form-hint <?= isset($errors['NAMA']) ? 'error' : '' ?>">
                            <?= $errors['NAMA'] ?? 'Maksimal 80 karakter' ?>
                        </span>
                    </div>

                    <div class="form-group span-2">
                        <label for="ALAMAT">Alamat <span class="label-req">*</span></label>
                        <input type="text" id="ALAMAT" name="ALAMAT" maxlength="160"
                               class="form-control <?= isset($errors['ALAMAT']) ? 'error' : '' ?>"
                               value="<?= clean($old['ALAMAT'] ?? '') ?>"
                               placeholder="Contoh: 123 Harvard Yard, Cambridge MA">
                        <span class="form-hint <?= isset($errors['ALAMAT']) ? 'error' : '' ?>">
                            <?= $errors['ALAMAT'] ?? 'Alamat rumah atau kampus, maksimal 160 karakter' ?>
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
                        <span class="form-hint">Fakultas utama mahasiswa</span>
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
        </div>

        <div class="form-footer">
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <?= $action === 'add' ? 'Simpan Mahasiswa' : 'Update Data' ?>
            </button>
            <a href="mahasiswa.php" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="table-card">
    <div class="table-card-header">
        <div>
            <div class="table-card-title">Daftar Mahasiswa</div>
            <div class="table-card-sub">Total <?= count($list) ?> mahasiswa terdaftar</div>
        </div>
        <div class="table-card-actions">
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchMahasiswa" placeholder="Cari mahasiswa...">
            </div>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table" id="tblMahasiswa">
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Profil</th>
                <th>NPM</th>
                <th>Nama Lengkap</th>
                <th>Fakultas / Prodi</th>
                <th>Alamat</th>
                <th class="text-center">Nilai</th>
                <th class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($list): ?>
            <?php foreach ($list as $i => $m): ?>
            <?php
            $rowPhoto = profilePhotoUrl($m['FOTO_PROFILE'] ?? null);
            $deleteUrl = 'mahasiswa.php?action=delete&npm=' . urlencode($m['NPM']);
            ?>
            <tr>
                <td class="row-no text-center"><?= $i + 1 ?></td>
                <td>
                    <div class="student-avatar">
                        <?php if ($rowPhoto): ?>
                            <img src="<?= clean($rowPhoto) ?>" alt="Foto <?= clean($m['NAMA']) ?>">
                        <?php else: ?>
                            <span><?= clean(studentInitials($m['NAMA'])) ?></span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="mono"><?= clean($m['NPM']) ?></td>
                <td class="fw-600"><?= clean($m['NAMA']) ?></td>
                <td>
                    <div class="fw-600" style="font-size:.82rem"><?= clean($m['PRODI'] ?? '-') ?></div>
                    <div class="text-muted" style="font-size:.72rem"><?= clean($m['FAKULTAS'] ?? '-') ?></div>
                </td>
                <td class="text-muted"><?= clean($m['ALAMAT']) ?></td>
                <td class="text-center">
                    <a href="nilai.php?npm=<?= urlencode($m['NPM']) ?>"
                       class="badge badge-navy" style="text-decoration:none;cursor:pointer;"
                       title="Lihat nilai">
                        <?= (int)$m['jml_nilai'] ?> mata kuliah
                    </a>
                </td>
                <td>
                    <div class="action-group" style="justify-content:center">
                        <a href="mahasiswa.php?action=edit&npm=<?= urlencode($m['NPM']) ?>"
                           class="btn-icon edit" title="Edit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <a href="<?= clean($deleteUrl) ?>"
                           class="btn-icon del"
                           title="Hapus"
                           onclick="return confirm('Yakin hapus mahasiswa ini? Semua nilai terkait juga akan ikut terhapus.')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr class="empty-row"><td colspan="8">Belum ada mahasiswa. <a href="mahasiswa.php?action=add" style="color:var(--crimson)">Tambah data pertama</a></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<div class="confirm-modal" id="confirmModal">
    <div class="modal-icon">!</div>
    <h3>Hapus Data Mahasiswa?</h3>
    <p>Anda akan menghapus <strong id="deleteTargetName"></strong>.<br>Tindakan ini tidak dapat dibatalkan.</p>
    <div class="modal-actions">
        <button class="btn btn-danger" onclick="executeDelete()">Ya, Hapus</button>
        <button class="btn btn-secondary" onclick="closeModal()">Batal</button>
    </div>
</div>

<?php require 'footer.php'; ?>
