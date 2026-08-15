<ul class="nav nav-pills flex-column">
    <li class="nav-item">
        <a href="{{ route('dashboard') }}" class="nav-link @active('dashboard')">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('lookup.index') }}" class="nav-link @active('lookup.*')">
            <i class="bi bi-search me-2"></i>Check GoldScore
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('customers.index') }}" class="nav-link @active('customers.*')">
            <i class="bi bi-people me-2"></i>Customers
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('khata.index') }}" class="nav-link @active('khata.index', 'khata.show')">
            <i class="bi bi-journal-bookmark me-2"></i>Udhar Khata
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('khata.receive') }}" class="nav-link @active('khata.receive', 'khata.receive.customer')">
            <i class="bi bi-cash-coin me-2"></i>Received Entry
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('udhaars.index') }}" class="nav-link @active('udhaars.*')">
            <i class="bi bi-receipt me-2"></i>Credit Entries
        </a>
    </li>
    @can('manageStaff', App\Models\User::class)
        <li class="nav-item">
            <a href="{{ route('staff.index') }}" class="nav-link @active('staff.*')">
                <i class="bi bi-person-badge me-2"></i>Staff
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('books.index') }}" class="nav-link @active('books.*')">
                <i class="bi bi-journal-text me-2"></i>Shop books
            </a>
        </li>
    @endcan
</ul>
