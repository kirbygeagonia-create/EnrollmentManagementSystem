<?php

namespace App\Models;

use App\Enums\LevelCompleted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Studenteducationalbackgrounds extends Model
{
    protected $table = 'studenteducationalbackgrounds';

    protected $primaryKey = 'backgroundId';

    public $timestamps = false;

    protected $fillable = ['studentId', 'institutionId', 'levelCompleted', 'strandTrack', 'yearCompleted', 'honorsCertifications', 'supportingDocumentPath'];

    protected function casts(): array
    {
        return [
            'levelCompleted' => LevelCompleted::class,
            'yearCompleted' => 'date',
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
}
