<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeIncome extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'total_amount',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
