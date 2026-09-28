<?php

declare(strict_types=1);

namespace App\Http\Controllers\Participant;

use App\Actions\Entries\AddEntryImage;
use App\Actions\Entries\CreateEntryDraft;
use App\Actions\Entries\EntryGuard;
use App\Actions\Entries\RemoveEntryImage;
use App\Actions\Entries\RemoveSupplementaryPdf;
use App\Actions\Entries\SetSupplementaryPdf;
use App\Actions\Entries\SubmitEntry;
use App\Actions\Entries\UpdateEntry;
use App\Enums\ConsentType;
use App\Enums\EntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\PrivateMediaResponse;
use App\Models\Category;
use App\Models\Entry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EntryController extends Controller
{
    public function store(Request $request, CreateEntryDraft $action): RedirectResponse
    {
        $category = Category::query()->findOrFail((int) $request->input('category_id'));
        $entry = $action->handle($request->user(), $category);

        return redirect()->route('participant.entries.edit', $entry)
            ->with('status', 'Inscrição criada como rascunho. Preencha os campos e envie antes do prazo.');
    }

    public function edit(Entry $entry, EntryGuard $guard, SubmitEntry $submit): View
    {
        Gate::authorize('view', $entry);
        $entry->load(['category', 'edition', 'links', 'proofRequests.media']);

        return view('participant.entries.edit', [
            'entry' => $entry,
            'editable' => $entry->status->isEditableByParticipant() && $guard->isRegistrationOpen($entry->edition),
            'isSubmitted' => $entry->status === EntryStatus::Enviada,
            'images' => $entry->getMedia('images'),
            'pdf' => $entry->getFirstMedia('supplementary_pdf'),
            'missing' => $entry->status === EntryStatus::Rascunho ? $submit->missingItems($entry) : [],
            'consentTypes' => ConsentType::cases(),
        ]);
    }

    /**
     * Salva os textos e, com intent=submit, envia a inscrição na mesma requisição:
     * o que foi digitado nunca se perde se o envio falhar (ex.: falta imagem).
     */
    public function update(Request $request, Entry $entry, UpdateEntry $update, SubmitEntry $submit): RedirectResponse
    {
        Gate::authorize('update', $entry);
        $ip = (string) $request->ip();
        $wantsSubmit = $request->input('intent') === 'submit' && $entry->status === EntryStatus::Rascunho;

        $update->handle($entry, $request->only([
            ...Entry::TEXT_FIELDS, 'ai_used', 'ai_usage_description', 'links', 'consents',
        ]), $ip);

        if (! $wantsSubmit) {
            return back()->with('status', 'Alterações salvas.');
        }

        try {
            $submit->handle($entry->refresh(), (array) $request->input('consents', []), $ip);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput($request->only('consents'))
                ->with('status', 'Rascunho salvo, mas a inscrição ainda não foi enviada.');
        }

        return redirect()->route('participant.entries.edit', $entry)
            ->with('status', 'Inscrição enviada! Você pode editá-la até o fim do prazo.');
    }

    public function storeImage(Request $request, Entry $entry, AddEntryImage $action): RedirectResponse
    {
        Gate::authorize('update', $entry);
        $action->handle($entry, $request->file('image'));

        return back()->with('status', 'Imagem adicionada.');
    }

    public function destroyImage(Entry $entry, Media $media, RemoveEntryImage $action): RedirectResponse
    {
        Gate::authorize('update', $entry);
        $action->handle($entry, $media);

        return back()->with('status', 'Imagem removida.');
    }

    public function storePdf(Request $request, Entry $entry, SetSupplementaryPdf $action): RedirectResponse
    {
        Gate::authorize('update', $entry);
        $action->handle($entry, $request->file('pdf'));

        return back()->with('status', 'PDF complementar salvo.');
    }

    public function destroyPdf(Entry $entry, RemoveSupplementaryPdf $action): RedirectResponse
    {
        Gate::authorize('update', $entry);
        $action->handle($entry);

        return back()->with('status', 'PDF complementar removido.');
    }

    /** Arquivos ficam no disco privado; aqui, só o dono (a organização usa a rota própria). */
    public function file(Entry $entry, Media $media): StreamedResponse
    {
        Gate::authorize('update', $entry);

        return PrivateMediaResponse::make($entry, $media);
    }
}
