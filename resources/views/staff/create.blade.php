@extends('layouts.app')

@section('title', 'Add staff')
@section('heading', 'Add staff member')

@section('content')
    <div class="card gs-stat-card">
        <div class="card-body">
            <form method="POST" action="{{ route('staff.store') }}">
                @include('staff._form', ['submitLabel' => 'Create account'])
            </form>
        </div>
    </div>
@endsection
