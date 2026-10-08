<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSocialPostRequest;
use App\Models\SocialPost;
use App\Support\Social\SocialPostStatus;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Social-Media-Beiträge planen. Das Veröffentlichen auf LinkedIn und
 * Facebook kommt in einem späteren Schritt.
 */
class SocialPostController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/SocialPosts/Index', [
            'posts' => SocialPost::query()
                ->latest('updated_at')
                ->get()
                ->map(fn (SocialPost $post): array => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'content' => $post->content,
                    'platforms' => $post->platforms,
                    'status' => $post->status,
                    'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                    'updated_at' => $post->updated_at->toIso8601String(),
                ]),
            'platforms' => SocialPost::PLATFORMS,
            'statuses' => SocialPostStatus::badges(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/SocialPosts/Create', [
            'platforms' => SocialPost::PLATFORMS,
            'maxContentLength' => SocialPost::MAX_CONTENT_LENGTH,
        ]);
    }

    public function store(StoreSocialPostRequest $request): RedirectResponse
    {
        SocialPost::create($request->postData());

        return to_route('admin.social-posts.index')->with('success', 'Beitrag angelegt.');
    }

    public function edit(SocialPost $socialPost): Response
    {
        return Inertia::render('Admin/SocialPosts/Edit', [
            'post' => [
                'id' => $socialPost->id,
                'title' => $socialPost->title,
                'content' => $socialPost->content,
                'platforms' => $socialPost->platforms,
                'status' => $socialPost->status,
                'scheduled_at' => $socialPost->scheduled_at?->toIso8601String(),
                'editable' => $socialPost->isEditable(),
            ],
            'platforms' => SocialPost::PLATFORMS,
            'maxContentLength' => SocialPost::MAX_CONTENT_LENGTH,
            'statuses' => SocialPostStatus::badges(),
        ]);
    }

    public function update(StoreSocialPostRequest $request, SocialPost $socialPost): RedirectResponse
    {
        if (! $socialPost->isEditable()) {
            return back()->with('error', 'Veröffentlichte Beiträge können nicht mehr geändert werden.');
        }

        $socialPost->update($request->postData());

        return to_route('admin.social-posts.index')->with('success', 'Beitrag gespeichert.');
    }

    public function destroy(SocialPost $socialPost): RedirectResponse
    {
        $socialPost->delete();

        return to_route('admin.social-posts.index')->with('success', 'Beitrag gelöscht.');
    }
}
