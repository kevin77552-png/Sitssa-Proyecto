<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Throwable;

class ImportBatchController extends Controller
{
    public function store(Request $request)
    {
        $this->authorizeAdministrator($request);

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:'.config('inventory_import.max_file_kilobytes', 102400)],
            'cutoff_date' => ['required', 'date_format:Y-m-d'],
            'sheet_name' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            return $this->redirectWithErrors($request, $validator->errors(), true);
        }

        $uploadedFile = $request->file('file');
        $diskName = config('inventory_import.disk', 'local');
        $disk = Storage::disk($diskName);
        $path = $uploadedFile->store(config('inventory_import.directory', 'imports'), $diskName);
        if (! is_string($path) || $path === '') {
            return $this->redirectWithErrors($request, ['file' => 'No se pudo guardar el archivo de importación.']);
        }

        try {
            $hash = hash_file('sha256', $disk->path($path));
            if ($hash === false) {
                throw new \RuntimeException('No se pudo calcular la huella del archivo.');
            }

            if (ImportBatch::query()->where('file_hash', $hash)->blocksDuplicateUpload()->exists()) {
                $disk->delete($path);

                return $this->redirectWithErrors($request, ['file' => 'Este archivo ya tiene un lote activo o completado. Revierte el lote anterior antes de cargarlo otra vez.']);
            }

            $batch = DB::transaction(function () use ($request, $uploadedFile, $path, $hash): ImportBatch {
                $batch = ImportBatch::create([
                    'user_id' => $request->user()->id,
                    'file_name' => $uploadedFile->getClientOriginalName(),
                    'file_path' => $path,
                    'file_hash' => $hash,
                    'file_size' => $uploadedFile->getSize(),
                    'status' => 'analyzing',
                    'requested_sheet' => $request->input('sheet_name') ?: null,
                    'cutoff_date' => $request->input('cutoff_date') ?: null,
                ]);
                DB::table('import_job_outbox')->insert([
                    'batch_id' => $batch->id,
                    'job_type' => 'analyze',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $batch;
            });
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        return new RedirectResponse(route('imports.show', $batch));
    }

    public function omitted(Request $request, ImportBatch $batch)
    {
        $this->authorizeAdministrator($request);
        $filename = 'import_lote_'.$batch->id.'_omitidos.csv';
        $rows = $batch->rows()
            ->whereIn('status', ['existente', 'repetido', 'sin_sigicov', 'invalida'])
            ->orderBy('excel_row');

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['fila_excel', 'sigicov', 'descripcion', 'estado', 'motivo']);

            $rows->chunkById(500, function ($chunk) use ($handle): void {
                foreach ($chunk as $row) {
                    $payload = $row->payload ?? [];
                    fputcsv($handle, [
                        $row->excel_row,
                        $this->safeCsvValue($row->sigicov),
                        $this->safeCsvValue($payload['descripcion'] ?? ''),
                        $row->status,
                        $this->safeCsvValue($row->reason),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['administrador', 'superadministrador'], true), 403);
    }

    private function safeCsvValue(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    private function redirectWithErrors(Request $request, array|MessageBag $errors, bool $withInput = false): RedirectResponse
    {
        $errorBag = $errors instanceof MessageBag ? $errors : new MessageBag($errors);
        $viewErrorBag = (new ViewErrorBag())->put('default', $errorBag);
        $request->session()->flash('errors', $viewErrorBag);
        if ($withInput) {
            $request->session()->flashInput($request->input());
        }

        return new RedirectResponse($request->headers->get('referer', route('imports.index')));
    }
}
