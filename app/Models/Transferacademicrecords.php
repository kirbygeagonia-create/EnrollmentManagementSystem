<?php

namespace App\Models;

use App\Enums\PassResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transferacademicrecords extends Model
{
    protected $table = 'transferacademicrecords';

    protected $primaryKey = 'transferRecordId';

    public $timestamps = false;

    protected $fillable = ['studentId', 'institutionId', 'subjectNameAtOldSchool', 'unitsAtOldSchool', 'gradeAtOldSchool', 'passResult'];

    protected function casts(): array
    {
        return [
            'unitsAtOldSchool' => 'decimal:2',
            'gradeAtOldSchool' => 'decimal:2',
            'passResult' => PassResult::class,
        ];
    }

    /**
     * @return BelongsTo<Educationalinstitutions, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Educationalinstitutions::class, 'institutionId');
    }

    /**
     * @return BelongsTo<Students, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    /**
     * @return HasMany<Creditedsubjects, $this>
     */
    public function creditedsubjects(): HasMany
    {
        return $this->hasMany(Creditedsubjects::class, 'transferRecordId');
    }
}
