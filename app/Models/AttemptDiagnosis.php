<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptDiagnosis extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['attempt_id', 'dimension', 'tag', 'evidence'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }
}
