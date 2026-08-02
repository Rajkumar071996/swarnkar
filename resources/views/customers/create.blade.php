@extends('layouts.app')

@section('title', 'Add customer')
@section('heading', 'Add customer')
@section('subheading', 'If this person already exists on the network, their profile is linked rather than duplicated.')

@section('content')
    <div class="card gs-stat-card">
        <div class="card-body">
            <form method="POST" action="{{ route('customers.store') }}">
                @include('customers._form', ['submitLabel' => 'Save customer'])
            </form>
        </div>
    </div>
@endsection
