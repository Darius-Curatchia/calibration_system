window.addEventListener('DOMContentLoaded', () => {
    const isGuest = IS_GUEST;

    // ── Cal type normalizer ───────────────────────────────────────────────────
    function normalizeCalType(raw) {
        if (!raw) return '—';
        const v = raw.trim();
        if (v === 'Internal') return 'Internal Calibration';
        if (v === 'External') return 'External Calibration';
        return v;
    }

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
    const qStart           = Math.floor(todayM / 3) * 3;
    const qsDate           = new Date(todayY, qStart, 1);
    const qeDate           = new Date(todayY, qStart + 3, 0);
    const thisQuarterStart = `${qsDate.getFullYear()}-${pad(qsDate.getMonth() + 1)}-01`;
    const thisQuarterEnd   = `${qeDate.getFullYear()}-${pad(qeDate.getMonth() + 1)}-${pad(qeDate.getDate())}`;
    const thisYearStart = `${todayY}-01-01`;
    const thisYearEnd   = `${todayY}-12-31`;

    // ── Next-cal chip coloring ────────────────────────────────────────────────
    document.querySelectorAll('.next-cal-cell').forEach(td => {
        const raw  = td.dataset.raw;
        const text = td.textContent.trim();
        if (!raw || raw === '0000-00-00' || !text) return;
        let chipClass = 'cal-default';
        if      (raw < todayStr)                               { chipClass = 'cal-overdue';    }
        else if (raw >= thisMonthStart && raw <= thisMonthEnd) { chipClass = 'cal-this-month'; }
        else if (raw >= nextMonthStart && raw <= nextMonthEnd) { chipClass = 'cal-next-month'; }
        td.innerHTML = `<span class="cal-chip ${chipClass}">${text}</span>`;
    });

    // ── Calibrator chip ───────────────────────────────────────────────────────
    document.querySelectorAll('.calibrator-cell').forEach(td => {
        const val     = td.textContent.trim();
        const calType = (td.closest('tr')?.dataset?.calType || '').toLowerCase();
        if (!val) return;
        if (calType.includes('external') || val.toUpperCase() !== 'SDP') {
            td.innerHTML = `<span class="calibrator-external">${val}</span>`;
        }
    });

    // ── Sort state ─────────────────────────────────────────────────────────────
    let sortKey = null;   // 'nextCalRaw' | 'calDateRaw' | null
    let sortDir = 'asc';  // 'asc' | 'desc'
    let _userPickedSort = false;

    const SORTABLE_COLS = {
        'calDate': 'calDateRaw',
        'nextCal': 'nextCalRaw',
    };

    // ── Parse "dd-Mon-yy" → "YYYY-MM-DD" for correct lexicographic sort ───────
    function parseDDMonyy(str) {
        if (!str) return '';
        const months = {Jan:'01',Feb:'02',Mar:'03',Apr:'04',May:'05',Jun:'06',
                        Jul:'07',Aug:'08',Sep:'09',Oct:'10',Nov:'11',Dec:'12'};
        const m = str.match(/^(\d{2})-([A-Za-z]{3})-(\d{2})$/);
        if (!m) return '';
        const day = m[1];
        const mon = months[m[2]] || '';
        if (!mon) return '';
        const yr2 = parseInt(m[3], 10);
        const yr4 = yr2 >= 50 ? 1900 + yr2 : 2000 + yr2;
        return `${yr4}-${mon}-${day}`;
    }

    // ── Inject calDateRaw onto each data row ──────────────────────────────────
    document.querySelectorAll('tr.data-row').forEach(row => {
        const calDateFormatted = row.dataset.calDate || '';
        if (calDateFormatted) {
            const parsed = parseDDMonyy(calDateFormatted);
            if (parsed) row.dataset.calDateRaw = parsed;
        }
    });

    // ── Sort helper ────────────────────────────────────────────────────────────
    function sortFilteredRows(rows) {
        if (!sortKey) return rows;
        return [...rows].sort((a, b) => {
            const av = (a.dataset[sortKey] || '').trim();
            const bv = (b.dataset[sortKey] || '').trim();
            const aEmpty = !av || av === '0000-00-00';
            const bEmpty = !bv || bv === '0000-00-00';
            if (aEmpty && bEmpty) return 0;
            if (aEmpty) return 1;
            if (bEmpty) return -1;
            if (av < bv) return sortDir === 'asc' ? -1 : 1;
            if (av > bv) return sortDir === 'asc' ?  1 : -1;
            return 0;
        });
    }

    // ── Inject sort UI into table headers ─────────────────────────────────────
    function initSortHeaders() {
        const ths = document.querySelectorAll('#samplesTable thead th');
        ths.forEach(th => {
            const text = th.textContent.trim();
            let colKey = null;
            if (text === 'Cal Date') colKey = 'calDate';
            if (text === 'Next Cal') colKey = 'nextCal';
            if (!colKey) return;

            th.dataset.sortCol  = colKey;
            th.style.cursor     = 'pointer';
            th.style.userSelect = 'none';
            th.style.whiteSpace = 'nowrap';
            th.innerHTML = `<span class="th-sort-label">${text}</span><span class="th-sort-icon" aria-hidden="true"></span>`;

            th.addEventListener('click', () => {
                const dsKey = SORTABLE_COLS[colKey];
                if (sortKey === dsKey) {
                    sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    sortKey = dsKey;
                    sortDir = 'asc';
                }
                _userPickedSort = true;
                updateSortIcons();
                filterRows(true);
            });
        });
        updateSortIcons();
    }

    function updateSortIcons() {
        document.querySelectorAll('#samplesTable thead th[data-sort-col]').forEach(th => {
            const dsKey = SORTABLE_COLS[th.dataset.sortCol];
            const icon  = th.querySelector('.th-sort-icon');
            if (!icon) return;
            if (sortKey === dsKey) {
                icon.textContent    = sortDir === 'asc' ? ' ↑' : ' ↓';
                th.style.background = '#0a4570';
                th.style.color      = '#ffffff';
            } else {
                icon.textContent    = ' ⇅';
                th.style.background = '';
                th.style.color      = '';
            }
        });
    }

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
        if (dateRangeField) dateRangeField.value = 'next_cal';
        presetChips.forEach(c => c.classList.remove('active'));
        rangeActiveDot.style.display   = 'none';
        rangeResultBadge.style.display = 'none';
        toggleDateRangeBtn.classList.remove('btn-accent');
        toggleDateRangeBtn.classList.add('btn-muted');
        if (!_userPickedSort) { sortKey = null; updateSortIcons(); }
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
                if (dateRangeField) dateRangeField.value = 'next_cal';
                onRangeChanged();
                if (!_userPickedSort) { sortKey = null; updateSortIcons(); }
                filterRows(true);
                return;
            }
            presetChips.forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            if (dateRangeField) dateRangeField.value = 'next_cal';
            switch (chip.dataset.preset) {
                case 'overdue':       dateFrom.value = '2000-01-01';  dateTo.value = todayStr;       break;
                case 'thismonth':     dateFrom.value = thisMonthStart; dateTo.value = thisMonthEnd;   break;
                case 'nextmonth':     dateFrom.value = nextMonthStart; dateTo.value = nextMonthEnd;   break;
                case 'lastmonth-cal':
                    dateFrom.value = lastMonthStart; dateTo.value = lastMonthEnd;
                    if (dateRangeField) dateRangeField.value = 'cal_date';
                    break;
                case 'quarter':   dateFrom.value = thisQuarterStart; dateTo.value = thisQuarterEnd;  break;
                case 'year':      dateFrom.value = thisYearStart;    dateTo.value = thisYearEnd;     break;
            }
            onRangeChanged();
            if (!_userPickedSort) {
                sortKey = chip.dataset.preset === 'lastmonth-cal' ? 'calDateRaw' : 'nextCalRaw';
                sortDir = chip.dataset.preset === 'lastmonth-cal' ? 'desc' : 'asc';
                updateSortIcons();
            }
            filterRows(true);
        });
    });

    // ── Element refs ──────────────────────────────────────────────────────────
    const tableBody           = document.querySelector('#samplesTable tbody');
    const noResultsRow        = document.getElementById('noResultsRow');
    const searchInput         = document.getElementById('searchInput');
    const statusFilter        = document.getElementById('statusFilter');
    const calibratorFilter    = document.getElementById('calibratorFilter');
    const calTypeFilter       = document.getElementById('calTypeFilter');
    const selectedBadge       = document.getElementById('selectedCountBadge');
    const toggleDeleteBtn     = document.getElementById('toggleDeleteBtn');
    const bulkDeleteBar       = document.getElementById('bulkDeleteBar');
    const deleteSelectedBtn   = document.getElementById('deleteSelectedBtn');
    const cancelDeleteBtn     = document.getElementById('cancelDeleteBtn');
    const selectAllDelete     = document.getElementById('selectAllDelete');
    const deleteColHeader     = document.getElementById('deleteColHeader');
    const currentPageInput    = document.getElementById('currentPageInput');
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
    const deleteHiddenInputs  = document.getElementById('deleteHiddenInputs');
    const samplesForm         = document.getElementById('samplesForm');
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
    let currentPage  = sessionStorage.getItem('standardSamplesPage')
                       ? parseInt(sessionStorage.getItem('standardSamplesPage'))
                       : CURRENT_PAGE_INIT;
    let filteredRows = [];

    let statusColIndex, calibratorColIndex;
    document.querySelectorAll('#samplesTable thead th').forEach((th, i) => {
        const t = th.innerText.toLowerCase();
        if (t.includes('status'))     statusColIndex = i;
        if (t.includes('calibrator')) calibratorColIndex = i;
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

    // ── Row click → detail modal ──────────────────────────────────────────────
    tableBody.addEventListener('click', function (e) {
        if (e.target.closest('.bulkCol') || e.target.closest('.printCol') || e.target.closest('.exportCol')) return;
        const row = e.target.closest('tr.data-row');
        if (!row) return;
        const d = row.dataset;
        document.getElementById('modal-description').textContent    = d.description   || '—';
        document.getElementById('modal-equipment-code').textContent = d.equipmentCode || '—';
        document.getElementById('modal-model-maker').textContent    = d.modelMaker    || '—';
        document.getElementById('modal-serial-no').textContent      = d.serialNo      || '—';
        document.getElementById('modal-model').textContent          = d.model         || '—';
        document.getElementById('modal-maker').textContent          = d.maker         || '—';
        document.getElementById('modal-location').textContent       = d.location      || '—';
        document.getElementById('modal-section').textContent        = d.section       || '—';
        document.getElementById('modal-manager').textContent        = d.manager       || '—';
        document.getElementById('modal-reference').textContent      = d.reference     || '—';
        document.getElementById('modal-calibrator').textContent     = d.calibrator    || '—';
        document.getElementById('modal-env-type').textContent       = d.envType       || '—';
        const remarksEl = document.getElementById('modal-remarks');
        if (remarksEl) {
            const rv = d.remarks || '';
            remarksEl.textContent = rv || 'No remarks.';
            remarksEl.classList.toggle('has-value', !!rv);
        }
        document.getElementById('modal-cal-type').textContent = normalizeCalType(d.calType);
        document.getElementById('modal-cal-date').textContent       = d.calDate       || '—';
        document.getElementById('modal-next-cal').textContent       = d.nextCal       || '—';
        document.getElementById('modal-frequency').textContent      = d.frequency     || '—';
        document.getElementById('modal-status').innerHTML =
            `<span class="status-badge ${d.statusClass}">${d.status}</span>`;
        if (editRecordBtn) editRecordBtn.href = `edit_standard_sample.php?id=${d.id}&page=${currentPage}`;
        detailModal.classList.add('open');
    });

    function closeDetail() { detailModal.classList.remove('open'); }
    closeDetailModal.addEventListener('click', closeDetail);
    closeDetailModalBtn.addEventListener('click', closeDetail);
    detailModal.addEventListener('click', e => { if (e.target === detailModal) closeDetail(); });

    // ── Filtering ─────────────────────────────────────────────────────────────
    function filterRows(resetPage = false) {
        const searchTerm     = searchInput.value.toLowerCase();
        const statusTerm     = statusFilter.value.toLowerCase();
        const calibratorTerm = calibratorFilter.value.toLowerCase();
        const calTypeTerm    = calTypeFilter ? calTypeFilter.value.toLowerCase() : '';
        const fromVal        = dateFrom ? dateFrom.value : '';
        const toVal          = dateTo   ? dateTo.value   : '';
        const dateField      = dateRangeField ? dateRangeField.value : 'next_cal';

        const raw = Array.from(tableBody.querySelectorAll('tr.data-row')).filter(row => {
            const text       = row.innerText.toLowerCase();
            const status     = row.cells[statusColIndex]?.innerText.toLowerCase() || '';
            const calibrator = row.cells[calibratorColIndex]?.innerText.toLowerCase() || '';
            const calType    = (row.dataset.calType || '').toLowerCase();
            let dateMatch = true;
            if (fromVal || toVal) {
                const rowDateVal = dateField === 'cal_date'
                    ? (row.dataset.calDateRaw || '')
                    : (row.dataset.nextCalRaw || '');
                if (!rowDateVal || rowDateVal === '0000-00-00') { dateMatch = false; }
                else {
                    if (fromVal && rowDateVal < fromVal) dateMatch = false;
                    if (toVal   && rowDateVal > toVal)   dateMatch = false;
                }
            }
            return text.includes(searchTerm) &&
                   (statusTerm === '' || status.includes(statusTerm)) &&
                   (calibratorTerm === '' || calibrator.includes(calibratorTerm)) &&
                   (calTypeTerm === '' || calType === calTypeTerm) &&
                   dateMatch;
        });

        // ── Apply sort ────────────────────────────────────────────────────────
        filteredRows = sortFilteredRows(raw);

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
    function savePage() { sessionStorage.setItem('standardSamplesPage', currentPage); }

    function showPage() {
        const start = (currentPage - 1) * rowsPerPage;
        const end   = start + rowsPerPage;
        tableBody.querySelectorAll('tr.data-row').forEach(r => r.style.display = 'none');
        if (noResultsRow) noResultsRow.style.display = filteredRows.length === 0 ? '' : 'none';

        // Re-append rows in sorted order so DOM matches filteredRows sequence
        if (filteredRows.length > 0) {
            filteredRows.forEach(r => tableBody.appendChild(r));
            filteredRows.slice(start, end).forEach(r => r.style.display = '');
        }

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
        samplesForm.submit();
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

    function openStickerPrint(ids, size) {
        if (!ids || ids.length === 0) return;
        window.open(`standard_sticker_print.php?ids=${ids.join(',')}&size=${size}`, '_blank');
        stickerModal.classList.remove('open'); bulkPrintBar.classList.remove('visible');
        hideAllBulkCols(); uncheckAll(); updatePrintBadge();
    }
    const smallStickerBtn = document.getElementById('smallStickerBtn');
    const bigStickerBtn   = document.getElementById('bigStickerBtn');
    if (smallStickerBtn) smallStickerBtn.addEventListener('click', () => openStickerPrint(window.selectedIds, 'small'));
    if (bigStickerBtn)   bigStickerBtn.addEventListener('click',   () => openStickerPrint(window.selectedIds, 'big'));

    searchInput.addEventListener('input',       () => filterRows(true));
    statusFilter.addEventListener('change',     () => filterRows(true));
    calibratorFilter.addEventListener('change', () => filterRows(true));
    if (calTypeFilter) calTypeFilter.addEventListener('change', () => filterRows(true));

    // ══════════════════════════════════════════════════════════════════════════
    //  EXPORT LOGIC
    // ══════════════════════════════════════════════════════════════════════════

    const EXPORT_COLUMNS = [
        { label: 'No.',            key: null             },
        { label: 'Description',    key: 'description'    },
        { label: 'Equipment Code', key: 'equipmentCode'  },
        { label: 'Model / Maker',  key: 'modelMaker'     },
        { label: 'Serial No.',     key: 'serialNo'       },
        { label: 'Location',       key: 'location'       },
        { label: 'Cal Date',       key: 'calDate'        },
        { label: 'Next Cal',       key: 'nextCal'        },
        { label: 'Frequency',      key: 'frequency'      },
        { label: 'Calibrator',     key: 'calibrator'     },
        { label: 'Cal Type',       key: 'calType'        },
        { label: 'Status',         key: 'status'         },
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

    const SIG_SESSION_KEY = 'stdSamplesSignatories';
    const REV_SESSION_KEY = 'stdSamplesRevisions';

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
        return sigFields.map(f => ({
            name:  f.name  ? f.name.value.trim()  || '—' : '—',
            title: f.title ? f.title.value.trim() || '—' : '—',
        }));
    }
    function getRevisions() {
        return revFields.map(f => ({
            code:  f.code  ? f.code.value.trim()  : '',
            date:  f.date  ? f.date.value.trim()  : '',
            by:    f.by    ? f.by.value.trim()    : '',
            desc:  f.desc  ? f.desc.value.trim()  : '',
            appr1: f.appr1 ? f.appr1.value.trim() : '',
            appr2: f.appr2 ? f.appr2.value.trim() : '',
        }));
    }

    function decodeHtml(str) {
        if (!str) return '';
        const txt = document.createElement('textarea');
        txt.innerHTML = str;
        return txt.value;
    }

    function sanitizeForPdf(str) {
        if (!str) return '';
        return str
            .replace(/Ω/g, 'Ohm')
            .replace(/ω/g, 'ohm')
            .replace(/©/g, '(c)')
            .replace(/®/g, '(R)')
            .replace(/™/g, '(TM)')
            .replace(/µ/g, 'u')
            .replace(/°/g, 'deg')
            .replace(/±/g, '+/-');
    }

    function buildColIndexMap() {
        const map = {};
        const headers = Array.from(document.querySelectorAll('#samplesTable thead th'));
        headers.forEach((th, i) => {
            const t = th.textContent.trim().toLowerCase();
            if (t.includes('description'))  map.description  = i;
            if (t.includes('equipment'))    map.equipmentCode = i;
            if (t.includes('model'))        map.modelMaker   = i;
            if (t.includes('serial'))       map.serialNo     = i;
            if (t.includes('location'))     map.location     = i;
            if (t.includes('cal date'))     map.calDate      = i;
            if (t.includes('next cal'))     map.nextCal      = i;
            if (t.includes('frequency'))    map.frequency    = i;
            if (t.includes('calibrator'))   map.calibrator   = i;
            if (t.includes('cal type'))     map.calType      = i;
            if (t.includes('status'))       map.status       = i;
        });
        return map;
    }
    const colIndexMap = buildColIndexMap();

    function getCellText(row, key) {
        // For calType, always use dataset to avoid badge HTML
        if (key === 'calType') return sanitizeForPdf(decodeHtml(row.dataset.calType || ''));
        const idx = colIndexMap[key];
        if (idx !== undefined && row.cells[idx]) {
            return sanitizeForPdf(row.cells[idx].textContent.trim());
        }
        return sanitizeForPdf(decodeHtml(decodeHtml(row.dataset[key] || '')));
    }

    function getExportData() {
        const rows = window.exportRowIds
            ? filteredRows.filter(row => window.exportRowIds.includes(row.dataset.id))
            : filteredRows;
        return rows.map((row, i) => {
            return EXPORT_COLUMNS.map(col => col.key === null ? (i + 1) : getCellText(row, col.key));
        });
    }

    function buildFilterNote() {
        const parts = [];
        if (searchInput.value.trim())   parts.push(`Search: "${searchInput.value.trim()}"`);
        if (statusFilter.value)         parts.push(`Status: ${statusFilter.value}`);
        if (calibratorFilter.value)     parts.push(`Calibrator: ${calibratorFilter.value}`);
        if (dateFrom && (dateFrom.value || dateTo.value)) {
            const fieldLabel = (dateRangeField && dateRangeField.value === 'cal_date') ? 'Cal Date' : 'Next Cal';
            parts.push(`${fieldLabel}: ${dateFrom.value || '…'} → ${dateTo.value || '…'}`);
        }
        const rowCount = `${filteredRows.length} row${filteredRows.length !== 1 ? 's' : ''}`;
        return parts.length ? `${rowCount} · Filters: ${parts.join(', ')}` : `${rowCount} · No active filters`;
    }

    // ── Bulk export ────────────────────────────────────────────────────────────
    if (exportReportBtn) exportReportBtn.addEventListener('click', () => {
        bulkDeleteBar.classList.remove('visible');
        bulkPrintBar.classList.remove('visible');
        uncheckAll();
        bulkExportBar.classList.add('visible');
        showExportCols();
        updateExportBadge();
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
        bulkExportBar.classList.remove('visible');
        hideAllBulkCols();
        uncheckAll();
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
        ws['!cols'] = [{wch:5},{wch:32},{wch:16},{wch:22},{wch:18},{wch:18},{wch:12},{wch:12},{wch:12},{wch:16},{wch:20},{wch:16}];
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Standard Samples');
        XLSX.writeFile(wb, `standard_samples_${new Date().toISOString().slice(0,10)}.xlsx`);
        closeExport();
    });

    // ── PDF export ─────────────────────────────────────────────────────────────
    exportPdfBtn.addEventListener('click', async () => {
        if (typeof window.jspdf === 'undefined') { alert('PDF library still loading, please try again.'); return; }
        const { jsPDF } = window.jspdf;
        const doc    = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        const pageW  = doc.internal.pageSize.getWidth();
        const pageH  = doc.internal.pageSize.getHeight();
        const dateStr = new Date().toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });

        const HDR_H      = 28;
        const LOGO_MAX_W = 20;
        const LOGO_X     = 6;
        const TEXT_X     = LOGO_X + LOGO_MAX_W + 5;

        doc.setFillColor(5, 48, 79);
        doc.rect(0, 0, pageW, HDR_H, 'F');

        try {
            const logoImg = await new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth; canvas.height = img.naturalHeight;
                    canvas.getContext('2d').drawImage(img, 0, 0);
                    resolve({ dataUrl: canvas.toDataURL('image/png'), w: img.naturalWidth, h: img.naturalHeight });
                };
                img.onerror = reject;
                img.src = 'images/shindengen-logo2.png';
            });
            const aspect = logoImg.h / logoImg.w;
            const LOGO_W = LOGO_MAX_W;
            const LOGO_H = LOGO_MAX_W * aspect;
            const LOGO_Y = (HDR_H - LOGO_H) / 2;
            doc.addImage(logoImg.dataUrl, 'PNG', LOGO_X, LOGO_Y, LOGO_W, LOGO_H);
        } catch(e) {}

        doc.setTextColor(255, 255, 255);
        doc.setFontSize(15); doc.setFont('helvetica', 'bold');
        doc.text('List of Standard Samples', TEXT_X, 10);
        doc.setFontSize(9.5); doc.setFont('helvetica', 'normal');
        doc.setTextColor(180, 210, 235);
        doc.text('Reference: PC2-9710 Calibration Control Procedure', TEXT_X, 18);
        doc.setFontSize(9); doc.setTextColor(200, 225, 245);
        doc.text(`Generated: ${dateStr}`, pageW - 8, 18, { align: 'right' });

        doc.autoTable({
            head: [EXPORT_COLUMNS.map(c => c.label)],
            body: getExportData(),
            startY: 32,
            margin: { left: 8, right: 8, bottom: 10 },
            theme: 'grid',
            rowPageBreak: 'avoid',
            tableLineColor: [180, 195, 210],
            tableLineWidth: 0.25,
            styles:             { fontSize: 6.8, cellPadding: { top:2.5, bottom:2.5, left:3, right:3 }, overflow: 'linebreak', font: 'helvetica', textColor: [28,43,58], lineColor: [180,195,210], lineWidth: 0.25 },
            headStyles:         { fillColor: [26,144,217], textColor: [255,255,255], fontStyle: 'bold', fontSize: 6.5, overflow: 'linebreak', lineColor: [100,160,210], lineWidth: 0.25 },
            alternateRowStyles: { fillColor: [248,250,252] },
            columnStyles: {
                0:  { cellWidth: 11, halign: 'center' },
                1:  { cellWidth: 50 },
                2:  { cellWidth: 24 },
                3:  { cellWidth: 32 },
                4:  { cellWidth: 26 },
                5:  { cellWidth: 26 },
                6:  { cellWidth: 18 },
                7:  { cellWidth: 18 },
                8:  { cellWidth: 20 },
                9:  { cellWidth: 20 },
                10: { cellWidth: 22 },
                11: { cellWidth: 22, halign: 'center' },
            },
            didParseCell: function(data) {
                if (data.section === 'body' && data.column.index === 9) {
                    const v = (data.cell.raw || '').toString().trim().toUpperCase();
                    if (v && v !== 'SDP') {
                        data.cell.styles.fillColor = [255, 243, 128];
                        data.cell.styles.textColor = [133, 100, 0];
                        data.cell.styles.fontStyle = 'bold';
                    }
                }
                if (data.section === 'body' && data.column.index === 10) {
                    const v = (data.cell.raw || '').toString().toLowerCase();
                    if      (v.includes('internal')) { data.cell.styles.textColor = [10,69,112];  data.cell.styles.fillColor = [220,238,253]; }
                    else if (v.includes('external')) { data.cell.styles.textColor = [124,77,0];   data.cell.styles.fillColor = [254,243,220]; }
                }
                if (data.section === 'body' && data.column.index === 11) {
                    const v = (data.cell.raw || '').toString().toLowerCase();
                    if      (v === 'good')         { data.cell.styles.textColor = [22,101,52];   data.cell.styles.fillColor = [220,252,231]; }
                    else if (v === 'not good')     { data.cell.styles.textColor = [185,28,28];   data.cell.styles.fillColor = [253,232,232]; }
                    else if (v === 'not in use')   { data.cell.styles.textColor = [107,114,128]; data.cell.styles.fillColor = [243,244,246]; }
                    else if (v === 'for disposal') { data.cell.styles.textColor = [146,64,14];   data.cell.styles.fillColor = [254,243,220]; }
                }
            },
            didDrawPage: function() {
                const pn = doc.internal.getCurrentPageInfo().pageNumber;
                doc.setFontSize(7); doc.setTextColor(150);
                doc.text(`Page ${pn}`, pageW / 2, pageH - 5, { align: 'center' });
            },
        });

        const signatories = getSignatories();
        const revisions   = getRevisions();
        const marginL     = 8;
        const usableW     = pageW - marginL * 2;

        const SIG_H   = 18;
        const HEAD_H  = 7;
        const ROW_H   = 5;
        const nRows   = revisions.length;
        const bodyH   = HEAD_H + ROW_H * nRows;
        const TOTAL_H = SIG_H + bodyH + 6;

        if (doc.lastAutoTable.finalY + TOTAL_H > pageH - 10) doc.addPage();
        doc.setPage(doc.internal.getNumberOfPages());

        const sigTop = pageH - TOTAL_H - 8;
        const colW   = usableW / 4;

        doc.setDrawColor(200, 210, 220); doc.setLineWidth(0.3);
        doc.line(marginL, sigTop - 3, pageW - marginL, sigTop - 3);
        doc.setFontSize(6.5); doc.setFont('helvetica', 'bold'); doc.setTextColor(5, 48, 79);
        doc.text('Prepared / Checked / Approved by:', marginL, sigTop + 2.5);

        const lh = 2.8;
        signatories.forEach((sig, i) => {
            const cx    = marginL + colW * i + colW / 2;
            const nameY = sigTop + 12;
            const lineY = sigTop + 13;
            const titY  = sigTop + 17;
            doc.setDrawColor(80, 100, 120); doc.setLineWidth(0.35);
            doc.line(cx - colW * 0.38, lineY, cx + colW * 0.38, lineY);
            doc.setFont('helvetica', 'bold'); doc.setFontSize(6.5); doc.setTextColor(28, 43, 58);
            doc.text(sig.name, cx, nameY, { align: 'center', maxWidth: colW - 4 });
            doc.setFont('helvetica', 'normal'); doc.setFontSize(5.5); doc.setTextColor(100, 120, 138);
            doc.text(sig.title, cx, titY, { align: 'center', maxWidth: colW - 4 });
        });

        const revTop  = sigTop + SIG_H + 2;
        const wDept   = 28;
        const pairGap = wDept;
        const leftW   = (pageW - 2 * marginL) - pairGap - wDept;
        const wRev    = 10;
        const wDate   = 18;
        const wBy     = 26;
        const wSec    = 28;
        const wDesc   = leftW - wRev - wDate - wBy - wSec;
        const xRev    = marginL;
        const xDate   = xRev  + wRev;
        const xBy     = xDate + wDate;
        const xDesc   = xBy   + wBy;
        const xSec    = xDesc + wDesc;
        const xDept   = marginL + leftW + pairGap;

        doc.setDrawColor(180, 195, 210); doc.setLineWidth(0.3);
        doc.rect(xRev, revTop, leftW, bodyH);
        doc.setFillColor(26, 144, 217);
        doc.rect(xRev, revTop, leftW, HEAD_H, 'F');

        const hCols = [
            { label: 'Rev.',                             x: xRev,  w: wRev  },
            { label: 'Rev. Date',                        x: xDate, w: wDate },
            { label: 'Revised By',                       x: xBy,   w: wBy   },
            { label: 'Nature / Description of Revision', x: xDesc, w: wDesc },
            { label: 'Approved by\n(Section Mgr.)',      x: xSec,  w: wSec  },
        ];
        doc.setFont('helvetica', 'bold'); doc.setFontSize(5); doc.setTextColor(255, 255, 255);
        hCols.forEach((col, ci) => {
            if (ci > 0) { doc.setDrawColor(255, 255, 255); doc.setLineWidth(0.2); doc.line(col.x, revTop, col.x, revTop + bodyH); }
            const lines  = col.label.split('\n');
            const startY = revTop + 2.5 + (lines.length > 1 ? 0 : lh * 0.5);
            lines.forEach((line, li) => doc.text(line, col.x + col.w / 2, startY + li * lh, { align: 'center', maxWidth: col.w - 2 }));
        });

        revisions.forEach((rev, ri) => {
            const rowY  = revTop + HEAD_H + ri * ROW_H;
            const textY = rowY + ROW_H * 0.65;
            if (ri % 2 === 1) { doc.setFillColor(248, 250, 252); doc.rect(xRev, rowY, wRev + wDate + wBy + wDesc, ROW_H, 'F'); }
            doc.setDrawColor(220, 228, 236); doc.setLineWidth(0.2);
            doc.line(xRev, rowY + ROW_H, xSec, rowY + ROW_H);
            doc.setFont('helvetica', 'normal'); doc.setFontSize(5.5); doc.setTextColor(28, 43, 58);
            doc.text(String(rev.code || ''), xRev  + wRev  / 2, textY, { align: 'center' });
            doc.text(rev.date || '',          xDate + 2,        textY, { maxWidth: wDate - 4 });
            doc.text(rev.by   || '',          xBy   + 2,        textY, { maxWidth: wBy   - 4 });
            doc.text(rev.desc || '',          xDesc + 2,        textY, { maxWidth: wDesc - 4 });
            [xDate, xBy, xDesc].forEach(lx => { doc.setDrawColor(200, 210, 220); doc.setLineWidth(0.2); doc.line(lx, rowY, lx, rowY + ROW_H); });
        });

        doc.setFillColor(255, 255, 255);
        doc.rect(xSec, revTop + HEAD_H, wSec, ROW_H * nRows, 'F');

        try {
            const sig1Img = await new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth; canvas.height = img.naturalHeight;
                    canvas.getContext('2d').drawImage(img, 0, 0);
                    resolve({ dataUrl: canvas.toDataURL('image/png'), w: img.naturalWidth, h: img.naturalHeight });
                };
                img.onerror = reject;
                img.src = 'images/Signature-1.png';
            });
            const sig1MaxW = (wSec - 6) * 0.6;
            const sig1MaxH = (ROW_H * nRows - 4) * 0.6;
            const sig1Ratio = Math.min(sig1MaxW / (sig1Img.w / 3.7795), sig1MaxH / (sig1Img.h / 3.7795));
            const sig1W = (sig1Img.w / 3.7795) * sig1Ratio;
            const sig1H = (sig1Img.h / 3.7795) * sig1Ratio;
            doc.addImage(sig1Img.dataUrl, 'PNG', xSec + (wSec - sig1W) / 2, revTop + HEAD_H + (ROW_H * nRows - sig1H) / 2, sig1W, sig1H);
        } catch(e) {}

        doc.setDrawColor(180, 195, 210); doc.setLineWidth(0.3);
        doc.rect(xDept, revTop, wDept, bodyH);
        doc.setFillColor(26, 144, 217);
        doc.rect(xDept, revTop, wDept, HEAD_H, 'F');
        doc.setFont('helvetica', 'bold'); doc.setFontSize(5); doc.setTextColor(255, 255, 255);
        'Approved by\n(Dept. Mgr.)'.split('\n').forEach((line, li) =>
            doc.text(line, xDept + wDept / 2, revTop + 2.5 + li * lh, { align: 'center', maxWidth: wDept - 2 })
        );

        const deptMergeH = ROW_H * (nRows - 1);
        doc.setFillColor(255, 255, 255);
        doc.rect(xDept, revTop + HEAD_H, wDept, deptMergeH, 'F');

        try {
            const sig2Img = await new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth; canvas.height = img.naturalHeight;
                    canvas.getContext('2d').drawImage(img, 0, 0);
                    resolve({ dataUrl: canvas.toDataURL('image/png'), w: img.naturalWidth, h: img.naturalHeight });
                };
                img.onerror = reject;
                img.src = 'images/Signature-2.png';
            });
            const sig2MaxW  = wDept - 4;
            const sig2MaxH  = deptMergeH - 2;
            const sig2Ratio = Math.min(sig2MaxW / (sig2Img.w / 3.7795), sig2MaxH / (sig2Img.h / 3.7795));
            const sig2W = (sig2Img.w / 3.7795) * sig2Ratio;
            const sig2H = (sig2Img.h / 3.7795) * sig2Ratio;
            doc.addImage(sig2Img.dataUrl, 'PNG', xDept + (wDept - sig2W) / 2, revTop + HEAD_H + (deptMergeH - sig2H) / 2, sig2W, sig2H);
        } catch(e) {}

        doc.setDrawColor(200, 210, 220); doc.setLineWidth(0.2);
        doc.line(xDept, revTop + HEAD_H + deptMergeH, xDept + wDept, revTop + HEAD_H + deptMergeH);

        const row3Y = revTop + HEAD_H + ROW_H * (nRows - 1);
        doc.setFillColor(248, 250, 252);
        doc.rect(xDept, row3Y, wDept, ROW_H, 'F');
        doc.setFont('helvetica', 'bold'); doc.setFontSize(4.5); doc.setTextColor(80, 100, 120);
        doc.text('QA Dept. Mngr.', xDept + wDept / 2, row3Y + ROW_H * 0.65, { align: 'center', maxWidth: wDept - 4 });

        doc.save(`standard_samples_${new Date().toISOString().slice(0, 10)}.pdf`);
        closeExport();
    });

    // ── Init ──────────────────────────────────────────────────────────────────
    initSortHeaders();

    document.querySelectorAll('#samplesTable thead th[data-sort-col]').forEach(th => {
        th.addEventListener('click', () => { _userPickedSort = true; }, true);
    });

    filterRows();
    hideAllBulkCols();

    // ── Certificate button ────────────────────────────────────────────────────
    const certBtn = document.getElementById('certBtn');
    if (certBtn) certBtn.addEventListener('click', () => {
        window.location.href = 'certificate_registration.php';
    });
});