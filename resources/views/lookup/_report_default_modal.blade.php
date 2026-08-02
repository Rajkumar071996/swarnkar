<div class="modal fade" id="reportDefaultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('flags.store', $customer) }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Report a default against {{ $customer->full_name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-warning small">
                    A report affects this customer at every store on the network. Evidence is required, and
                    the report only influences their score once it has been verified.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="reason" class="form-label">Reason</label>
                        <select id="reason" name="reason" class="form-select" required>
                            @foreach (App\Enums\DefaultFlagReason::cases() as $reason)
                                <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="occurred_on" class="form-label">Date it happened</label>
                        <input type="date" id="occurred_on" name="occurred_on" class="form-control"
                               max="{{ now()->toDateString() }}" required>
                    </div>

                    <div class="col-md-6">
                        <label for="amount_involved" class="form-label">Amount involved</label>
                        <input type="number" step="0.01" min="0" id="amount_involved" name="amount_involved"
                               class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label for="evidence" class="form-label">Evidence (invoice or cheque image)</label>
                        <input type="file" id="evidence" name="evidence" class="form-control"
                               accept="image/*,application/pdf" required>
                    </div>

                    <div class="col-12">
                        <label for="narrative" class="form-label">What happened</label>
                        <textarea id="narrative" name="narrative" rows="3" class="form-control" required></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Submit report</button>
            </div>
        </form>
    </div>
</div>
