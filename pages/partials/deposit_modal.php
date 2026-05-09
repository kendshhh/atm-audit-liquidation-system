<div class="modal fade" id="addDepositModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title">Add Deposit with Allocations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= actionUrl('add_deposit.php') ?>" id="depositForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Select Account</label>
                            <select name="account_id" class="form-select form-select-lg" required>
                                <option value="">Choose account</option>
                                <?php foreach (fetchAccounts() as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>"><?= e($account['account_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Deposit Date</label>
                            <input type="date" name="deposit_date" class="form-control form-control-lg" value="<?= e(today()) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Deposit Amount</label>
                            <input type="number" name="total_amount" id="depositTotal" class="form-control form-control-lg" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Optional deposit note"></textarea>
                        </div>
                    </div>

                    <div class="section-title-row mt-4">
                        <div>
                            <h4>Allocation Rows</h4>
                            <p>Each row becomes a payable or planned budget item.</p>
                        </div>
                        <button class="btn btn-outline-primary" type="button" id="addAllocationRow"><i class="bi bi-plus-lg"></i> Add Row</button>
                    </div>

                    <div id="allocationRows">
                        <div class="allocation-row glass-mini-card">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-3">
                                    <label class="form-label">Purpose / For What</label>
                                    <input name="purpose[]" class="form-control" placeholder="Food, Car, Bills" required>
                                </div>
                                <div class="col-lg-2">
                                    <label class="form-label">Category</label>
                                    <select name="category[]" class="form-select" required>
                                        <?php foreach (DEFAULT_CATEGORIES as $category): ?>
                                            <option value="<?= e($category) ?>"><?= e($category) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2">
                                    <label class="form-label">Amount</label>
                                    <input name="amount[]" class="form-control allocation-amount" type="number" min="0.01" step="0.01" required>
                                </div>
                                <div class="col-lg-2">
                                    <label class="form-label">Status</label>
                                    <select name="status[]" class="form-select" required>
                                        <?php foreach (STATUS_OPTIONS as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $status === 'Not Yet Paid' ? 'selected' : '' ?>><?= e($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2">
                                    <label class="form-label">Notes</label>
                                    <input name="allocation_notes[]" class="form-control" placeholder="Optional">
                                </div>
                                <div class="col-lg-1 d-grid">
                                    <button class="btn btn-outline-danger remove-allocation" type="button"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="allocation-summary">
                        <div><span>Total Deposit</span><strong id="summaryDeposit">₱0.00</strong></div>
                        <div><span>Total Allocated</span><strong id="summaryAllocated">₱0.00</strong></div>
                        <div><span>Remaining Unallocated</span><strong id="summaryRemaining">₱0.00</strong></div>
                    </div>
                    <div class="warning-text" id="allocationWarning">Allocation total must match the deposited amount before saving.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary-soft" id="saveDepositButton" disabled>Save Deposit</button>
                </div>
            </form>
        </div>
    </div>
</div>
