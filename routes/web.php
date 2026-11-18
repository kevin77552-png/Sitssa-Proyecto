<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Redirigir la raíz al login
Route::get('/', function () {
    return redirect()->route('login');
});

Route::view('dashboard', 'dashboard')
    ->middleware('auth')
    ->name('dashboard');

require __DIR__.'/auth.php';

use App\Http\Controllers\HistoryPrintController;
use App\Http\Controllers\ImportBatchController;
use App\Http\Controllers\InventoryBarcodePrintController;
use App\Http\Controllers\PurchaseOrderInvoiceController;
use App\Livewire\Admin\Imports\Index as ImportBatchIndex;
use App\Livewire\Admin\Imports\Show as ImportBatchShow;

Route::get('purchase-orders/{purchaseOrder}/invoice', [PurchaseOrderInvoiceController::class, 'show'])
    ->middleware('auth')
    ->whereNumber('purchaseOrder')
    ->name('purchase-orders.invoice');

Route::middleware('auth')->group(function () {
    Route::get('admin/historial-general/imprimir', HistoryPrintController::class)
        ->name('admin.history.print');
    Route::get('admin/inventory/barcodes/print', InventoryBarcodePrintController::class)
        ->name('inventory.barcodes.print');
    Route::get('admin/importaciones', ImportBatchIndex::class)->name('imports.index');
    Route::post('admin/importaciones', [ImportBatchController::class, 'store'])->name('imports.store');
    Route::get('admin/importaciones/{batch}/omitidos.csv', [ImportBatchController::class, 'omitted'])
        ->whereUuid('batch')
        ->name('imports.omitted');
    Route::get('admin/importaciones/{batch}', ImportBatchShow::class)
        ->whereUuid('batch')
        ->name('imports.show');
});
