<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Stores a customer's handwritten signature as a PNG on the private disk so
 * girvi receipts can print it without putting the file on a public URL.
 */
class CustomerSignature
{
    public function store(Customer $customer, ?string $payload): void
    {
        if ($payload === 'clear') {
            $this->forget($customer);

            return;
        }

        if (! filled($payload)) {
            return;
        }

        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#', $payload, $matches)) {
            throw ValidationException::withMessages([
                'signature' => 'Draw the signature on the pad.',
            ]);
        }

        $binary = base64_decode($matches[1], true);

        if ($binary === false
            || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")
            || strlen($binary) < 32
            || strlen($binary) > 200_000) {
            throw ValidationException::withMessages([
                'signature' => 'That signature could not be saved.',
            ]);
        }

        $path = 'signatures/'.$customer->id.'.png';
        Storage::disk('local')->put($path, $binary);
        $customer->forceFill(['signature_path' => $path])->save();
    }

    public function dataUri(?Customer $customer): ?string
    {
        if (! $customer?->signature_path || ! Storage::disk('local')->exists($customer->signature_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($customer->signature_path));
    }

    private function forget(Customer $customer): void
    {
        if (filled($customer->signature_path)) {
            Storage::disk('local')->delete($customer->signature_path);
        }

        $customer->forceFill(['signature_path' => null])->save();
    }
}
