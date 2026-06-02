<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarteroAssignmentReportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cartero_assignment_report_id',
        'tipo_paquete',
        'paquete_id',
        'codigo',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(CarteroAssignmentReport::class, 'cartero_assignment_report_id');
    }
}
