<div class="btn-group w-100 mb-3" role="group" aria-label="Switch module">
    <a href="{{ route('dashboard') }}"
       class="btn btn-sm {{ $isGirvi ? 'btn-outline-light' : 'btn-primary' }}"
       @if (! empty($dismissOffcanvas)) data-bs-dismiss="offcanvas" @endif>
        <i class="bi bi-graph-up-arrow me-1"></i>GoldScore
    </a>
    <a href="{{ route('girvi.dashboard') }}"
       class="btn btn-sm {{ $isGirvi ? 'btn-primary' : 'btn-outline-light' }}"
       @if (! empty($dismissOffcanvas)) data-bs-dismiss="offcanvas" @endif>
        <i class="bi bi-safe me-1"></i>Girvi
    </a>
</div>
