<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_creates_main_and_sub_categories(): void
    {
        $this->get('/admin/categories/create')->assertOk();
        $this->post('/admin/categories', ['name' => 'Electronics', 'sort_order' => 1, 'is_active' => '1', 'image' => UploadedFile::fake()->image('e.jpg')])->assertRedirect('/admin/categories');

        $parent = Category::firstWhere('slug', 'electronics');
        $this->assertNotNull($parent->image_path);

        $this->post('/admin/categories', ['name' => 'Audio', 'parent_id' => $parent->id, 'is_active' => '1']);
        $this->assertSame($parent->id, Category::firstWhere('slug', 'audio')->parent_id);

        $this->get('/admin/categories')->assertOk()->assertSee('Electronics')->assertSee('Audio');
    }

    public function test_only_two_levels_are_allowed(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->post('/admin/categories', ['name' => 'Grandchild', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');
        $this->put("/admin/categories/{$parent->id}", ['name' => $parent->name, 'parent_id' => Category::factory()->create()->id])->assertSessionHasErrors('parent_id');
    }

    public function test_category_with_products_must_reassign_before_delete(): void
    {
        $old = Category::factory()->create();
        $new = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $old->id]);

        $this->get("/admin/categories/{$old->id}/delete")->assertOk()->assertSee('Move products to');
        $this->delete("/admin/categories/{$old->id}")->assertSessionHasErrors('target');
        $this->assertModelExists($old);

        $this->delete("/admin/categories/{$old->id}", ['target' => $new->id])->assertRedirect('/admin/categories');
        $this->assertModelMissing($old);
        $this->assertSame($new->id, $product->fresh()->category_id);
    }

    public function test_category_with_sub_categories_cannot_be_deleted(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $this->delete("/admin/categories/{$parent->id}")->assertSessionHasErrors('target');
        $this->assertModelExists($parent);
    }

    public function test_deactivating_a_main_category_hides_its_sub_category_products(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $product = Product::factory()->create(['category_id' => $child->id]);

        $this->get($product->url())->assertOk();
        $parent->update(['is_active' => false]);
        $this->get($product->url())->assertNotFound();
    }
}
