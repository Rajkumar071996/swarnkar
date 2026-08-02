{{-- Field-level validation is rendered inline on each form, so this only surfaces flash messages. --}}
@foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'status' => 'info'] as $key => $variant)
    @if (session($key))
        <div class="alert alert-{{ $variant }} alert-dismissible fade show" role="alert" data-gs-autodismiss>
            {{ session($key) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach
