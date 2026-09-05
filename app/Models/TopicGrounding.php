<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicGrounding extends Model
{
    protected $fillable = ['kssm_code', 'syllabus_excerpt', 'rubric', 'tag_vocabulary'];

    protected function casts(): array
    {
        return [
            'tag_vocabulary' => 'array',
        ];
    }

    /**
     * Retrieval context for grading a homework answer against this KSSM
     * code - syllabus excerpt, rubric, and the tag vocabulary the LLM must
     * pick from. Null if no grounding has been authored for it yet.
     */
    public static function forKssmCode(string $kssmCode): ?self
    {
        return static::where('kssm_code', $kssmCode)->first();
    }
}
