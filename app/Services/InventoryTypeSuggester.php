<?php

namespace App\Services;

use App\Models\InventoryType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class InventoryTypeSuggester
{
    private ?Collection $activeTypes = null;

    public function suggest(?string $description): ?InventoryType
    {
        $normalizedDescription = $this->normalize($description ?? '');
        if ($normalizedDescription === '') {
            return null;
        }

        $matches = ($this->activeTypes ??= InventoryType::query()->where('is_active', true)->get())
            ->filter(function (InventoryType $type) use ($normalizedDescription): bool {
                foreach ($type->keywords ?? [] as $keyword) {
                    $normalizedKeyword = $this->normalize((string) $keyword);
                    if ($normalizedKeyword !== '' && str_contains(' '.$normalizedDescription.' ', ' '.$normalizedKeyword.' ')) {
                        return true;
                    }
                }

                return false;
            })
            ->unique('id')
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii(Str::lower(trim($value)));

        return trim((string) preg_replace('/[^\pL\pN]+/u', ' ', $value));
    }
}
