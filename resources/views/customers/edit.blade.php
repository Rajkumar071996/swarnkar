@extends('layouts.app')

@section('title', 'Edit customer')
@section('heading', 'Edit ' . $customer->full_name)

@section('content')
    <div class="card gs-stat-card">
        <div class="card-body">
            <form method="POST" action="{{ route('customers.update', $customer) }}">
                @method('PUT')
                @include('customers._form', ['submitLabel' => 'Save changes'])
            </form>
        </div>
    </div>
@endsection
