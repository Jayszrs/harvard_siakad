<?php
// header.php - shared top navigation + sidebar
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? clean($pageTitle) . ' - ' : '' ?><?= SITE_NAME ?> SIAKAD</title>
<link rel="icon" type="image/svg+xml" href="<?= SITE_LOGO ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Source+Serif+4:ital,wght@0,300;0,400;0,600;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>

<aside class="sidebar" id="sidebar">
    <a class="sidebar-logo" href="index.php" aria-label="Beranda Harvard SIAKAD">
        <div class="logo-seal">
            <img src="<?= SITE_LOGO ?>" alt="Logo Harvard">
        </div>
        <div class="logo-text">
            <span class="logo-uni">HARVARD</span>
            <span class="logo-dept">UNIVERSITAS</span>
            <span class="logo-sys">SIAKAD</span>
        </div>
    </a>

    <nav class="sidebar-nav">
        <div class="nav-section-label">MENU UTAMA</div>
        <a href="index.php" class="nav-item <?= isActive('index.php') ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            </span>
            <span>Beranda</span>
        </a>

        <div class="nav-section-label">DATA UTAMA</div>
        <a href="mahasiswa.php" class="nav-item <?= isActive('mahasiswa.php') ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <span>Mahasiswa</span>
        </a>
        <a href="matakuliah.php" class="nav-item <?= isActive('matakuliah.php') ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </span>
            <span>Mata Kuliah</span>
        </a>
        <a href="nilai.php" class="nav-item <?= isActive('nilai.php') ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </span>
            <span>Nilai</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sf-year">Tahun Akademik 2024/2025</div>
        <div class="sf-copy">&copy; Harvard University</div>
    </div>
</aside>

<div class="main-wrapper">
<header class="topbar">
    <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <a class="topbar-brand" href="index.php" aria-label="Beranda Harvard SIAKAD">
            <img src="<?= SITE_LOGO ?>" alt="">
            <span>Harvard SIAKAD</span>
        </a>
        <div class="page-breadcrumb">
            <span class="bc-home">SIAKAD</span>
            <span class="bc-sep">/</span>
            <span class="bc-current"><?= clean($pageTitle ?? 'Beranda') ?></span>
        </div>
    </div>
    <div class="topbar-right">
        <div class="topbar-date">
            <?= tanggalIndonesia() ?>
        </div>
        <div class="topbar-avatar">
            <div class="avatar-ring">
                <img src="<?= SITE_LOGO ?>" alt="">
            </div>
            <div class="avatar-info">
                <span class="av-name">Admin Akademik</span>
                <span class="av-role">Admin Akademik</span>
            </div>
        </div>
    </div>
</header>

<?php $flash = getFlash(); if ($flash): ?>
<div class="flash flash-<?= clean($flash['type']) ?>" id="flashMsg">
    <span class="flash-icon">
        <?= $flash['type'] === 'success' ? 'OK' : ($flash['type'] === 'error' ? '!' : 'i') ?>
    </span>
    <span><?= clean($flash['msg']) ?></span>
    <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Tutup pesan">&times;</button>
</div>
<?php endif; ?>

<main class="main-content">
