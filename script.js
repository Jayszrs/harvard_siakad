/* ============================================================
   script.js - Harvard University SIAKAD
   ============================================================ */

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('open');
}

document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const button = document.getElementById('hamburgerBtn');
    if (sidebar && button && !sidebar.contains(e.target) && !button.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});

let pendingDeleteUrl = null;

function confirmDelete(url, name) {
    pendingDeleteUrl = url;
    const nameEl = document.getElementById('deleteTargetName');
    if (nameEl) nameEl.textContent = name || 'data ini';

    const overlay = document.getElementById('modalOverlay');
    const modal = document.getElementById('confirmModal');
    if (overlay) overlay.classList.add('active');
    if (modal) modal.classList.add('active');
}

function closeModal() {
    const overlay = document.getElementById('modalOverlay');
    const modal = document.getElementById('confirmModal');
    if (overlay) overlay.classList.remove('active');
    if (modal) modal.classList.remove('active');
    pendingDeleteUrl = null;
}

function executeDelete() {
    if (pendingDeleteUrl) window.location.href = pendingDeleteUrl.replace(/&amp;/g, '&');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

function initSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr:not(.empty-row)');
        let visible = 0;

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const show = text.includes(q);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        let emptyRow = table.querySelector('.dyn-empty');
        if (visible === 0) {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.className = 'empty-row dyn-empty';
                emptyRow.innerHTML = '<td colspan="20">Tidak ada data yang cocok dengan pencarian.</td>';
                table.querySelector('tbody').appendChild(emptyRow);
            }
        } else if (emptyRow) {
            emptyRow.remove();
        }
    });
}

function initNilaiCalc() {
    const tugasEl = document.getElementById('TUGAS');
    const utsEl = document.getElementById('UTS');
    const uasEl = document.getElementById('UAS');
    const rataEl = document.getElementById('displayRata');
    const hurufEl = document.getElementById('displayHuruf');
    const hurufInput = document.getElementById('HURUF');

    if (!tugasEl || !utsEl || !uasEl) return;

    function calc() {
        const tugas = parseFloat(tugasEl.value) || 0;
        const uts = parseFloat(utsEl.value) || 0;
        const uas = parseFloat(uasEl.value) || 0;
        const rata = (tugas + uts + uas) / 3;

        let huruf = 'E';
        if (rata > 90) huruf = 'A';
        else if (rata > 70) huruf = 'B';
        else if (rata > 60) huruf = 'C';
        else if (rata > 50) huruf = 'D';

        if (rataEl) rataEl.textContent = rata.toFixed(2);
        if (hurufEl) {
            hurufEl.textContent = huruf;
            hurufEl.className = 'huruf-val grade-' + huruf.toLowerCase();
        }
        if (hurufInput) hurufInput.value = huruf;
    }

    [tugasEl, utsEl, uasEl].forEach(el => {
        el.addEventListener('input', calc);
        el.addEventListener('change', calc);
    });

    calc();
}

function initScoreValidation() {
    ['TUGAS', 'UTS', 'UAS'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;

        el.addEventListener('blur', function() {
            let value = parseFloat(this.value);
            if (isNaN(value)) {
                this.value = '';
                return;
            }
            if (value < 0) value = 0;
            if (value > 100) value = 100;
            this.value = value;
        });
    });
}

function initAutoUpper(id) {
    const el = document.getElementById(id);
    if (!el) return;

    el.addEventListener('input', function() {
        const pos = this.selectionStart;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(pos, pos);
    });
}

function initProfilePreview() {
    const input = document.getElementById('FOTO_PROFILE');
    const preview = document.getElementById('profilePreview');
    if (!input || !preview) return;

    input.addEventListener('change', function() {
        const file = this.files && this.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = function(event) {
            preview.innerHTML = '';
            const img = document.createElement('img');
            img.src = event.target.result;
            img.alt = 'Pratinjau foto profil';
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);

        const label = input.closest('.file-control');
        if (label) {
            const text = label.querySelector('span');
            if (text) text.textContent = file.name;
        }
    });
}

function initFacultyProgramSelect() {
    const facultyEl = document.getElementById('FAKULTAS');
    const programEl = document.getElementById('PRODI');
    if (!facultyEl || !programEl) return;

    function syncPrograms() {
        const faculty = facultyEl.value;
        let selectedStillVisible = false;

        Array.from(programEl.options).forEach(option => {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const match = option.dataset.fakultas === faculty;
            option.hidden = !match;
            option.disabled = !match;
            if (match && option.selected) selectedStillVisible = true;
        });

        if (!selectedStillVisible) programEl.value = '';
        programEl.disabled = !faculty;
    }

    facultyEl.addEventListener('change', syncPrograms);
    syncPrograms();
}

function initNilaiCourseFilter() {
    const studentEl = document.getElementById('NPM');
    const courseEl = document.getElementById('KDMK');
    if (!studentEl || !courseEl) return;

    function syncCourses() {
        const student = studentEl.selectedOptions[0];
        const fakultas = student ? student.dataset.fakultas : '';
        const prodi = student ? student.dataset.prodi : '';
        let selectedStillVisible = false;

        Array.from(courseEl.options).forEach(option => {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const match = option.dataset.fakultas === fakultas && option.dataset.prodi === prodi;
            option.hidden = !match;
            option.disabled = !match;
            if (match && option.selected) selectedStillVisible = true;
        });

        if (!selectedStillVisible) courseEl.value = '';
        courseEl.disabled = !studentEl.value;
    }

    studentEl.addEventListener('change', syncCourses);
    syncCourses();
}

function animateCounters() {
    document.querySelectorAll('.stat-value[data-target]').forEach(el => {
        const target = parseInt(el.getAttribute('data-target'), 10);
        if (isNaN(target)) return;

        let current = 0;
        const step = Math.max(1, Math.ceil(target / 40));
        el.textContent = '0';

        const timer = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = current;
            if (current >= target) clearInterval(timer);
        }, 30);
    });
}

function initFlashDismiss() {
    const flash = document.getElementById('flashMsg');
    if (!flash) return;

    setTimeout(() => {
        flash.style.opacity = '0';
        flash.style.transition = 'opacity .5s';
        setTimeout(() => flash.remove(), 500);
    }, 4500);
}

function renumberRows(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;

    let number = 1;
    table.querySelectorAll('tbody tr:not(.empty-row)').forEach(row => {
        const no = row.querySelector('.row-no');
        if (no) no.textContent = number++;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initNilaiCalc();
    initScoreValidation();
    initAutoUpper('NPM');
    initAutoUpper('KDMK');
    initProfilePreview();
    initFacultyProgramSelect();
    initNilaiCourseFilter();
    initSearch('searchMahasiswa', 'tblMahasiswa');
    initSearch('searchMatakuliah', 'tblMatakuliah');
    initSearch('searchNilai', 'tblNilai');
    animateCounters();
    initFlashDismiss();
});
