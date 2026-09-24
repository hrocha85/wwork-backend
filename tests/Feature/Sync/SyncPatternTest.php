<?php

namespace Tests\Feature\Sync;

use App\Models\Agency;
use App\Models\Membership;
use App\Models\SyncDeletion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * O trio de sincronização que a fatia 1 entrega mesmo sem app offline:
 * sync_uuid único desde a criação, soft delete e lápide em sync_deletions.
 *
 * É o contrato que o React Native futuro vai ler sem pedir migração. O molde
 * é o da Samaúma, com a coluna agency_id a mais no tombstone.
 */
class SyncPatternTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_uuid_e_gerado_so_nas_tres_tabelas(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->owner()->create();
        $membership = Membership::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
        ]);

        foreach ([$agency, $owner, $membership] as $model) {
            $this->assertNotEmpty($model->sync_uuid, class_basename($model).' ficou sem sync_uuid.');
            $this->assertTrue(Str::isUuid($model->sync_uuid), $model->sync_uuid.' não é uuid.');
        }

        $this->assertSame(
            3,
            DB::table('agencies')->where('sync_uuid', $agency->sync_uuid)->count()
                + DB::table('users')->where('sync_uuid', $owner->sync_uuid)->count()
                + DB::table('memberships')->where('sync_uuid', $membership->sync_uuid)->count(),
        );
    }

    public function test_sync_uuid_vindo_de_fora_e_respeitado(): void
    {
        $fromDevice = (string) Str::uuid();

        $agency = Agency::factory()->create(['sync_uuid' => $fromDevice]);

        $this->assertSame($fromDevice, $agency->sync_uuid);
    }

    public function test_sync_uuid_e_unico_em_agencies(): void
    {
        $agency = Agency::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('agencies')->insert([
            'sync_uuid' => $agency->sync_uuid,
            'name' => 'outra',
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_sync_uuid_e_unico_em_users(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'sync_uuid' => $user->sync_uuid,
            'name' => 'outra pessoa',
            'email' => 'outra@wwork.test',
            'password' => 'x',
            'role' => 'owner',
            'locale' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_sync_uuid_e_unico_em_memberships(): void
    {
        $membership = Membership::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('memberships')->insert([
            'sync_uuid' => $membership->sync_uuid,
            'agency_id' => $membership->agency_id,
            'user_id' => $membership->user_id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_soft_delete_de_agency_grava_a_lapide(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->owner()->create();
        Membership::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
        ]);

        $uuid = $agency->sync_uuid;
        $agency->delete();

        $this->assertSoftDeleted('agencies', ['id' => $agency->id]);

        $tombstone = SyncDeletion::query()->where('sync_uuid', $uuid)->sole();
        $this->assertSame('agency', $tombstone->entity_type);
        $this->assertSame($agency->id, $tombstone->server_id);
        $this->assertSame($agency->id, $tombstone->agency_id);
        $this->assertContains($owner->id, $tombstone->affected_user_ids);
        $this->assertNotNull($tombstone->deleted_at);
    }

    public function test_soft_delete_de_user_grava_a_lapide(): void
    {
        $owner = User::factory()->owner()->create();

        $uuid = $owner->sync_uuid;
        $owner->delete();

        $this->assertSoftDeleted('users', ['id' => $owner->id]);

        $tombstone = SyncDeletion::query()->where('sync_uuid', $uuid)->sole();
        $this->assertSame('user', $tombstone->entity_type);
        $this->assertSame($owner->id, $tombstone->server_id);
    }

    public function test_soft_delete_de_membership_grava_a_lapide_com_a_agencia(): void
    {
        $membership = Membership::factory()->create();

        $uuid = $membership->sync_uuid;
        $membership->delete();

        $this->assertSoftDeleted('memberships', ['id' => $membership->id]);

        $tombstone = SyncDeletion::query()->where('sync_uuid', $uuid)->sole();
        $this->assertSame('membership', $tombstone->entity_type);
        $this->assertSame($membership->agency_id, $tombstone->agency_id);
        $this->assertSame([$membership->user_id], $tombstone->affected_user_ids);
    }

    public function test_vinculo_apagado_deixa_de_contar_mas_pode_ser_recriado(): void
    {
        $agency = Agency::factory()->create();
        $invited = User::factory()->invited()->create();

        $first = Membership::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $invited->id,
        ]);
        $first->delete();

        $this->assertSame(
            0,
            Membership::query()
                ->where('agency_id', $agency->id)
                ->where('user_id', $invited->id)
                ->count(),
        );

        // Reconvidar a mesma pessoa não estoura a chave única: o vínculo velho
        // continua apagado e o novo nasce vivo.
        $second = Membership::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $invited->id,
        ]);

        $this->assertNotSame($first->id, $second->id);
        $this->assertTrue($second->exists);
    }
}
