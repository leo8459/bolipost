<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageReportExport extends Model
{
    protected $table = 'package_report_exports';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'report_type',
        'filters',
        'status',
        'file_path',
        'file_name',
        'row_count',
        'total_rows',
        'progress',
        'progress_message',
        'error_message',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'row_count' => 'integer',
            'total_rows' => 'integer',
            'progress' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
