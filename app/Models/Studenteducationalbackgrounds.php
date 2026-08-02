<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Studenteducationalbackgrounds extends Model
{
    protected $table = 'studenteducationalbackgrounds';
    public $timestamps = false;
    protected $fillable = ['studentId', 'institutionId', 'levelCompleted', 'strandTrack', 'yearCompleted', 'honorsCertifications', 'supportingDocumentPath'];

    protected function casts(): array
    {
        return [
            'levelCompleted' => \App\Enums\LevelCompleted::class,
            'yearCompleted' => 'date',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Educationalinstitutions::class, 'institutionId');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }
}
