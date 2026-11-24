<?php

namespace Tests\Feature;

use Tests\TestCase;

class SearchSuggestionTest extends TestCase
{
    public function test_supplier_suggestions_never_include_another_suppliers_invitation(): void
    {
        $buyer = $this->buyer();
        $mine = $this->supplierUser();
        $other = $this->supplierUser();
        $visible = $this->openRfq($buyer, [$mine], ['title' => 'Steel for visible site']);
        $hidden = $this->openRfq($buyer, [$other], ['title' => 'Steel for hidden site']);

        $this->actingAs($mine)->getJson(route('search.suggest', ['q' => 'Steel']))
            ->assertOk()->assertJsonFragment(['title' => $visible->number])
            ->assertJsonMissing(['title' => $hidden->number]);
    }
}
