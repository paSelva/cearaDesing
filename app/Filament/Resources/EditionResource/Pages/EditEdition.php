<?php

declare(strict_types=1);

namespace App\Filament\Resources\EditionResource\Pages;

use App\Actions\Admin\UpdateEditionSettings;
use App\Filament\Resources\EditionResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEdition extends EditRecord
{
    protected static string $resource = EditionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'name',
            'registration_starts_at',
            'registration_ends_at',
            'judging_starts_at',
            'judging_ends_at',
            'voting_starts_at',
            'voting_ends_at',
            'results_announce_on',
            'ceremony_on',
            'project_period_starts_on',
            'project_period_ends_on',
            'judges_per_entry',
            'band_gold_min',
            'band_silver_min',
            'band_bronze_min',
            'band_honorable_min',
        ]));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateEditionSettings::class)->handle($record, $data);
    }
}
