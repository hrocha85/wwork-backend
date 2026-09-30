<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
        Storage::fake('local');
    }

    public function test_invited_member_sets_and_replaces_own_photo(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk()->assertJsonPath('user.has_avatar', false);

        $this->get('/api/v1/me/avatar')->assertNotFound();

        $this->post('/api/v1/me/avatar', [
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ])->assertOk()->assertJsonPath('user.has_avatar', true);

        $invited = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $first = $invited->avatar_path;
        Storage::disk('local')->assertExists($first);
        $this->get('/api/v1/me/avatar')->assertOk();

        $this->post('/api/v1/me/avatar', [
            'photo' => UploadedFile::fake()->image('again.png'),
        ])->assertOk();

        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($invited->refresh()->avatar_path);
        $this->getJson('/api/v1/me')->assertJsonPath('user.has_avatar', true);
    }

    public function test_owner_sees_member_photo_and_member_cannot_browse_team_photos(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();
        $this->post('/api/v1/me/avatar', [
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ])->assertOk();

        $invited = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $this->get('/api/v1/team/'.$owner->id.'/avatar')->assertForbidden();

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $members = collect($this->getJson('/api/v1/team')->assertOk()->json('members'));
        $this->assertTrue($members->firstWhere('id', $invited->id)['has_avatar']);
        $this->assertFalse($members->firstWhere('id', $owner->id)['has_avatar']);

        $this->get('/api/v1/team/'.$invited->id.'/avatar')->assertOk();
        $this->get('/api/v1/team/'.$owner->id.'/avatar')->assertNotFound();
        $this->get('/api/v1/team/999999/avatar')->assertNotFound();
    }

    public function test_rejects_files_that_are_not_photos(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->post('/api/v1/me/avatar', [
            'photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertExactJson(['error' => 'agency.logo_invalid']);
    }
}
