<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notes\CreateNote;
use App\Actions\Notes\DeleteNote;
use App\Actions\Notes\UpdateNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNoteRequest;
use App\Http\Requests\Api\V1\UpdateNoteRequest;
use App\Http\Resources\V1\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NoteController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return NoteResource::collection(
            Note::query()->orderByDesc('occurred_at')->paginate(25)
        );
    }

    public function store(StoreNoteRequest $request, CreateNote $createNote): JsonResponse
    {
        $note = $createNote($request->validated());

        return NoteResource::make($note)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Note $note): NoteResource
    {
        return NoteResource::make($note);
    }

    public function update(UpdateNoteRequest $request, Note $note, UpdateNote $updateNote): NoteResource
    {
        return NoteResource::make($updateNote($note, $request->validated()));
    }

    public function destroy(Note $note, DeleteNote $deleteNote): Response
    {
        $deleteNote($note);

        return response()->noContent();
    }
}
