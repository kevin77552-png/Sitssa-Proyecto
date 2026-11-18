<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PurchaseOrderInvoiceController extends Controller
{
    public function show(Request $request, PurchaseOrder $purchaseOrder)
    {
        $user = $request->user();
        abort_unless($user && $user->activo, 403);
        $canView = in_array($user?->role, ['administrador', 'superadministrador', 'gerente'], true)
            || ($user?->role === 'proveedor' && (int) $purchaseOrder->provider_id === (int) $user->id);

        abort_unless($canView, 403);

        $path = $purchaseOrder->attachment;
        abort_unless(
            is_string($path) && str_starts_with($path, 'invoices/') && ! str_contains($path, '..'),
            404
        );

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $mime = $disk->mimeType($path);
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => abort(404),
        };
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return $disk->response(
            $path,
            "purchase-order-{$purchaseOrder->id}.{$extension}",
            [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
            ],
            $disposition
        );
    }
}
