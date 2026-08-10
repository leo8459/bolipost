<?php

namespace Tests\Feature;

use App\Support\PackageWeightFilter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageWeightFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('package_weights', function (Blueprint $table): void {
            $table->id();
            $table->decimal('peso', 12, 3)->nullable();
        });

        DB::table('package_weights')->insert([
            ['id' => 1, 'peso' => 0.500],
            ['id' => 2, 'peso' => 1.000],
            ['id' => 3, 'peso' => 50.250],
            ['id' => 4, 'peso' => 10000.000],
            ['id' => 5, 'peso' => 10000.001],
            ['id' => 6, 'peso' => null],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('package_weights');

        parent::tearDown();
    }

    public function test_weight_range_includes_its_minimum_and_maximum(): void
    {
        $query = DB::query()->fromSub(
            DB::table('package_weights')->selectRaw('id, CAST(peso AS TEXT) AS peso'),
            'p',
        );

        PackageWeightFilter::apply($query, 1, 10000);

        $this->assertSame([2, 3, 4], $query->orderBy('id')->pluck('id')->all());
    }

    public function test_each_weight_limit_can_be_used_independently(): void
    {
        $minimumQuery = DB::query()->fromSub(
            DB::table('package_weights')->selectRaw('id, CAST(peso AS TEXT) AS peso'),
            'p',
        );
        PackageWeightFilter::apply($minimumQuery, 10000, null);

        $maximumQuery = DB::query()->fromSub(
            DB::table('package_weights')->selectRaw('id, CAST(peso AS TEXT) AS peso'),
            'p',
        );
        PackageWeightFilter::apply($maximumQuery, null, 1);

        $this->assertSame([4, 5], $minimumQuery->orderBy('id')->pluck('id')->all());
        $this->assertSame([1, 2], $maximumQuery->orderBy('id')->pluck('id')->all());
    }
}
