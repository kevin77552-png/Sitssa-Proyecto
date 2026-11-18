<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_only_access_its_assigned_invoice(): void
    {
        Storage::fake('local');
        $invoice = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        Storage::disk('local')->put('invoices/invoice.pdf', $invoice);

        $assignedProvider = User::factory()->create(['role' => 'proveedor', 'activo' => true]);
        $otherProvider = User::factory()->create(['role' => 'proveedor', 'activo' => true]);
        $order = PurchaseOrder::create([
            'cantidad' => 1,
            'descripcion' => 'Assigned order',
            'status' => 'factura_subida',
            'attachment' => 'invoices/invoice.pdf',
            'provider_id' => $assignedProvider->id,
        ]);

        $this->actingAs($assignedProvider)
            ->get(route('purchase-orders.invoice', $order))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($otherProvider)
            ->get(route('purchase-orders.invoice', $order))
            ->assertForbidden();
    }
}