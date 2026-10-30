<?php

namespace App\Http\Controllers;

use App\Actions\Tags\ListTags;
use App\Http\Requests\Tags\IndexTagsRequest;
use App\Http\Requests\Tags\StoreTagRequest;
use App\Http\Requests\Tags\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(
        IndexTagsRequest $request,
        ListTags $action,
    ): Response {
        // Collect only the validated search filter.
        $filters = $request->safe()->only(['search']);

        // Ask the action for matching tags and page information.
        $tags = $action->get($filters, $request->user());

        // Send the results and current search text to Vue.
        return Inertia::render('tags/Index', [
            'tags' => $tags,
            'search' => $filters['search'] ?? '',
            'canCreate' => $request->user()->can('create', Tag::class),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('tags/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTagRequest $request): RedirectResponse
    {
        // Save only the fields that passed the request's validation.
        Tag::create($request->validated());

        // Send success feedback to the screen.
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tag created successfully.',
        ]);

        // Return to the creation form.
        return back();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tag $tag): Response
    {
        // Send only the saved fields needed by the edit form.
        $tagDetails = $tag->only(['id', 'name']);

        return Inertia::render('tags/Edit', [
            'tag' => $tagDetails,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        // Rename the existing record without changing its ID or links.
        $tag->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tag updated successfully.',
        ]);

        // Follow Edit User's return-to-list behaviour after saving.
        return to_route('tags.index');
    }
}
