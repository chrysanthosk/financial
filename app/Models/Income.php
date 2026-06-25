<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    use HasFactory;

    protected $fillable = [
        'income_date',
        'amount',
        'income_source_id',
        'note',
        'created_by',
    ];

    protected $casts = [
        'income_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(IncomeSource::class, 'income_source_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
