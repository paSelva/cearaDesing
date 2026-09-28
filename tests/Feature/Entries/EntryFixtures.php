<?php

use App\Enums\ConsentType;
use App\Enums\ParticipantType;
use App\Models\Category;
use App\Models\Edition;
use App\Models\Entry;
use App\Models\ParticipantProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/** Edição 2026 com inscrições de 28/09 00:00:00 a 28/10 23:59:59 (§7, P77) e "agora" dentro do prazo. */
function openEdition(): Edition
{
    test()->travelTo(Carbon::parse('2026-10-01 12:00:00', 'America/Fortaleza'));

    return Edition::factory()->create([
        'registration_starts_at' => '2026-09-28 00:00:00',
        'registration_ends_at' => '2026-10-28 23:59:59',
    ]);
}

function regularCategory(Edition $edition): Category
{
    return Category::factory()->for($edition)->create(['is_student_only' => false]);
}

function studentCategory(Edition $edition): Category
{
    return Category::factory()->for($edition)->create(['is_student_only' => true]);
}

function participant(ParticipantType $type = ParticipantType::Profissional): User
{
    return ParticipantProfile::factory()->create(['participant_type' => $type])->user;
}

function entryFor(User $owner, Category $category, string $state = 'rascunho'): Entry
{
    $factory = Entry::factory()->for($owner)->for($category)->state(['edition_id' => $category->edition_id]);

    return match ($state) {
        'rascunho' => $factory->create(),
        'completo' => $factory->complete()->create(),
        'enviada' => $factory->enviada()->create(),
        'aprovada' => $factory->aprovada()->create(),
        'desclassificada' => $factory->desclassificada()->create(),
    };
}

/**
 * Imagem 1x1 real (sem depender da extensão GD), com o tamanho informado ao validador.
 * O tipo segue a extensão do nome: jpg, png, webp ou gif.
 */
function fakeImage(string $name = 'foto.jpg', int $kb = 200): UploadedFile
{
    $bytes = [
        'jpg' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=',
        'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
        'webp' => 'UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA',
        'gif' => 'R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==',
    ];
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $extension = $extension === 'jpeg' ? 'jpg' : $extension;

    return UploadedFile::fake()->createWithContent($name, base64_decode($bytes[$extension]))->size($kb);
}

function fakePdf(int $kb = 500): UploadedFile
{
    return UploadedFile::fake()->createWithContent('portfolio.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n")->size($kb);
}

function allConsents(): array
{
    return array_map(fn ($case) => $case->value, ConsentType::cases());
}

function completeTexts(array $overrides = []): array
{
    return array_replace([
        'title' => 'Cadeira Jangada',
        'authorship_credits' => 'Estúdio Sintético — design; Oficina X — produção',
        'summary' => 'Uma cadeira inspirada nas jangadas.',
        'context_solution' => 'Contexto e solução sintéticos.',
        'results_differentials' => 'Resultados sintéticos.',
        'ai_used' => '0',
        'links' => [],
    ], $overrides);
}
