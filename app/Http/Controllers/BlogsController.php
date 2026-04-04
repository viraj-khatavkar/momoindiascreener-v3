<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogsController extends Controller
{
    public function index(): Response
    {
        $blogs = Blog::query()
            ->where('is_published', true)
            ->latest('published_at')
            ->paginate(20);

        return inertia('Blogs/Index', [
            'blogs' => $blogs,
        ]);
    }

    public function show(Blog $blog): Response
    {
        if (! $blog->is_published) {
            abort(404);
        }

        if ($blog->is_paid) {
            $user = auth()->user();

            if (! $user || (! $user->is_paid && ! $user->is_newsletter_paid)) {
                abort(403);
            }
        }

        return inertia('Blogs/Show', [
            'blog' => $blog,
            'contentHtml' => Str::markdown($blog->content, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            'comments' => Inertia::scroll(
                fn () => $blog->comments()
                    ->whereNull('parent_id')
                    ->with(['user:id,name', 'replies' => fn ($q) => $q->with('user:id,name')->oldest()])
                    ->latest()
                    ->simplePaginate(15, pageName: 'comments')
            ),
        ]);
    }
}
