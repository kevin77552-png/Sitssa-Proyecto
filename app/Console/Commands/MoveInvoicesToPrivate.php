<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MoveInvoicesToPrivate extends Command
{
    protected $signature = 'invoices:secure-storage';

    protected $description = 'Move existing invoice files from public storage to private storage';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $moved = 0;

        foreach ($public->allFiles('invoices') as $path) {
            $content = $public->get($path);

            if ($private->exists($path)) {
                if (! hash_equals(hash('sha256', $private->get($path)), hash('sha256', $content))) {
                    throw new RuntimeException("A different private invoice already exists at {$path}.");
                }
            } elseif (! $private->put($path, $content)) {
                throw new RuntimeException("Unable to copy invoice {$path} to private storage.");
            }

            if (! $public->delete($path)) {
                throw new RuntimeException("Unable to remove public invoice {$path} after copying it.");
            }

            $moved++;
        }

        $this->info("Secured {$moved} invoice file(s).");

        return self::SUCCESS;
    }
}
