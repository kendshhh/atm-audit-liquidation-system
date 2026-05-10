function peso(value) {
    return '₱' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applyFontSize(value) {
    var size = Number(value || 16);
    document.documentElement.style.setProperty('--app-font-size', size + 'px');
    localStorage.setItem('atm_font_size', String(size));
    var slider = document.getElementById('fontSizeSlider');
    var label = document.getElementById('fontSizeValue');
    if (slider) {
        slider.value = String(size);
    }
    if (label) {
        label.textContent = size + 'px';
    }
}

function syncThemeButton() {
    var button = document.getElementById('themeToggleButton');
    if (!button) {
        return;
    }
    var icon = button.querySelector('i');
    var dark = document.body.classList.contains('theme-dark');
    if (icon) {
        icon.className = dark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    }
    button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to night mode');
    button.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to night mode');
}

function applyTheme(theme) {
    document.body.classList.toggle('theme-dark', theme === 'dark');
    localStorage.setItem('atm_theme', theme === 'dark' ? 'dark' : 'light');
    syncThemeButton();
}

function syncSidebarButton() {
    var button = document.getElementById('sidebarCollapseToggle');
    if (!button) {
        return;
    }
    var icon = button.querySelector('i');
    var collapsed = document.body.classList.contains('sidebar-collapsed');
    if (icon) {
        icon.className = collapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
    }
    button.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
    button.setAttribute('title', collapsed ? 'Expand navigation' : 'Collapse navigation');
}

function applySidebarCollapsed(collapsed) {
    document.body.classList.toggle('sidebar-collapsed', !!collapsed);
    localStorage.setItem('atm_sidebar_collapsed', collapsed ? '1' : '0');
    syncSidebarButton();
}

applyFontSize(localStorage.getItem('atm_font_size') || 16);
applyTheme(localStorage.getItem('atm_theme') === 'dark' ? 'dark' : 'light');
applySidebarCollapsed(localStorage.getItem('atm_sidebar_collapsed') === '1' && window.innerWidth >= 992);

if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

function isDesktopLayout() {
    return window.matchMedia('(min-width: 992px)').matches;
}

var sideNav = document.querySelector('.side-nav');
if (sideNav) {
    if (isDesktopLayout()) {
        var savedNavScroll = Number(sessionStorage.getItem('atm_side_nav_scroll') || 0);
        sideNav.scrollTop = savedNavScroll;
    }

    sideNav.addEventListener('scroll', function () {
        if (isDesktopLayout()) {
            sessionStorage.setItem('atm_side_nav_scroll', String(sideNav.scrollTop));
        }
    });

    sideNav.querySelectorAll('.nav-link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (!isDesktopLayout()) {
                sessionStorage.setItem('atm_restore_window_scroll_pending', '0');
                return;
            }
            sessionStorage.setItem('atm_side_nav_scroll', String(sideNav.scrollTop));
            sessionStorage.setItem('atm_restore_window_scroll', String(window.scrollY || window.pageYOffset || 0));
            sessionStorage.setItem('atm_restore_window_scroll_pending', '1');
        });
    });
}

if (isDesktopLayout() && sessionStorage.getItem('atm_restore_window_scroll_pending') === '1') {
    var restoreY = Number(sessionStorage.getItem('atm_restore_window_scroll') || 0);
    window.requestAnimationFrame(function () {
        window.scrollTo(0, restoreY);
    });
    sessionStorage.setItem('atm_restore_window_scroll_pending', '0');
} else if (!isDesktopLayout()) {
    sessionStorage.setItem('atm_restore_window_scroll_pending', '0');
}

document.querySelectorAll('.confirm-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!confirm('Are you sure you want to continue?')) {
            event.preventDefault();
        }
    });
});

var sidebarToggle = document.getElementById('sidebarToggle');
var sidebarBackdrop = document.getElementById('sidebarBackdrop');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
        document.body.classList.add('nav-open');
    });
}
if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', function () {
        document.body.classList.remove('nav-open');
    });
}

var sidebarCollapseToggle = document.getElementById('sidebarCollapseToggle');
if (sidebarCollapseToggle) {
    syncSidebarButton();
    sidebarCollapseToggle.addEventListener('click', function () {
        applySidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
    });
}

window.addEventListener('resize', function () {
    if (window.innerWidth < 992) {
        document.body.classList.remove('sidebar-collapsed');
    } else if (localStorage.getItem('atm_sidebar_collapsed') === '1') {
        document.body.classList.add('sidebar-collapsed');
    }
    syncSidebarButton();
});

var fontSizeSlider = document.getElementById('fontSizeSlider');
if (fontSizeSlider) {
    applyFontSize(fontSizeSlider.value || localStorage.getItem('atm_font_size') || 16);
    fontSizeSlider.addEventListener('input', function () {
        applyFontSize(this.value);
    });
}

var themeToggleButton = document.getElementById('themeToggleButton');
if (themeToggleButton) {
    syncThemeButton();
    themeToggleButton.addEventListener('click', function () {
        applyTheme(document.body.classList.contains('theme-dark') ? 'light' : 'dark');
    });
}

function triggerPrint(event) {
    if (event) {
        event.preventDefault();
    }
    window.print();
}

document.querySelectorAll('#printPageButton, .print-page-button, [data-print-page]').forEach(function (button) {
    button.addEventListener('click', triggerPrint);
});

var depositForm = document.getElementById('depositForm');
var depositTotal = document.getElementById('depositTotal');
var allocationRows = document.getElementById('allocationRows');
var addAllocationRow = document.getElementById('addAllocationRow');
var saveDepositButton = document.getElementById('saveDepositButton');
var allocationWarning = document.getElementById('allocationWarning');

function updateAllocationTotals() {
    if (!depositForm || !depositTotal || !allocationRows) {
        return;
    }
    var total = parseFloat(depositTotal.value || '0');
    var allocated = 0;
    allocationRows.querySelectorAll('.allocation-amount').forEach(function (input) {
        allocated += parseFloat(input.value || '0');
    });
    var remaining = total - allocated;
    var overAllocated = remaining < -0.009;
    var hasAllocation = allocated > 0;
    var canSave = total > 0 && hasAllocation && !overAllocated;

    document.getElementById('summaryDeposit').textContent = peso(total);
    document.getElementById('summaryAllocated').textContent = peso(allocated);
    document.getElementById('summaryRemaining').textContent = peso(remaining);
    if (overAllocated) {
        allocationWarning.textContent = 'Allocated amount cannot be greater than the deposited amount.';
    } else if (!hasAllocation && total > 0) {
        allocationWarning.textContent = 'Add at least one allocation row before saving.';
    } else if (remaining > 0.009) {
        allocationWarning.textContent = 'Remaining amount will be automatically added to Savings with an auto-note.';
    } else {
        allocationWarning.textContent = '';
    }
    saveDepositButton.disabled = !canSave;
}

function bindAllocationRow(row) {
    row.querySelectorAll('input, select').forEach(function (field) {
        field.addEventListener('input', updateAllocationTotals);
        field.addEventListener('change', updateAllocationTotals);
    });
    var remove = row.querySelector('.remove-allocation');
    if (remove) {
        remove.addEventListener('click', function () {
            var rows = allocationRows.querySelectorAll('.allocation-row');
            if (rows.length === 1) {
                row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
            } else {
                row.remove();
            }
            updateAllocationTotals();
        });
    }
}

if (depositForm && allocationRows) {
    allocationRows.querySelectorAll('.allocation-row').forEach(bindAllocationRow);
    depositTotal.addEventListener('input', updateAllocationTotals);
    addAllocationRow.addEventListener('click', function () {
        var template = allocationRows.querySelector('.allocation-row');
        var clone = template.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) { input.value = ''; });
        clone.querySelectorAll('select').forEach(function (select) {
            var defaultOption = Array.from(select.options).find(function (option) { return option.value === 'Not Yet Paid'; });
            select.selectedIndex = defaultOption ? defaultOption.index : 0;
        });
        allocationRows.appendChild(clone);
        bindAllocationRow(clone);
        updateAllocationTotals();
    });
    depositForm.addEventListener('submit', function (event) {
        updateAllocationTotals();
        if (saveDepositButton.disabled) {
            event.preventDefault();
        }
    });
    updateAllocationTotals();
}

if (window.openDepositModal && document.getElementById('addDepositModal')) {
    new bootstrap.Modal(document.getElementById('addDepositModal')).show();
}

var statusModal = document.getElementById('statusModal');
if (statusModal) {
    statusModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('statusId').value = button.dataset.id || '';
        document.getElementById('statusPurpose').value = button.dataset.purpose || '';
        document.getElementById('statusValue').value = button.dataset.status || 'Not Yet Paid';
        document.getElementById('statusPaid').value = button.dataset.paid || '0.00';
        document.getElementById('statusNotes').value = button.dataset.notes || '';
    });
}

var transactionEditModal = document.getElementById('transactionEditModal');
if (transactionEditModal) {
    transactionEditModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('transactionEditId').value = button.dataset.id || '';
        document.getElementById('transactionEditDate').value = button.dataset.date || '';
        document.getElementById('transactionEditType').value = button.dataset.type || 'Payment';
        document.getElementById('transactionEditCategory').value = button.dataset.category || '';
        document.getElementById('transactionEditAmount').value = button.dataset.amount || '0.00';
        document.getElementById('transactionEditStatus').value = button.dataset.status || 'Completed';
        document.getElementById('transactionEditDescription').value = button.dataset.description || '';
        document.getElementById('transactionEditAccount').value = button.dataset.account || '';
    });
}

var reconcileAccount = document.getElementById('reconcileAccount');
var reconcileSystemBalance = document.getElementById('reconcileSystemBalance');
var reconcileActualBalance = document.getElementById('reconcileActualBalance');
var reconcileDifference = document.getElementById('reconcileDifference');

function updateReconciliationPreview(resetActual) {
    if (!reconcileAccount || !reconcileSystemBalance || !reconcileActualBalance || !reconcileDifference) {
        return;
    }

    var selected = reconcileAccount.options[reconcileAccount.selectedIndex];
    var systemBalance = parseFloat(selected ? selected.dataset.balance || '0' : '0');
    reconcileSystemBalance.value = peso(systemBalance);

    if (resetActual || reconcileActualBalance.value === '') {
        reconcileActualBalance.value = systemBalance.toFixed(2);
    }

    var actualBalance = parseFloat(reconcileActualBalance.value || '0');
    var difference = actualBalance - systemBalance;
    var label = difference < 0 ? 'Missing Funds' : (difference > 0 ? 'Excess Funds' : 'Balanced');
    reconcileDifference.value = label + ' - ' + peso(difference);
}

if (reconcileAccount && reconcileActualBalance) {
    reconcileAccount.addEventListener('change', function () {
        updateReconciliationPreview(true);
    });
    reconcileActualBalance.addEventListener('input', function () {
        updateReconciliationPreview(false);
    });
    updateReconciliationPreview(true);
}

if (window.Chart) {
    var balanceCanvas = document.getElementById('balanceChart');
    if (balanceCanvas) {
        new Chart(balanceCanvas, {
            type: 'bar',
            data: {
                labels: JSON.parse(balanceCanvas.dataset.labels || '[]').map(function (label) { return label.replace(' ATM Account', ''); }),
                datasets: [{ label: 'Current Balance', data: JSON.parse(balanceCanvas.dataset.values || '[]'), backgroundColor: ['#7B8CFF', '#A78BFA'], borderRadius: 14 }]
            },
            options: { plugins: { legend: { display: false } }, responsive: true, maintainAspectRatio: false }
        });
    }

    var paymentCanvas = document.getElementById('paymentChart');
    if (paymentCanvas) {
        new Chart(paymentCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Pending', 'Not Yet Paid', 'Partially Paid', 'Saved', 'Borrowed'],
                datasets: [{ data: JSON.parse(paymentCanvas.dataset.values || '[]'), backgroundColor: ['#7ED6A7', '#F6C177', '#E5E7EB', '#A78BFA', '#93C5FD', '#FDBA74'], borderWidth: 0 }]
            },
            options: { cutout: '64%', plugins: { legend: { position: 'bottom' } }, responsive: true, maintainAspectRatio: false }
        });
    }
}
