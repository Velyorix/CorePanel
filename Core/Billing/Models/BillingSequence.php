<?php

namespace Core\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSequence extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'current_value',
        'year',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_value' => 'integer',
            'year' => 'integer',
        ];
    }
}
