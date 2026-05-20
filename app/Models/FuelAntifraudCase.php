<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FuelAntifraudCase extends Model
{
    public const TYPE_DUPLICATE_INVOICE = 'duplicate_invoice';
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_RESOLVED = 'resolved';

    protected $table = 'fuel_antifraud_cases';

    protected $fillable = [
        'case_key',
        'type',
        'status',
        'invoice_number',
        'fuel_invoice_id',
        'conflicting_fuel_invoice_id',
        'fuel_log_id',
        'conflicting_fuel_log_id',
        'vehicle_id',
        'driver_id',
        'conflicting_vehicle_id',
        'conflicting_driver_id',
        'detected_source',
        'summary',
        'evidence_json',
        'reviewed_at',
        'reviewed_by_user_id',
        'activo',
    ];

    protected $casts = [
        'evidence_json' => 'array',
        'reviewed_at' => 'datetime',
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(FuelInvoice::class, 'fuel_invoice_id');
    }

    public function conflictingInvoice()
    {
        return $this->belongsTo(FuelInvoice::class, 'conflicting_fuel_invoice_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function conflictingVehicle()
    {
        return $this->belongsTo(Vehicle::class, 'conflicting_vehicle_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function conflictingDriver()
    {
        return $this->belongsTo(Driver::class, 'conflicting_driver_id');
    }

    public function scopeActive($query)
    {
        if (Schema::hasColumn($this->getTable(), 'activo')) {
            $query->where($this->qualifyColumn('activo'), true);
        }

        return $query;
    }

    public static function buildDuplicateKey(string $invoiceNumber, ?int $invoiceA, ?int $invoiceB, string $source): string
    {
        $ids = collect([$invoiceA, $invoiceB])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->implode('-');

        return sha1(implode('|', [
            self::TYPE_DUPLICATE_INVOICE,
            trim($invoiceNumber),
            $ids !== '' ? $ids : 'blocked-attempt',
            $source,
        ]));
    }
}
