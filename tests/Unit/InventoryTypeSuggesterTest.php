<?php

namespace Tests\Unit;

use App\Models\InventoryType;
use App\Services\InventoryTypeSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTypeSuggesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_suggests_only_a_unique_word_match_and_does_not_assign_the_type(): void
    {
        $type = InventoryType::create([
            'name' => 'Filtros',
            'keywords' => ['filtro de aceite'],
        ]);

        $suggestion = app(InventoryTypeSuggester::class)->suggest('FÍLTRO de aceite para motor');

        $this->assertSame($type->id, $suggestion?->id);
        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_it_does_not_suggest_when_keywords_match_multiple_types_or_only_as_substrings(): void
    {
        InventoryType::create(['name' => 'Filtros', 'keywords' => ['filtro']]);
        InventoryType::create(['name' => 'Lubricantes', 'keywords' => ['filtro']]);
        InventoryType::create(['name' => 'Anillos', 'keywords' => ['aro']]);

        $suggester = app(InventoryTypeSuggester::class);

        $this->assertNull($suggester->suggest('filtro para motor'));
        $this->assertNull($suggester->suggest('carrocería'));
    }
}
