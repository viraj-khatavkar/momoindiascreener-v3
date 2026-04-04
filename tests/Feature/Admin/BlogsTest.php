<?php

use App\Models\Blog;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('shows the blogs index page', function () {
    Blog::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/admin/blogs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Blogs/Index')
            ->has('blogs.data', 3)
        );
});

it('filters blogs by title', function () {
    Blog::factory()->create(['title' => 'Laravel Tips']);
    Blog::factory()->create(['title' => 'Vue Guide']);

    $this->actingAs($this->admin)
        ->get('/admin/blogs?search=Laravel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Blogs/Index')
            ->has('blogs.data', 1)
            ->where('blogs.data.0.title', 'Laravel Tips')
        );
});

it('shows the create blog page', function () {
    $this->actingAs($this->admin)
        ->get('/admin/blogs/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Blogs/Create'));
});

it('stores a new blog post', function () {
    $this->actingAs($this->admin)
        ->post('/admin/blogs', [
            'title' => 'My First Post',
            'content' => 'This is the content.',
            'excerpt' => 'Short excerpt.',
            'featured_image' => '',
            'published_at' => null,
            'is_published' => false,
            'is_paid' => true,
        ])
        ->assertRedirect('/admin/blogs');

    $this->assertDatabaseHas('blogs', [
        'title' => 'My First Post',
        'slug' => 'my-first-post',
        'is_published' => false,
        'published_at' => null,
    ]);
});

it('stores a blog post with a manual published_at date', function () {
    $this->actingAs($this->admin)
        ->post('/admin/blogs', [
            'title' => 'Scheduled Post',
            'content' => 'Content here.',
            'excerpt' => '',
            'featured_image' => '',
            'published_at' => '2026-05-01 10:00:00',
            'is_published' => true,
            'is_paid' => false,
        ])
        ->assertRedirect('/admin/blogs');

    $blog = Blog::where('slug', 'scheduled-post')->first();
    expect($blog->published_at->format('Y-m-d'))->toBe('2026-05-01');
    expect($blog->is_paid)->toBeFalse();
});

it('validates required fields when storing', function () {
    $this->actingAs($this->admin)
        ->post('/admin/blogs', [
            'title' => '',
            'content' => '',
            'is_published' => false,
            'is_paid' => true,
        ])
        ->assertSessionHasErrors(['title', 'content']);
});

it('shows the edit blog page', function () {
    $blog = Blog::factory()->create();

    $this->actingAs($this->admin)
        ->get("/admin/blogs/{$blog->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Blogs/Edit')
            ->where('blog.id', $blog->id)
        );
});

it('updates a blog post', function () {
    $blog = Blog::factory()->create(['title' => 'Old Title']);

    $this->actingAs($this->admin)
        ->put("/admin/blogs/{$blog->id}", [
            'title' => 'New Title',
            'content' => 'Updated content.',
            'excerpt' => '',
            'featured_image' => '',
            'published_at' => null,
            'is_published' => false,
            'is_paid' => true,
        ])
        ->assertRedirect('/admin/blogs');

    $fresh = $blog->fresh();
    expect($fresh->title)->toBe('New Title');
    expect($fresh->slug)->toBe('new-title');
});

it('updates published_at to a manual date', function () {
    $blog = Blog::factory()->create(['published_at' => null]);

    $this->actingAs($this->admin)
        ->put("/admin/blogs/{$blog->id}", [
            'title' => $blog->title,
            'content' => $blog->content,
            'excerpt' => '',
            'featured_image' => '',
            'published_at' => '2026-06-15 09:30:00',
            'is_published' => true,
            'is_paid' => true,
        ])
        ->assertRedirect('/admin/blogs');

    expect($blog->fresh()->published_at->format('Y-m-d'))->toBe('2026-06-15');
});

it('clears published_at when set to null', function () {
    $blog = Blog::factory()->published()->create();

    $this->actingAs($this->admin)
        ->put("/admin/blogs/{$blog->id}", [
            'title' => $blog->title,
            'content' => $blog->content,
            'excerpt' => '',
            'featured_image' => '',
            'published_at' => null,
            'is_published' => false,
            'is_paid' => true,
        ])
        ->assertRedirect('/admin/blogs');

    expect($blog->fresh()->published_at)->toBeNull();
});

it('denies access to non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/admin/blogs')
        ->assertNotFound();
});
