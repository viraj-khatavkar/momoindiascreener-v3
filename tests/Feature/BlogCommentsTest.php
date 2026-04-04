<?php

use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;

beforeEach(function () {
    $this->blog = Blog::factory()->published()->free()->create();
    $this->user = User::factory()->create();
});

it('shows comments on the blog show page', function () {
    $comment = Comment::factory()->create(['blog_id' => $this->blog->id]);

    $this->get("/blogs/{$this->blog->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Blogs/Show')
            ->has('comments.data', 1)
        );
});

it('loads replies nested under their parent comment', function () {
    $parent = Comment::factory()->create(['blog_id' => $this->blog->id]);
    Comment::factory()->reply($parent)->count(2)->create();

    $this->get("/blogs/{$this->blog->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('comments.data', 1)
            ->has('comments.data.0.replies', 2)
        );
});

it('allows authenticated users to post a comment', function () {
    $this->actingAs($this->user)
        ->post("/blogs/{$this->blog->slug}/comments", [
            'body' => 'Great post!',
            'parent_id' => null,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('comments', [
        'blog_id' => $this->blog->id,
        'user_id' => $this->user->id,
        'body' => 'Great post!',
        'parent_id' => null,
    ]);
});

it('validates comment body is required', function () {
    $this->actingAs($this->user)
        ->post("/blogs/{$this->blog->slug}/comments", [
            'body' => '',
            'parent_id' => null,
        ])
        ->assertSessionHasErrors(['body']);
});

it('validates comment body max length', function () {
    $this->actingAs($this->user)
        ->post("/blogs/{$this->blog->slug}/comments", [
            'body' => str_repeat('a', 10001),
            'parent_id' => null,
        ])
        ->assertSessionHasErrors(['body']);
});

it('prevents guests from posting comments', function () {
    $this->post("/blogs/{$this->blog->slug}/comments", [
        'body' => 'Trying to comment',
        'parent_id' => null,
    ])->assertRedirect('/login');
});

it('allows replying to a top-level comment', function () {
    $parent = Comment::factory()->create(['blog_id' => $this->blog->id]);

    $this->actingAs($this->user)
        ->post("/blogs/{$this->blog->slug}/comments", [
            'body' => 'Nice reply!',
            'parent_id' => $parent->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('comments', [
        'blog_id' => $this->blog->id,
        'parent_id' => $parent->id,
        'body' => 'Nice reply!',
    ]);
});

it('flattens reply-to-reply to top-level parent', function () {
    $topLevel = Comment::factory()->create(['blog_id' => $this->blog->id]);
    $reply = Comment::factory()->reply($topLevel)->create();

    $this->actingAs($this->user)
        ->post("/blogs/{$this->blog->slug}/comments", [
            'body' => 'Reply to reply',
            'parent_id' => $reply->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('comments', [
        'body' => 'Reply to reply',
        'parent_id' => $topLevel->id,
    ]);
});

it('validates parent_id exists', function () {
    $this->actingAs($this->user)
        ->post("/blogs/{$this->blog->slug}/comments", [
            'body' => 'A comment',
            'parent_id' => 9999,
        ])
        ->assertSessionHasErrors(['parent_id']);
});

it('allows comment author to delete their own comment', function () {
    $comment = Comment::factory()->create([
        'blog_id' => $this->blog->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->delete("/comments/{$comment->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

it('allows admin to delete any comment', function () {
    $comment = Comment::factory()->create(['blog_id' => $this->blog->id]);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->delete("/comments/{$comment->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

it('prevents non-author non-admin from deleting a comment', function () {
    $comment = Comment::factory()->create(['blog_id' => $this->blog->id]);

    $this->actingAs($this->user)
        ->delete("/comments/{$comment->id}")
        ->assertForbidden();
});

it('cascade-deletes comments when blog is deleted', function () {
    Comment::factory()->count(3)->create(['blog_id' => $this->blog->id]);

    $this->blog->delete();

    expect(Comment::where('blog_id', $this->blog->id)->count())->toBe(0);
});

it('cascade-deletes replies when parent comment is deleted', function () {
    $parent = Comment::factory()->create(['blog_id' => $this->blog->id]);
    Comment::factory()->reply($parent)->count(2)->create();

    $parent->delete();

    expect(Comment::where('parent_id', $parent->id)->count())->toBe(0);
});
