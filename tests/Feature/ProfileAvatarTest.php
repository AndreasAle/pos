<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(["pos.features.subscription" => false]);
    }

    private function user(?Business $business = null): User
    {
        $business ??= Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        return User::factory()->create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'role' => 'owner']);
    }

    public function test_user_can_upload_a_photo_which_is_cropped_to_a_square_jpeg(): void
    {
        Storage::fake('local');
        $user = $this->user();
        $res = $this->actingAs($user)->postJson(route('profile.avatar.store'), ['photo' => UploadedFile::fake()->image('me.png', 600, 300)]);

        $res->assertOk()->assertJsonStructure(['message', 'url']);
        $path = $user->fresh()->avatar;
        $this->assertStringStartsWith('avatars/'.$user->business_id.'/', $path);
        Storage::disk('local')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame([480, 480], [$w, $h]);
    }

    public function test_non_image_and_oversized_files_are_rejected(): void
    {
        Storage::fake('local');
        $user = $this->user();
        $this->actingAs($user)->postJson(route('profile.avatar.store'), ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertStatus(422);
        $this->actingAs($user)->postJson(route('profile.avatar.store'), ['photo' => UploadedFile::fake()->image('big.jpg', 600, 600)->size(6000)])->assertStatus(422);
        $this->assertNull($user->fresh()->avatar);
    }

    public function test_avatar_is_only_served_inside_the_same_business(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $this->actingAs($owner)->postJson(route('profile.avatar.store'), ['photo' => UploadedFile::fake()->image('me.jpg', 300, 300)])->assertOk();
        $teammate = User::factory()->create(['business_id' => $owner->business_id, 'outlet_id' => $owner->outlet_id, 'role' => 'cashier']);
        $stranger = $this->user();

        $this->actingAs($teammate)->get(route('avatar.show', $owner))->assertOk();
        $this->actingAs($stranger)->get(route('avatar.show', $owner))->assertForbidden();
    }

    public function test_removing_the_photo_deletes_the_file_and_falls_back_to_google_photo(): void
    {
        Storage::fake('local');
        $user = $this->user();
        $user->forceFill(['google_avatar' => 'https://lh3.googleusercontent.com/a/abc'])->save();
        $this->actingAs($user)->postJson(route('profile.avatar.store'), ['photo' => UploadedFile::fake()->image('me.jpg', 300, 300)])->assertOk();
        $path = $user->fresh()->avatar;
        $this->assertStringContainsString('/avatars/', $user->fresh()->avatarUrl());

        $this->actingAs($user)->deleteJson(route('profile.avatar.destroy'))->assertOk()->assertJson(['url' => 'https://lh3.googleusercontent.com/a/abc']);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_profile_page_renders_for_google_users_with_the_google_badge(): void
    {
        $user = $this->user();
        $user->forceFill(['google_id' => 'g-123'])->save();

        $this->actingAs($user)->get(route('profile'))->assertOk()->assertSee('Terhubung dengan Google', false)->assertSee('Unggah foto');
    }
}
