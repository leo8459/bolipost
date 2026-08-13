<?php

namespace Tests\Feature;

use App\Livewire\PaquetesEms;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class PaquetesEmsRegionalSearchOptimizationTest extends TestCase
{
    public function test_exact_regional_search_keeps_an_internal_filter_after_clearing_the_input(): void
    {
        $component = new class extends PaquetesEms
        {
            public function completeExactSearch(string $codigo, string $message): void
            {
                $this->completeRecibirRegionalExactSearch($codigo, $message);
            }
        };

        $component->search = '  EE123456789BO  ';
        $component->searchQuery = '';

        $component->completeExactSearch('EE123456789BO', 'Seleccionado.');

        $this->assertSame('', $component->search);
        $this->assertSame('EE123456789BO', $component->searchQuery);
        $this->assertSame('Seleccionado.', session('success'));
    }

    public function test_regional_receive_listing_is_paginated_instead_of_loading_every_pending_record(): void
    {
        $component = new class extends PaquetesEms
        {
            public bool $paginationWasUsed = false;

            protected function almacenUnificadoQuery()
            {
                return new class($this)
                {
                    public function __construct(private object $component)
                    {
                    }

                    public function simplePaginate(int $perPage): Paginator
                    {
                        $this->component->paginationWasUsed = true;

                        return new Paginator([], $perPage);
                    }

                    public function get(): never
                    {
                        throw new \RuntimeException('Recibir regional no debe cargar todos los registros pendientes.');
                    }
                };
            }
        };

        $component->mode = 'transito_ems';
        $component->perPagePaquetes = 100;
        $component->render();

        $this->assertTrue($component->paginationWasUsed);
    }
}
