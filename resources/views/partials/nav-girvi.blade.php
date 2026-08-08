<ul class="nav nav-pills flex-column">
    <li class="nav-item">
        <a href="{{ route('girvi.dashboard') }}" class="nav-link @active('girvi.dashboard')"
           @if (! empty($dismissOffcanvas)) data-bs-dismiss="offcanvas" @endif>
            <i class="bi bi-speedometer2 me-2"></i>Girvi Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('girvi.loans.create') }}" class="nav-link @active('girvi.loans.create')"
           @if (! empty($dismissOffcanvas)) data-bs-dismiss="offcanvas" @endif>
            <i class="bi bi-plus-square me-2"></i>New Girvi
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('girvi.loans.index') }}" class="nav-link @active('girvi.loans.index', 'girvi.loans.show')"
           @if (! empty($dismissOffcanvas)) data-bs-dismiss="offcanvas" @endif>
            <i class="bi bi-safe me-2"></i>All Mortgage
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('girvi.loans.index', ['filter' => 'unreleased']) }}" class="nav-link"
           @if (! empty($dismissOffcanvas)) data-bs-dismiss="offcanvas" @endif>
            <i class="bi bi-lock me-2"></i>UnReleased
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('girvi.loans.index', ['filter' => 'released']) }}" class="nav-link"
           @if (! empty($dismissOffcanvas)) data-bs-dismiss="offcanvas" @endif>
            <i class="bi bi-unlock me-2"></i>Released
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('girvi.release.create') }}" class="nav-link @active('girvi.release.*')"
           @if (! empty($dismissOffcanvas)) data-bs-dismiss="offcanvas" @endif>
            <i class="bi bi-box-arrow-up me-2"></i>Release
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('customers.index') }}" class="nav-link @active('customers.*')"
           @if (! empty($dismissOffcanvas)) data-bs-dismiss="offcanvas" @endif>
            <i class="bi bi-people me-2"></i>Customers
        </a>
    </li>
    @can('manageStaff', App\Models\User::class)
        <li class="nav-item">
            <a href="{{ route('staff.index') }}" class="nav-link @active('staff.*')"
               @if (! empty($dismissOffcanvas)) data-bs-dismiss="offcanvas" @endif>
                <i class="bi bi-person-badge me-2"></i>Staff
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('girvi.settings.edit') }}" class="nav-link @active('girvi.settings.*')"
               @if (! empty($dismissOffcanvas)) data-bs-dismiss="offcanvas" @endif>
                <i class="bi bi-sliders me-2"></i>Settings
            </a>
        </li>
    @endcan
</ul>
