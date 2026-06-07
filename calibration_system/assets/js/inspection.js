window.addEventListener('DOMContentLoaded', () => {
    const isGuest = IS_GUEST;

    // ── Date helpers ──────────────────────────────────────────────────────────
    const now    = new Date();
    const todayY = now.getFullYear();
    const todayM = now.getMonth();
    function pad(n) { return String(n).padStart(2, '0'); }
    const todayStr       = `${todayY}-${pad(todayM + 1)}-${pad(now.getDate())}`;
    const thisMonthStart = `${todayY}-${pad(todayM + 1)}-01`;
    const thisMonthEnd   = `${todayY}-${pad(todayM + 1)}-${pad(new Date(todayY, todayM + 1, 0).getDate())}`;
    const nm             = new Date(todayY, todayM + 1, 1);
    const nextMonthStart = `${nm.getFullYear()}-${pad(nm.getMonth() + 1)}-01`;
    const nextMonthEnd   = `${nm.getFullYear()}-${pad(nm.getMonth() + 1)}-${pad(new Date(nm.getFullYear(), nm.getMonth() + 1, 0).getDate())}`;
    const lm             = new Date(todayY, todayM - 1, 1);
    const lastMonthStart = `${lm.getFullYear()}-${pad(lm.getMonth() + 1)}-01`;
    const lastMonthEnd   = `${lm.getFullYear()}-${pad(lm.getMonth() + 1)}-${pad(new Date(lm.getFullYear(), lm.getMonth() + 1, 0).getDate())}`;

    // ── Date range refs ───────────────────────────────────────────────────────
    const toggleDateRangeBtn = document.getElementById('toggleDateRangeBtn');
    const dateRangeBar       = document.getElementById('dateRangeBar');
    const dateFrom           = document.getElementById('dateFrom');
    const dateTo             = document.getElementById('dateTo');
    const dateRangeField     = document.getElementById('dateRangeField');
    const clearDateRange     = document.getElementById('clearDateRange');
    const rangeActiveDot     = document.getElementById('rangeActiveDot');
    const rangeResultBadge   = document.getElementById('rangeResultBadge');
    const presetChips        = document.querySelectorAll('.preset-chip');

    toggleDateRangeBtn.addEventListener('click', () => {
        const isOpen = dateRangeBar.style.display !== 'none';
        if (isOpen) { dateRangeBar.style.display = 'none'; resetRange(); filterRows(true); }
        else        { dateRangeBar.style.display = 'flex'; }
    });
    clearDateRange.addEventListener('click', () => { resetRange(); filterRows(true); });

    function resetRange() {
        dateFrom.value = ''; dateTo.value = '';
        if (dateRangeField) dateRangeField.value = 'next_inspection';
        presetChips.forEach(c => c.classList.remove('active'));
        rangeActiveDot.style.display   = 'none';
        rangeResultBadge.style.display = 'none';
        toggleDateRangeBtn.classList.remove('btn-accent');
        toggleDateRangeBtn.classList.add('btn-muted');
    }
    function onRangeChanged() {
        const has = dateFrom.value || dateTo.value;
        rangeActiveDot.style.display = has ? 'inline-block' : 'none';
        if (has) { toggleDateRangeBtn.classList.remove('btn-muted'); toggleDateRangeBtn.classList.add('btn-accent'); }
        else     { toggleDateRangeBtn.classList.remove('btn-accent'); toggleDateRangeBtn.classList.add('btn-muted'); }
    }
    dateFrom.addEventListener('change', () => { presetChips.forEach(c => c.classList.remove('active')); onRangeChanged(); filterRows(true); });
    dateTo.addEventListener('change',   () => { presetChips.forEach(c => c.classList.remove('active')); onRangeChanged(); filterRows(true); });
    if (dateRangeField) dateRangeField.addEventListener('change', () => filterRows(true));
    presetChips.forEach(chip => {
        chip.addEventListener('click', () => {
            if (chip.classList.contains('active')) {
                chip.classList.remove('active');
                dateFrom.value = ''; dateTo.value = '';
                if (dateRangeField) dateRangeField.value = 'next_inspection';
                onRangeChanged(); filterRows(true); return;
            }
            presetChips.forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            if (dateRangeField) dateRangeField.value = 'next_inspection';
            switch (chip.dataset.preset) {
                case 'thismonth':
                    dateFrom.value = thisMonthStart; dateTo.value = thisMonthEnd;
                    break;
                case 'nextmonth':
                    dateFrom.value = nextMonthStart; dateTo.value = nextMonthEnd;
                    break;
                case 'lastmonth-insp':
                    dateFrom.value = lastMonthStart; dateTo.value = lastMonthEnd;
                    if (dateRangeField) dateRangeField.value = 'inspection_date';
                    break;
            }
            onRangeChanged(); filterRows(true);
        });
    });

    // ── Element refs ──────────────────────────────────────────────────────────
    const tableBody           = document.querySelector('#inspectionTable tbody');
    const noResultsRow        = document.getElementById('noResultsRow');
    const searchInput         = document.getElementById('searchInput');
    const resultFilter        = document.getElementById('resultFilter');
    const selectedBadge       = document.getElementById('selectedCountBadge');
    const toggleDeleteBtn     = document.getElementById('toggleDeleteBtn');
    const bulkDeleteBar       = document.getElementById('bulkDeleteBar');
    const deleteSelectedBtn   = document.getElementById('deleteSelectedBtn');
    const cancelDeleteBtn     = document.getElementById('cancelDeleteBtn');
    const selectAllDelete     = document.getElementById('selectAllDelete');
    const deleteColHeader     = document.getElementById('deleteColHeader');
    const currentPageInput    = document.getElementById('currentPageInput');
    const deleteHiddenInputs  = document.getElementById('deleteHiddenInputs');
    const inspectionForm      = document.getElementById('inspectionForm');
    const togglePrintBtn      = document.getElementById('togglePrintBtn');
    const bulkPrintBar        = document.getElementById('bulkPrintBar');
    const continuePrintBtn    = document.getElementById('continuePrintBtn');
    const cancelPrintBtn      = document.getElementById('cancelPrintBtn');
    const printSelectedBadge  = document.getElementById('printSelectedBadge');
    const selectAllPrint      = document.getElementById('selectAllPrint');
    const printColHeader      = document.getElementById('printColHeader');
    const stickerModal        = document.getElementById('stickerModal');
    const closeStickerBtn     = document.getElementById('closeStickerModal');
    const detailModal         = document.getElementById('detailModal');
    const closeDetailModal    = document.getElementById('closeDetailModal');
    const closeDetailModalBtn = document.getElementById('closeDetailModalBtn');
    const editRecordBtn       = document.getElementById('editRecordBtn');
    const deleteConfirmModal  = document.getElementById('deleteConfirmModal');
    const deleteCountSpan     = document.getElementById('deleteCountSpan');
    const confirmDeleteYesBtn = document.getElementById('confirmDeleteYesBtn');
    const confirmDeleteNoBtn  = document.getElementById('confirmDeleteNoBtn');

    const exportReportBtn     = document.getElementById('exportReportBtn');
    const exportModal         = document.getElementById('exportModal');
    const closeExportModal    = document.getElementById('closeExportModal');
    const cancelExportBtn     = document.getElementById('cancelExportBtn');
    const exportExcelBtn      = document.getElementById('exportExcelBtn');
    const exportPdfBtn        = document.getElementById('exportPdfBtn');
    const exportFilterNote    = document.getElementById('exportFilterNote');
    const bulkExportBar       = document.getElementById('bulkExportBar');
    const continueExportBtn   = document.getElementById('continueExportBtn');
    const cancelExportSelBtn  = document.getElementById('cancelExportSelBtn');
    const exportSelectedBadge = document.getElementById('exportSelectedBadge');
    const selectAllExport     = document.getElementById('selectAllExport');
    const exportColHeader     = document.getElementById('exportColHeader');

    let rowsPerPage  = 15;
    let currentPage  = sessionStorage.getItem('inspectionReportPage')
                       ? parseInt(sessionStorage.getItem('inspectionReportPage'))
                       : CURRENT_PAGE_INIT;
    let filteredRows = [];

    let resultColIndex = -1;
    document.querySelectorAll('#inspectionTable thead th').forEach((th, i) => {
        if (th.innerText.toLowerCase().includes('result')) resultColIndex = i;
    });

    // ── Bulk column helpers ───────────────────────────────────────────────────
    function hideAllBulkCols() {
        document.querySelectorAll('.bulkCol').forEach(c => c.style.display = 'none');
        document.querySelectorAll('.printCol').forEach(c => c.style.display = 'none');
        document.querySelectorAll('.exportCol').forEach(c => c.style.display = 'none');
        if (deleteColHeader) deleteColHeader.style.display = 'none';
        if (printColHeader)  printColHeader.style.display  = 'none';
        if (exportColHeader) exportColHeader.style.display = 'none';
    }
    function showDeleteCols() {
        document.querySelectorAll('.bulkCol').forEach(c => c.style.display = '');
        document.querySelectorAll('.printCol').forEach(c => c.style.display = 'none');
        document.querySelectorAll('.exportCol').forEach(c => c.style.display = 'none');
        if (deleteColHeader) deleteColHeader.style.display = '';
        if (printColHeader)  printColHeader.style.display  = 'none';
        if (exportColHeader) exportColHeader.style.display = 'none';
    }
    function showPrintCols() {
        document.querySelectorAll('.printCol').forEach(c => c.style.display = '');
        document.querySelectorAll('.bulkCol').forEach(c => c.style.display = 'none');
        document.querySelectorAll('.exportCol').forEach(c => c.style.display = 'none');
        if (printColHeader)  printColHeader.style.display  = '';
        if (deleteColHeader) deleteColHeader.style.display = 'none';
        if (exportColHeader) exportColHeader.style.display = 'none';
    }
    function showExportCols() {
        document.querySelectorAll('.exportCol').forEach(c => c.style.display = '');
        document.querySelectorAll('.bulkCol').forEach(c => c.style.display = 'none');
        document.querySelectorAll('.printCol').forEach(c => c.style.display = 'none');
        if (exportColHeader) exportColHeader.style.display = '';
        if (deleteColHeader) deleteColHeader.style.display = 'none';
        if (printColHeader)  printColHeader.style.display  = 'none';
    }
    function uncheckAll() {
        document.querySelectorAll('.delete-cb').forEach(cb => cb.checked = false);
        document.querySelectorAll('.print-cb').forEach(cb => cb.checked = false);
        document.querySelectorAll('.export-cb').forEach(cb => cb.checked = false);
        if (selectAllDelete) selectAllDelete.checked = false;
        if (selectAllPrint)  selectAllPrint.checked  = false;
        if (selectAllExport) selectAllExport.checked = false;
    }

    // ── Detail modal ──────────────────────────────────────────────────────────
    tableBody.addEventListener('click', function (e) {
        if (e.target.closest('.bulkCol') || e.target.closest('.printCol') || e.target.closest('.exportCol')) return;
        const row = e.target.closest('tr.data-row');
        if (!row) return;
        const d = row.dataset;
        document.getElementById('modal-description').textContent     = d.description    || '—';
        document.getElementById('modal-equipment-code').textContent  = d.equipmentCode  || '—';
        document.getElementById('modal-location').textContent        = d.location       || '—';
        document.getElementById('modal-frequency').textContent       = d.frequency      || '—';
        document.getElementById('modal-inspection-date').textContent = d.inspectionDate || '—';
        document.getElementById('modal-next-inspection').textContent = d.nextInspection || '—';
        document.getElementById('modal-result').innerHTML =
            `<span class="result-badge ${d.resultClass}">${d.result}</span>`;
        if (editRecordBtn) editRecordBtn.href = `edit_inspection.php?id=${d.id}&page=${currentPage}`;
        detailModal.classList.add('open');
    });
    function closeDetail() { detailModal.classList.remove('open'); }
    closeDetailModal.addEventListener('click', closeDetail);
    closeDetailModalBtn.addEventListener('click', closeDetail);
    detailModal.addEventListener('click', e => { if (e.target === detailModal) closeDetail(); });

    // ── Filtering ──────────────────────────────────────────────────────────────
    function filterRows(resetPage = false) {
        const searchTerm = searchInput.value.toLowerCase();
        const resultTerm = resultFilter.value.toLowerCase();
        const fromVal    = dateFrom ? dateFrom.value : '';
        const toVal      = dateTo   ? dateTo.value   : '';
        const dateField  = dateRangeField ? dateRangeField.value : 'next_inspection';

        filteredRows = Array.from(tableBody.querySelectorAll('tr.data-row')).filter(row => {
            const text   = row.innerText.toLowerCase();
            const result = row.cells[resultColIndex]?.innerText.toLowerCase() || '';
            const raw    = dateField === 'inspection_date'
                ? (row.dataset.inspectionDateRaw || '')
                : (row.dataset.nextInspectionRaw || '');
            let dateMatch = true;
            if (fromVal || toVal) {
                if (!raw || raw === '0000-00-00') { dateMatch = false; }
                else {
                    if (fromVal && raw < fromVal) dateMatch = false;
                    if (toVal   && raw > toVal)   dateMatch = false;
                }
            }
            return text.includes(searchTerm) &&
                   (resultTerm === '' || result.includes(resultTerm)) &&
                   dateMatch;
        });

        if (resetPage) currentPage = 1;

        if (rangeResultBadge) {
            if (fromVal || toVal) {
                rangeResultBadge.style.display = 'inline-flex';
                rangeResultBadge.textContent   = `${filteredRows.length} result${filteredRows.length !== 1 ? 's' : ''}`;
            } else {
                rangeResultBadge.style.display = 'none';
            }
        }
        showPage();
    }

    // ── Pagination ─────────────────────────────────────────────────────────────
    function savePage() { sessionStorage.setItem('inspectionReportPage', currentPage); }

    function showPage() {
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * rowsPerPage;
        const end   = start + rowsPerPage;
        tableBody.querySelectorAll('tr.data-row').forEach(r => r.style.display = 'none');
        if (noResultsRow) noResultsRow.style.display = filteredRows.length === 0 ? '' : 'none';
        filteredRows.slice(start, end).forEach(r => r.style.display = '');
        if (currentPageInput) currentPageInput.value = currentPage;
        renderPagination();
        updateDeleteBadge();
        updatePrintBadge();
        savePage();
    }

    function renderPagination() {
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
        const p = document.getElementById('pagination');
        p.innerHTML = '';
        const prev = document.createElement('button');
        prev.textContent = '← Prev'; prev.disabled = currentPage === 1;
        prev.onclick = () => { currentPage--; showPage(); };
        const info = document.createElement('span');
        info.className = 'pagination-info';
        info.textContent = filteredRows.length === 0 ? 'No results' : `Page ${currentPage} of ${totalPages}`;
        const next = document.createElement('button');
        next.textContent = 'Next →'; next.disabled = currentPage === totalPages;
        next.onclick = () => { currentPage++; showPage(); };
        p.appendChild(prev); p.appendChild(info); p.appendChild(next);
    }

    // ── Badge updaters ─────────────────────────────────────────────────────────
    function updateDeleteBadge() {
        if (!selectedBadge) return;
        const count = document.querySelectorAll('.delete-cb:checked').length;
        selectedBadge.textContent = `${count} selected`;
        if (deleteSelectedBtn) deleteSelectedBtn.disabled = count === 0;
    }
    function updatePrintBadge() {
        if (!printSelectedBadge) return;
        printSelectedBadge.textContent = `${document.querySelectorAll('.print-cb:checked').length} selected`;
    }
    function updateExportBadge() {
        if (!exportSelectedBadge) return;
        exportSelectedBadge.textContent = `${document.querySelectorAll('.export-cb:checked').length} selected`;
    }
    tableBody.addEventListener('change', function (e) {
        if (e.target.matches('.delete-cb')) updateDeleteBadge();
        if (e.target.matches('.print-cb'))  updatePrintBadge();
        if (e.target.matches('.export-cb')) updateExportBadge();
    });

    // ── Bulk delete ────────────────────────────────────────────────────────────
    if (toggleDeleteBtn) toggleDeleteBtn.addEventListener('click', () => {
        bulkPrintBar.classList.remove('visible');
        if (bulkExportBar) bulkExportBar.classList.remove('visible');
        uncheckAll(); bulkDeleteBar.classList.add('visible'); showDeleteCols(); updateDeleteBadge();
    });
    if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', () => {
        bulkDeleteBar.classList.remove('visible'); hideAllBulkCols(); uncheckAll(); updateDeleteBadge();
    });
    if (selectAllDelete) selectAllDelete.addEventListener('change', function () {
        filteredRows.forEach(row => { const cb = row.querySelector('.delete-cb'); if (cb) cb.checked = this.checked; });
        updateDeleteBadge();
    });
    if (deleteSelectedBtn) deleteSelectedBtn.addEventListener('click', () => {
        const checked = document.querySelectorAll('.delete-cb:checked');
        if (checked.length === 0) { alert('Please select at least one item to delete.'); return; }
        deleteCountSpan.textContent = checked.length;
        deleteConfirmModal.classList.add('open');
    });
    if (confirmDeleteYesBtn) confirmDeleteYesBtn.addEventListener('click', () => {
        const checked = document.querySelectorAll('.delete-cb:checked');
        deleteHiddenInputs.innerHTML = '';
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'selected_ids[]'; input.value = cb.value;
            deleteHiddenInputs.appendChild(input);
        });
        if (currentPageInput) currentPageInput.value = currentPage;
        inspectionForm.submit();
    });
    if (confirmDeleteNoBtn) confirmDeleteNoBtn.addEventListener('click', () => deleteConfirmModal.classList.remove('open'));
    deleteConfirmModal.addEventListener('click', e => { if (e.target === deleteConfirmModal) deleteConfirmModal.classList.remove('open'); });

    // ── Bulk print ─────────────────────────────────────────────────────────────
    if (togglePrintBtn) togglePrintBtn.addEventListener('click', () => {
        bulkDeleteBar.classList.remove('visible');
        if (bulkExportBar) bulkExportBar.classList.remove('visible');
        uncheckAll(); bulkPrintBar.classList.add('visible'); showPrintCols(); updatePrintBadge();
    });
    if (cancelPrintBtn) cancelPrintBtn.addEventListener('click', () => {
        bulkPrintBar.classList.remove('visible'); hideAllBulkCols(); uncheckAll(); updatePrintBadge();
    });
    if (selectAllPrint) selectAllPrint.addEventListener('change', function () {
        filteredRows.forEach(row => { const cb = row.querySelector('.print-cb'); if (cb) cb.checked = this.checked; });
        updatePrintBadge();
    });
    if (continuePrintBtn) continuePrintBtn.addEventListener('click', () => {
        const selected = document.querySelectorAll('.print-cb:checked');
        if (selected.length === 0) { alert('Please select at least one item to print.'); return; }
        window.selectedIds = Array.from(selected).map(cb => cb.value);
        stickerModal.classList.add('open');
    });
    if (closeStickerBtn) closeStickerBtn.addEventListener('click', () => {
        stickerModal.classList.remove('open'); bulkPrintBar.classList.remove('visible');
        hideAllBulkCols(); uncheckAll(); updatePrintBadge();
    });
    stickerModal.addEventListener('click', e => { if (e.target === stickerModal) stickerModal.classList.remove('open'); });

    function openStickerPrint(ids, orientation) {
        if (!ids || ids.length === 0) return;
        window.open(`sticker_print_inspection.php?ids=${ids.join(',')}&orientation=${orientation}`, '_blank');
        stickerModal.classList.remove('open'); bulkPrintBar.classList.remove('visible');
        hideAllBulkCols(); uncheckAll(); updatePrintBadge();
    }
    const horizontalBtn = document.getElementById('horizontalStickerBtn');
    const verticalBtn   = document.getElementById('verticalStickerBtn');
    if (horizontalBtn) horizontalBtn.addEventListener('click', () => openStickerPrint(window.selectedIds, 'horizontal'));
    if (verticalBtn)   verticalBtn.addEventListener('click',   () => openStickerPrint(window.selectedIds, 'vertical'));

    searchInput.addEventListener('input',   () => filterRows(true));
    resultFilter.addEventListener('change', () => filterRows(true));

    // ══════════════════════════════════════════════════════════════════════════
    //  EXPORT LOGIC
    // ══════════════════════════════════════════════════════════════════════════

    const EXPORT_COLUMNS = [
        { label: 'No.',              key: null              },
        { label: 'Description',      key: 'description'     },
        { label: 'Equipment Code',   key: 'equipmentCode'   },
        { label: 'Location',         key: 'location'        },
        { label: 'Inspection Date',  key: 'inspectionDate'  },
        { label: 'Next Inspection',  key: 'nextInspection'  },
        { label: 'Frequency',        key: 'frequency'       },
        { label: 'Result',           key: 'result'          },
    ];

    const sigFields = [
        { name: document.getElementById('sig1name'), title: document.getElementById('sig1title') },
        { name: document.getElementById('sig2name'), title: document.getElementById('sig2title') },
        { name: document.getElementById('sig3name'), title: document.getElementById('sig3title') },
        { name: document.getElementById('sig4name'), title: document.getElementById('sig4title') },
    ];
    const revFields = [
        { code: document.getElementById('rev1code'), date: document.getElementById('rev1date'), by: document.getElementById('rev1by'), desc: document.getElementById('rev1desc'), appr1: document.getElementById('rev1appr1'), appr2: document.getElementById('rev1appr2') },
        { code: document.getElementById('rev2code'), date: document.getElementById('rev2date'), by: document.getElementById('rev2by'), desc: document.getElementById('rev2desc'), appr1: document.getElementById('rev2appr1'), appr2: document.getElementById('rev2appr2') },
        { code: document.getElementById('rev3code'), date: document.getElementById('rev3date'), by: document.getElementById('rev3by'), desc: document.getElementById('rev3desc'), appr1: document.getElementById('rev3appr1'), appr2: document.getElementById('rev3appr2') },
    ];

    const SIG_SESSION_KEY = 'inspectionSignatories';
    const REV_SESSION_KEY = 'inspectionRevisions';

    function saveSigSession() {
        try { sessionStorage.setItem(SIG_SESSION_KEY, JSON.stringify(sigFields.map(f => ({ name: f.name ? f.name.value : '', title: f.title ? f.title.value : '' })))); } catch(e) {}
    }
    function loadSigSession() {
        try {
            const data = JSON.parse(sessionStorage.getItem(SIG_SESSION_KEY));
            if (!Array.isArray(data)) return;
            data.forEach((d, i) => {
                if (!sigFields[i]) return;
                if (sigFields[i].name  && d.name  !== undefined) sigFields[i].name.value  = d.name;
                if (sigFields[i].title && d.title !== undefined) sigFields[i].title.value = d.title;
            });
        } catch(e) {}
    }
    function saveRevSession() {
        try { sessionStorage.setItem(REV_SESSION_KEY, JSON.stringify(revFields.map(f => ({ code: f.code ? f.code.value : '', date: f.date ? f.date.value : '', by: f.by ? f.by.value : '', desc: f.desc ? f.desc.value : '', appr1: f.appr1 ? f.appr1.value : '', appr2: f.appr2 ? f.appr2.value : '' })))); } catch(e) {}
    }
    function loadRevSession() {
        try {
            const data = JSON.parse(sessionStorage.getItem(REV_SESSION_KEY));
            if (!Array.isArray(data)) return;
            data.forEach((d, i) => {
                if (!revFields[i]) return;
                const f = revFields[i];
                if (f.code  && d.code  !== undefined) f.code.value  = d.code;
                if (f.date  && d.date  !== undefined) f.date.value  = d.date;
                if (f.by    && d.by    !== undefined) f.by.value    = d.by;
                if (f.desc  && d.desc  !== undefined) f.desc.value  = d.desc;
                if (f.appr1 && d.appr1 !== undefined) f.appr1.value = d.appr1;
                if (f.appr2 && d.appr2 !== undefined) f.appr2.value = d.appr2;
            });
        } catch(e) {}
    }
    sigFields.forEach(f => {
        if (f.name)  f.name.addEventListener('input',  saveSigSession);
        if (f.title) f.title.addEventListener('input', saveSigSession);
    });
    revFields.forEach(f => {
        ['code','date','by','desc','appr1','appr2'].forEach(k => { if (f[k]) f[k].addEventListener('input', saveRevSession); });
    });
    loadSigSession();
    loadRevSession();

    function getSignatories() {
        return sigFields.map(f => ({ name: f.name ? f.name.value.trim() || '—' : '—', title: f.title ? f.title.value.trim() || '—' : '—' }));
    }
    function getRevisions() {
        return revFields.map(f => ({ code: f.code ? f.code.value.trim() : '', date: f.date ? f.date.value.trim() : '', by: f.by ? f.by.value.trim() : '', desc: f.desc ? f.desc.value.trim() : '', appr1: f.appr1 ? f.appr1.value.trim() : '', appr2: f.appr2 ? f.appr2.value.trim() : '' }));
    }

    function buildColIndexMap() {
        const map = {};
        Array.from(document.querySelectorAll('#inspectionTable thead th')).forEach((th, i) => {
            const t = th.textContent.trim().toLowerCase();
            if (t.includes('description'))        map.description    = i;
            if (t.includes('equipment'))          map.equipmentCode  = i;
            if (t.includes('location'))           map.location       = i;
            if (t === 'inspection date')          map.inspectionDate = i;
            if (t.includes('next inspection'))    map.nextInspection = i;
            if (t.includes('frequency'))          map.frequency      = i;
            if (t.includes('result'))             map.result         = i;
        });
        return map;
    }
    const colIndexMap = buildColIndexMap();

    function sanitizeForPdf(str) {
        if (!str) return '';
        return str
            .replace(/\u03A9/g, 'Ohm').replace(/\u03C9/g, 'ohm')
            .replace(/\u00A9/g, '(c)').replace(/\u00AE/g, '(R)')
            .replace(/\u2122/g, '(TM)').replace(/\u00B5/g, 'u')
            .replace(/\u00B0/g, 'deg').replace(/\u00B1/g, '+/-');
    }

    function getCellText(row, key) {
        const idx = colIndexMap[key];
        if (idx !== undefined && row.cells[idx]) return sanitizeForPdf(row.cells[idx].textContent.trim());
        const d = row.dataset;
        const txt = document.createElement('textarea');
        txt.innerHTML = d[key] || '';
        return sanitizeForPdf(txt.value);
    }

    function getExportData() {
        const rows = window.exportRowIds
            ? filteredRows.filter(row => window.exportRowIds.includes(row.dataset.id))
            : filteredRows;
        return rows.map((row, i) => EXPORT_COLUMNS.map(col => col.key === null ? (i + 1) : getCellText(row, col.key)));
    }

    function buildFilterNote() {
        const parts = [];
        if (searchInput.value.trim()) parts.push(`Search: "${searchInput.value.trim()}"`);
        if (resultFilter.value)       parts.push(`Result: ${resultFilter.value}`);
        if (dateFrom && (dateFrom.value || dateTo.value)) {
            const fieldLabel = (dateRangeField && dateRangeField.value === 'inspection_date') ? 'Inspection Date' : 'Next Inspection';
            parts.push(`${fieldLabel}: ${dateFrom.value || '…'} → ${dateTo.value || '…'}`);
        }
        const rowCount = `${filteredRows.length} row${filteredRows.length !== 1 ? 's' : ''}`;
        return parts.length ? `${rowCount} · Filters: ${parts.join(', ')}` : `${rowCount} · No active filters`;
    }

    // ── Bulk export ────────────────────────────────────────────────────────────
    if (exportReportBtn) exportReportBtn.addEventListener('click', () => {
        bulkDeleteBar.classList.remove('visible');
        bulkPrintBar.classList.remove('visible');
        uncheckAll(); bulkExportBar.classList.add('visible'); showExportCols(); updateExportBadge();
    });
    if (selectAllExport) selectAllExport.addEventListener('change', function() {
        filteredRows.forEach(row => { const cb = row.querySelector('.export-cb'); if (cb) cb.checked = this.checked; });
        updateExportBadge();
    });
    if (cancelExportSelBtn) cancelExportSelBtn.addEventListener('click', () => {
        bulkExportBar.classList.remove('visible'); hideAllBulkCols(); uncheckAll(); updateExportBadge();
    });
    if (continueExportBtn) continueExportBtn.addEventListener('click', () => {
        const checked = document.querySelectorAll('.export-cb:checked');
        window.exportRowIds = checked.length === 0 ? null : Array.from(checked).map(cb => cb.value);
        bulkExportBar.classList.remove('visible'); hideAllBulkCols(); uncheckAll();
        exportFilterNote.textContent = buildFilterNote();
        exportModal.classList.add('open');
    });

    function closeExport() { exportModal.classList.remove('open'); }
    closeExportModal.addEventListener('click', closeExport);
    cancelExportBtn.addEventListener('click',  closeExport);
    exportModal.addEventListener('click', e => { if (e.target === exportModal) closeExport(); });

    // ── Excel export ───────────────────────────────────────────────────────────
    exportExcelBtn.addEventListener('click', () => {
        if (typeof XLSX === 'undefined') { alert('Excel library still loading, please try again.'); return; }
        const ws = XLSX.utils.aoa_to_sheet([EXPORT_COLUMNS.map(c => c.label), ...getExportData()]);
        ws['!cols'] = [{wch:5},{wch:32},{wch:16},{wch:20},{wch:14},{wch:14},{wch:14},{wch:18}];
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Inspection Report');
        XLSX.writeFile(wb, `inspection_report_${new Date().toISOString().slice(0,10)}.xlsx`);
        closeExport();
    });

    // ── PDF export ─────────────────────────────────────────────────────────────
    exportPdfBtn.addEventListener('click', async () => {
        if (typeof window.jspdf === 'undefined') { alert('PDF library still loading, please try again.'); return; }
        const { jsPDF } = window.jspdf;
        const doc   = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        const pageW = doc.internal.pageSize.getWidth();
        const pageH = doc.internal.pageSize.getHeight();
        const dateStr = new Date().toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });

        const HDR_H = 28; const LOGO_MAX_W = 20; const LOGO_X = 6; const TEXT_X = LOGO_X + LOGO_MAX_W + 5;
        doc.setFillColor(5, 48, 79); doc.rect(0, 0, pageW, HDR_H, 'F');

        try {
            const logoImg = await new Promise((resolve, reject) => {
                const img = new Image(); img.crossOrigin = 'anonymous';
                img.onload = () => { const canvas = document.createElement('canvas'); canvas.width = img.naturalWidth; canvas.height = img.naturalHeight; canvas.getContext('2d').drawImage(img, 0, 0); resolve({ dataUrl: canvas.toDataURL('image/png'), w: img.naturalWidth, h: img.naturalHeight }); };
                img.onerror = reject; img.src = 'images/shindengen-logo2.png';
            });
            const LOGO_W = LOGO_MAX_W; const LOGO_H = LOGO_MAX_W * (logoImg.h / logoImg.w);
            doc.addImage(logoImg.dataUrl, 'PNG', LOGO_X, (HDR_H - LOGO_H) / 2, LOGO_W, LOGO_H);
        } catch(e) {}

        doc.setTextColor(255,255,255); doc.setFontSize(15); doc.setFont('helvetica','bold');
        doc.text('Inspection Report', TEXT_X, 10);
        doc.setFontSize(9.5); doc.setFont('helvetica','normal'); doc.setTextColor(180,210,235);
        doc.text('Reference: PC2-9710 Calibration Control Procedure', TEXT_X, 18);
        doc.setFontSize(9); doc.setTextColor(200,225,245);
        doc.text(`Generated: ${dateStr}`, pageW - 8, 18, { align: 'right' });

        doc.autoTable({
            head: [EXPORT_COLUMNS.map(c => c.label)],
            body: getExportData(),
            startY: 32,
            margin: { left: 8, right: 8, bottom: 10 },
            theme: 'grid',
            rowPageBreak: 'avoid',
            tableLineColor: [180, 195, 210], tableLineWidth: 0.25,
            styles:             { fontSize: 6.8, cellPadding: { top:2.5, bottom:2.5, left:3, right:3 }, overflow: 'linebreak', font: 'helvetica', textColor: [28,43,58], lineColor: [180,195,210], lineWidth: 0.25 },
            headStyles:         { fillColor: [26,144,217], textColor: [255,255,255], fontStyle: 'bold', fontSize: 6.5, lineColor: [100,160,210], lineWidth: 0.25 },
            alternateRowStyles: { fillColor: [248,250,252] },
            columnStyles: {
                0: { cellWidth: 11, halign: 'center' },
                1: { cellWidth: 75 },
                2: { cellWidth: 38 },
                3: { cellWidth: 46 },
                4: { cellWidth: 24 },
                5: { cellWidth: 24 },
                6: { cellWidth: 24 },
                7: { cellWidth: 39, halign: 'center' },
            },
            didParseCell: function(data) {
                if (data.section === 'body' && data.column.index === 7) {
                    const v = (data.cell.raw || '').toString().toLowerCase();
                    if      (v === 'good')                  { data.cell.styles.textColor = [22,101,52];   data.cell.styles.fillColor = [220,252,231]; }
                    else if (v === 'no good' || v === 'missing') { data.cell.styles.textColor = [185,28,28]; data.cell.styles.fillColor = [253,232,232]; }
                    else if (v === 'for disposal')          { data.cell.styles.textColor = [146,64,14];   data.cell.styles.fillColor = [254,243,220]; }
                    else if (v === 'safekeep')              { data.cell.styles.textColor = [109,40,217];  data.cell.styles.fillColor = [237,233,254]; }
                    else if (v === 'not yet inspected')     { data.cell.styles.textColor = [107,114,128]; data.cell.styles.fillColor = [243,244,246]; }
                }
            },
            didDrawPage: function() {
                const pn = doc.internal.getCurrentPageInfo().pageNumber;
                doc.setFontSize(7); doc.setTextColor(150);
                doc.text(`Page ${pn}`, pageW / 2, pageH - 5, { align: 'center' });
            },
        });

        // ── Signatories & Revision History ─────────────────────────────────────
        const signatories = getSignatories(); const revisions = getRevisions();
        const marginL = 8; const usableW = pageW - marginL * 2;
        const SIG_H = 18; const HEAD_H = 7; const ROW_H = 5;
        const nRows = revisions.length; const bodyH = HEAD_H + ROW_H * nRows;
        const TOTAL_H = SIG_H + bodyH + 6;

        if (doc.lastAutoTable.finalY + TOTAL_H > pageH - 10) doc.addPage();
        doc.setPage(doc.internal.getNumberOfPages());

        const sigTop = pageH - TOTAL_H - 8; const colW = usableW / 4; const lh = 2.8;
        doc.setDrawColor(200,210,220); doc.setLineWidth(0.3);
        doc.line(marginL, sigTop - 3, pageW - marginL, sigTop - 3);
        doc.setFontSize(6.5); doc.setFont('helvetica','bold'); doc.setTextColor(5,48,79);
        doc.text('Prepared / Checked / Approved by:', marginL, sigTop + 2.5);

        signatories.forEach((sig, i) => {
            const cx = marginL + colW * i + colW / 2;
            doc.setDrawColor(80,100,120); doc.setLineWidth(0.35);
            doc.line(cx - colW * 0.38, sigTop + 13, cx + colW * 0.38, sigTop + 13);
            doc.setFont('helvetica','bold'); doc.setFontSize(6.5); doc.setTextColor(28,43,58);
            doc.text(sig.name, cx, sigTop + 12, { align: 'center', maxWidth: colW - 4 });
            doc.setFont('helvetica','normal'); doc.setFontSize(5.5); doc.setTextColor(100,120,138);
            doc.text(sig.title, cx, sigTop + 17, { align: 'center', maxWidth: colW - 4 });
        });

        const revTop = sigTop + SIG_H + 2;
        const wDept = 28; const pairGap = wDept;
        const leftW = (pageW - 2 * marginL) - pairGap - wDept;
        const wRev = 10; const wDate = 18; const wBy = 26; const wSec = 28;
        const wDesc = leftW - wRev - wDate - wBy - wSec;
        const xRev = marginL; const xDate = xRev + wRev; const xBy = xDate + wDate;
        const xDesc = xBy + wBy; const xSec = xDesc + wDesc; const xDept = marginL + leftW + pairGap;

        doc.setDrawColor(180,195,210); doc.setLineWidth(0.3);
        doc.rect(xRev, revTop, leftW, bodyH);
        doc.setFillColor(26,144,217); doc.rect(xRev, revTop, leftW, HEAD_H, 'F');

        const hCols = [
            { label: 'Rev.',                             x: xRev,  w: wRev  },
            { label: 'Rev. Date',                        x: xDate, w: wDate },
            { label: 'Revised By',                       x: xBy,   w: wBy   },
            { label: 'Nature / Description of Revision', x: xDesc, w: wDesc },
            { label: 'Approved by\n(Section Mgr.)',      x: xSec,  w: wSec  },
        ];
        doc.setFont('helvetica','bold'); doc.setFontSize(5); doc.setTextColor(255,255,255);
        hCols.forEach((col, ci) => {
            if (ci > 0) { doc.setDrawColor(255,255,255); doc.setLineWidth(0.2); doc.line(col.x, revTop, col.x, revTop + bodyH); }
            const lines = col.label.split('\n');
            const startY = revTop + 2.5 + (lines.length > 1 ? 0 : lh * 0.5);
            lines.forEach((line, li) => doc.text(line, col.x + col.w / 2, startY + li * lh, { align: 'center', maxWidth: col.w - 2 }));
        });

        revisions.forEach((rev, ri) => {
            const rowY = revTop + HEAD_H + ri * ROW_H; const textY = rowY + ROW_H * 0.65;
            if (ri % 2 === 1) { doc.setFillColor(248,250,252); doc.rect(xRev, rowY, wRev + wDate + wBy + wDesc, ROW_H, 'F'); }
            doc.setDrawColor(220,228,236); doc.setLineWidth(0.2);
            doc.line(xRev, rowY + ROW_H, xSec, rowY + ROW_H);
            doc.setFont('helvetica','normal'); doc.setFontSize(5.5); doc.setTextColor(28,43,58);
            doc.text(String(rev.code || ''), xRev + wRev / 2, textY, { align: 'center' });
            doc.text(rev.date || '', xDate + 2, textY, { maxWidth: wDate - 4 });
            doc.text(rev.by   || '', xBy   + 2, textY, { maxWidth: wBy   - 4 });
            doc.text(rev.desc || '', xDesc + 2, textY, { maxWidth: wDesc - 4 });
            [xDate, xBy, xDesc].forEach(lx => { doc.setDrawColor(200,210,220); doc.setLineWidth(0.2); doc.line(lx, rowY, lx, rowY + ROW_H); });
        });

        doc.setFillColor(255,255,255); doc.rect(xSec, revTop + HEAD_H, wSec, ROW_H * nRows, 'F');
        try {
            const sig1Img = await new Promise((resolve, reject) => {
                const img = new Image(); img.crossOrigin = 'anonymous';
                img.onload = () => { const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight; c.getContext('2d').drawImage(img,0,0); resolve({dataUrl:c.toDataURL('image/png'),w:img.naturalWidth,h:img.naturalHeight}); };
                img.onerror = reject; img.src = 'images/Signature-1.png';
            });
            const r1 = Math.min((wSec-6)*0.6/(sig1Img.w/3.7795),(ROW_H*nRows-4)*0.6/(sig1Img.h/3.7795));
            const s1w=(sig1Img.w/3.7795)*r1; const s1h=(sig1Img.h/3.7795)*r1;
            doc.addImage(sig1Img.dataUrl,'PNG',xSec+(wSec-s1w)/2,revTop+HEAD_H+(ROW_H*nRows-s1h)/2,s1w,s1h);
        } catch(e) {}

        doc.setDrawColor(180,195,210); doc.setLineWidth(0.3);
        doc.rect(xDept, revTop, wDept, bodyH);
        doc.setFillColor(26,144,217); doc.rect(xDept, revTop, wDept, HEAD_H, 'F');
        doc.setFont('helvetica','bold'); doc.setFontSize(5); doc.setTextColor(255,255,255);
        'Approved by\n(Dept. Mgr.)'.split('\n').forEach((line,li) => doc.text(line, xDept+wDept/2, revTop+2.5+li*lh, {align:'center',maxWidth:wDept-2}));

        const deptMergeH = ROW_H * (nRows - 1);
        doc.setFillColor(255,255,255); doc.rect(xDept, revTop + HEAD_H, wDept, deptMergeH, 'F');
        try {
            const sig2Img = await new Promise((resolve, reject) => {
                const img = new Image(); img.crossOrigin = 'anonymous';
                img.onload = () => { const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight; c.getContext('2d').drawImage(img,0,0); resolve({dataUrl:c.toDataURL('image/png'),w:img.naturalWidth,h:img.naturalHeight}); };
                img.onerror = reject; img.src = 'images/Signature-2.png';
            });
            const r2 = Math.min((wDept-4)/(sig2Img.w/3.7795),(deptMergeH-2)/(sig2Img.h/3.7795));
            const s2w=(sig2Img.w/3.7795)*r2; const s2h=(sig2Img.h/3.7795)*r2;
            doc.addImage(sig2Img.dataUrl,'PNG',xDept+(wDept-s2w)/2,revTop+HEAD_H+(deptMergeH-s2h)/2,s2w,s2h);
        } catch(e) {}

        doc.setDrawColor(200,210,220); doc.setLineWidth(0.2);
        doc.line(xDept, revTop+HEAD_H+deptMergeH, xDept+wDept, revTop+HEAD_H+deptMergeH);
        const row3Y = revTop + HEAD_H + ROW_H * (nRows - 1);
        doc.setFillColor(248,250,252); doc.rect(xDept, row3Y, wDept, ROW_H, 'F');
        doc.setFont('helvetica','bold'); doc.setFontSize(4.5); doc.setTextColor(80,100,120);
        doc.text('QA Dept. Mngr.', xDept+wDept/2, row3Y+ROW_H*0.65, {align:'center',maxWidth:wDept-4});

        doc.save(`inspection_report_${new Date().toISOString().slice(0,10)}.pdf`);
        closeExport();
    });

    // ── Init ──────────────────────────────────────────────────────────────────
    filterRows();
    hideAllBulkCols();
});