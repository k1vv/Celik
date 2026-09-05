<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skill extends Model
{
    protected $fillable = ['subject_id', 'module_id', 'name', 'description', 'order'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** Curriculum grouping this skill belongs to (e.g. "Heat"), if authored. */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function masteries(): HasMany
    {
        return $this->hasMany(Mastery::class);
    }

    /**
     * Skills this skill depends on - see engine.md §8. Hand-authored:
     * e.g. Specific Heat Capacity depends on Thermal Equilibrium.
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'skill_prerequisites', 'skill_id', 'prerequisite_skill_id');
    }

    /**
     * Skills that depend on this skill - the inverse of prerequisites().
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'skill_prerequisites', 'prerequisite_skill_id', 'skill_id');
    }
}
