@extends('layouts.app')

@section('title', 'Staff')
@section('heading', 'Staff')
@section('subheading', 'Who can sign in to this store and what they may do.')

@section('actions')
    <a href="{{ route('staff.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Add staff member
    </a>
@endsection

@section('content')
    <div class="card gs-stat-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($staff as $member)
                    <tr>
                        <td class="fw-semibold">{{ $member->name }}</td>
                        <td>{{ $member->email }}</td>
                        <td>{{ $member->phone ?: '--' }}</td>
                        <td>{{ $member->role->label() }}</td>
                        <td>
                            <span class="badge {{ $member->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $member->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('staff.edit', $member) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            @can('delete', $member)
                                @if ($member->is_active)
                                    <form method="POST" action="{{ route('staff.destroy', $member) }}" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Deactivate {{ $member->name }}?')">
                                            Deactivate
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No staff yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
