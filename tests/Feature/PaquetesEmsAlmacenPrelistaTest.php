<?php

namespace Tests\Feature;

use App\Livewire\PaquetesEms;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaquetesEmsAlmacenPrelistaTest extends TestCase
{
    public function test_restore_matches_both_type_and_id_in_the_current_warehouse(): void
    {
        Schema::create('warehouse_rows', function (Blueprint $table) {
            $table->string('record_type');
            $table->integer('record_id');
            $table->string('ciudad');
        });
        DB::table('warehouse_rows')->insert([
            ['record_type' => 'EMS', 'record_id' => 1, 'ciudad' => 'LA PAZ'],
            ['record_type' => 'INT', 'record_id' => 1, 'ciudad' => 'ORURO'],
            ['record_type' => 'INT', 'record_id' => 2, 'ciudad' => 'LA PAZ'],
            ['record_type' => 'CONTRATO', 'record_id' => 1, 'ciudad' => 'LA PAZ'],
            ['record_type' => 'SOLICITUD', 'record_id' => 1, 'ciudad' => 'LA PAZ'],
        ]);

        $component = new class extends PaquetesEms {
            protected function almacenUnificadoQuery()
            {
                return DB::table('warehouse_rows')->where('ciudad', 'LA PAZ');
            }
        };
        $component->mode = 'almacen_ems';
        $component->perPagePaquetes = 1;
        $component->selectedPaquetes = ['1', '2'];
        $component->selectedPaquetesInt = ['1', '2', '2'];
        $component->updated('selectedPaquetesInt.2', '2');
        $component->selectedContratos = ['1', '3'];
        $component->selectedSolicitudes = ['1'];

        $component->restoreAlmacenSelection();

        $this->assertSame(['1'], $component->selectedPaquetes);
        $this->assertSame(['2'], $component->selectedPaquetesInt);
        $this->assertSame(['1'], $component->selectedContratos);
        $this->assertSame(['1'], $component->selectedSolicitudes);
    }
}
