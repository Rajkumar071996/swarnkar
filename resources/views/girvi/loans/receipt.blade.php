@php
    $store = $loan->store ?? auth()->user()->store;
@endphp

@extends('layouts.print', ['backUrl' => route('girvi.loans.show', $loan)])

@section('title', 'Girvi receipt ' . $loan->receipt_no)

@section('content')
    <div class="gs-print-sheet">
        @include('girvi._slip', ['loan' => $loan, 'store' => $store, 'copy' => 'Customer Copy'])
        @include('girvi._slip', ['loan' => $loan, 'store' => $store, 'copy' => 'Shop Copy'])
    </div>
@endsection
