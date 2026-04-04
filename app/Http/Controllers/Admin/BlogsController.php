<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class BlogsController extends Controller
{
    public function index(Request $request): Response
    {
        $blogs = Blog::query()
            ->when($request->search, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return inertia('Admin/Blogs/Index', [
            'blogs' => $blogs,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return inertia('Admin/Blogs/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'is_published' => ['required', 'boolean'],
            'is_paid' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $validated['slug'] = Str::slug($validated['title']);

        Blog::create($validated);

        return redirect('/admin/blogs')->with('success', 'Blog post created successfully.');
    }

    public function edit(Blog $blog): Response
    {
        return inertia('Admin/Blogs/Edit', [
            'blog' => $blog,
        ]);
    }

    public function update(Request $request, Blog $blog): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'is_published' => ['required', 'boolean'],
            'is_paid' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $validated['slug'] = Str::slug($validated['title']);

        $blog->update($validated);

        return redirect('/admin/blogs')->with('success', 'Blog post updated successfully.');
    }
}
