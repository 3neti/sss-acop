<?php

namespace App\Commerce\Http\Controllers\API;

use FrittenKeeZ\Vouchers\Facades\Vouchers;
use App\Http\Controllers\Controller;
use App\Commerce\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reference_id'     => ['required', 'string'],
            'item_description' => ['required', 'string'],
            'amount'           => ['required', 'numeric', 'min:0'],
            'currency'         => ['nullable', 'string', 'size:3'],
            'id_type'          => ['nullable', 'string'],
            'id_number'        => ['nullable', 'string'],
            'email'            => ['nullable', 'string'],
            'mobile'           => ['nullable', 'string'],
            'callback_url'     => ['required', 'url'],
        ]);

        $vendor = $request->user();

        // Store order with meta payload
        $order = Order::create([
            'reference_id' => $validated['reference_id'],
            'amount'       => $validated['amount'],
            'currency'     => $validated['currency'] ?? 'PHP',
            'callback_url' => $validated['callback_url'],
            'meta' => [
                'item_description' => $validated['item_description'],
                'id_type'          => $validated['id_type'] ?? null,
                'id_number'        => $validated['id_number'] ?? null,
                'email'            => $validated['email'] ?? null,
                'mobile'           => $validated['mobile'] ?? null,
            ],
        ]);

        // Attach voucher to order
        $voucher = Vouchers::withOwner($vendor)
            ->withEntities($order)
            ->withMetadata([
                'transaction' => 'Pay By Face',
            ])
            ->create();

        return response()->json([
            'success'      => true,
            'voucher_code' => $voucher->code,
            'url' => route('vendor.face.payment', ['voucher_code' => $voucher->code])
        ]);
    }
}
