<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ParticipantType;
use App\Enums\PersonType;
use Database\Factories\ParticipantProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['person_type', 'document', 'phone', 'city', 'state', 'participant_type'])]
#[Hidden(['document', 'phone'])]
class ParticipantProfile extends Model
{
    /** @use HasFactory<ParticipantProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'person_type' => PersonType::class,
            'participant_type' => ParticipantType::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
