<?php

use App\Models\Blog;
use App\Models\User;

it('shows published blogs on the index page', function () {
    Blog::factory()->published()->count(3)->create();
    Blog::factory()->create(['is_published' => false]);

    $this->get('/blogs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Blogs/Index')
            ->has('blogs.data', 3)
        );
});

it('shows the index page to guests', function () {
    $this->get('/blogs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Blogs/Index'));
});

it('shows a free blog to guests', function () {
    $blog = Blog::factory()->published()->free()->create();

    $this->get("/blogs/{$blog->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Blogs/Show')
            ->where('blog.id', $blog->id)
            ->has('contentHtml')
        );
});

it('shows a free blog to logged-in users', function () {
    $blog = Blog::factory()->published()->free()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get("/blogs/{$blog->slug}")
        ->assertOk();
});

it('denies guests access to a paid blog', function () {
    $blog = Blog::factory()->published()->create(['is_paid' => true]);

    $this->get("/blogs/{$blog->slug}")
        ->assertForbidden();
});

it('denies unpaid users access to a paid blog', function () {
    $blog = Blog::factory()->published()->create(['is_paid' => true]);
    $user = User::factory()->create(['is_paid' => false, 'is_newsletter_paid' => false]);

    $this->actingAs($user)
        ->get("/blogs/{$blog->slug}")
        ->assertForbidden();
});

it('allows paid users to access a paid blog', function () {
    $blog = Blog::factory()->published()->create(['is_paid' => true]);
    $user = User::factory()->create(['is_paid' => true]);

    $this->actingAs($user)
        ->get("/blogs/{$blog->slug}")
        ->assertOk();
});

it('allows newsletter paid users to access a paid blog', function () {
    $blog = Blog::factory()->published()->create(['is_paid' => true]);
    $user = User::factory()->create(['is_paid' => false, 'is_newsletter_paid' => true]);

    $this->actingAs($user)
        ->get("/blogs/{$blog->slug}")
        ->assertOk();
});

it('returns 404 for unpublished blogs', function () {
    $blog = Blog::factory()->create(['is_published' => false]);

    $this->get("/blogs/{$blog->slug}")
        ->assertNotFound();
});

it('returns 404 for non-existent slugs', function () {
    $this->get('/blogs/non-existent-slug')
        ->assertNotFound();
});

it('renders markdown content as HTML', function () {
    $blog = Blog::factory()->published()->free()->create([
        'content' => '# Hello World',
    ]);

    $this->get("/blogs/{$blog->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('contentHtml', fn ($html) => str_contains($html, '<h1>Hello World</h1>'))
        );
});
