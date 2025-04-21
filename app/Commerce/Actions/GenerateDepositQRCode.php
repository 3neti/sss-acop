<?php

namespace App\Commerce\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Http;

class GenerateDepositQRCode
{
    use AsAction;

    /**
     * Generate a deposit QR code.
     *
     * @param int|null $amount The deposit amount (optional).
     * @param string|null $account The account number (optional).
     * @return string The QR code as a base64 image string.
     * @throws \Exception if the request fails.
     */
    public function handle(?int $amount = null, ?string $account = null): string
    {
        $url = config('sss-acop.payment.server.url');

        // Prepare the payload for the GET request
        $payload = [
            'amount' => $amount,
            'account' => $account,
        ];

        // Make the GET request with the Bearer token from the .env file
        $response = Http::acceptJson()->withToken(config('sss-acop.payment.server.token'))
//            ->accept('text/plain') // Expect a plain text response
            ->get($url, $payload);

        // Check for a successful response
        if ($response->successful()) {
            return $response->body(); // Return the plain text QR code
        }

        // Handle errors or failed requests
        throw new \Exception('Failed to generate deposit QR code: ' . $response->body());
    }

    /**
     * Define validation rules for the input.
     *
     * @return array The validation rules.
     */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'integer', 'min:50'],
            'account' => ['nullable', 'numeric', 'starts_with:0', 'max_digits:11'],
        ];
    }
}
