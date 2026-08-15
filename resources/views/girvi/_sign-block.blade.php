<div class="gs-slip-signs">
    <div>
        @if ($signatureUri ?? null)
            <img src="{{ $signatureUri }}" alt="" class="gs-slip-sign-img">
        @endif
        <div>Customer sign</div>
    </div>
    <div>Lender sign</div>
</div>
