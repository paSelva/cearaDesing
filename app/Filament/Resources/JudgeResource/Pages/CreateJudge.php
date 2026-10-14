<?php

declare(strict_types=1);

namespace App\Filament\Resources\JudgeResource\Pages;

use App\Actions\Admin\CreateJudge as CreateJudgeAction;
use App\Filament\Resources\JudgeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateJudge extends CreateRecord
{
    protected static string $resource = JudgeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateJudgeAction::class)->handle($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
