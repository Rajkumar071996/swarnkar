@php
    $store = $loan->store ?? auth()->user()->store;
@endphp

@extends('layouts.print', ['backUrl' => route('girvi.loans.show', $loan)])

@section('title', 'Release receipt ' . ($settlement?->receipt_no ?? $loan->receipt_no))

@section('content')
    <div class="gs-print-sheet">
        @include('girvi._release_slip', [
            'loan' => $loan,
            'store' => $store,
            'settlement' => $settlement,
            'copy' => 'Customer Copy',
        ])
        @include('girvi._release_slip', [
            'loan' => $loan,
            'store' => $store,
            'settlement' => $settlement,
            'copy' => 'Shop Copy',
        ])
    </div>
@endsection
