@extends('layouts.app')

@section('title', 'Edit staff')
@section('heading', 'Edit ' . $staff->name)

@section('content')
    <div class="card gs-stat-card">
        <div class="card-body">
            <form method="POST" action="{{ route('staff.update', $staff) }}">
                @method('PUT')
                @include('staff._form', ['submitLabel' => 'Save changes'])
            </form>
        </div>
    </div>
@endsection
