function peso(value) {
    return '₱' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

function toggleBigText() {
    document.body.classList.toggle('large-text');
    localStorage.setItem('atm_big_text', document.body.classList.contains('large-text') ? '1' : '0');
}

if (localStorage.getItem('atm_big_text') === '1') {
    document.body.classList.add('large-text');
}

['bigTextToggle', 'settingsBigText'].forEach(function (id) {
    var button = document.getElementById(id);
    if (button) {
        button.addEventListener('click', toggleBigText);
    }
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
    var matched = total > 0 && Math.abs(remaining) < 0.01;

    document.getElementById('summaryDeposit').textContent = peso(total);
    document.getElementById('summaryAllocated').textContent = peso(allocated);
    document.getElementById('summaryRemaining').textContent = peso(remaining);
    allocationWarning.textContent = matched ? '' : 'Allocation total must match the deposited amount before saving.';
    saveDepositButton.disabled = !matched;
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
