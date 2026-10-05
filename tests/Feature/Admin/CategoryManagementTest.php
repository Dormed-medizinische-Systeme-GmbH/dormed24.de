<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_categories(): void
    {
        Category::factory()->count(3)->create();

        $this->actingAsAdmin()
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Categories/Index')->has('categories'));
    }

    public function test_edit_page_renders(): void
    {
        $category = Category::factory()->create();

        $this->actingAsAdmin()
            ->get(route('admin.categories.edit', $category))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('category.id', $category->id));
    }

    public function test_category_can_be_updated(): void
    {
        $category = Category::factory()->create();

        $this->actingAsAdmin()
            ->put(route('admin.categories.update', $category), [
                'name' => 'Beatmungstechnik',
                'slug' => 'beatmungstechnik',
                'description' => 'Alles rund um Beatmung',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Beatmungstechnik',
            'slug' => 'beatmungstechnik',
        ]);
    }

    public function test_update_rejects_reserved_slug(): void
    {
        $category = Category::factory()->create();

        $this->actingAsAdmin()
            ->put(route('admin.categories.update', $category), [
                'name' => 'Test',
                'slug' => 'admin',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_update_rejects_duplicate_slug(): void
    {
        Category::factory()->create(['slug' => 'monitoring']);
        $category = Category::factory()->create(['slug' => 'pflege']);

        $this->actingAsAdmin()
            ->put(route('admin.categories.update', $category), [
                'name' => 'Test',
                'slug' => 'monitoring',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_update_rejects_invalid_slug_format(): void
    {
        $category = Category::factory()->create();

        $this->actingAsAdmin()
            ->put(route('admin.categories.update', $category), [
                'name' => 'Test',
                'slug' => 'Groß Geschrieben',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAsAdmin()
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
