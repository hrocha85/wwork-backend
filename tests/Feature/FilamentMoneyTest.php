<?php

namespace Tests\Feature;

use App\Actions\Auth\InvitePaidOwner;
use App\Actions\Subscription\AssignPlan;
use App\Enums\MembershipRole;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Resources\Agencies\Pages\ViewAgency;
use App\Mail\OwnerWelcomeMail;
use App\Models\Activity;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Membership;
use App\Models\PlanPrice;
use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentMoneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_finance_assigns_complimentary_and_founder_sees_the_dashboard(): void
    {
        $finance = $this->staff('finance@wwork.app');
        $agency = Agency::query()->firstOrFail();

        $this->actingAs($finance, 'staff');

        Livewire::test(ViewAgency::class, ['record' => $agency->getRouteKey()])
            ->callAction('assignPlan', [
                'plan' => PlanCode::Basic->value,
                'reason' => 'complimentary',
                'until' => '2026-12-01',
            ])
            ->assertHasNoActionErrors();

        $agency->refresh();
        $this->assertSame(SubscriptionStatus::Complimentary, $agency->subscription->status);
        $this->assertNotNull($agency->subscription->complimentary_until);
        $this->assertTrue(
            Activity::query()->where('agency_id', $agency->id)->where('action', 'subscription.assigned')->exists(),
        );

        $founder = $this->staff('founder@wwork.app');
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        Client::query()->create([
            'agency_id' => $agency->id,
            'created_by' => $owner->id,
            'name' => 'Hidden House',
            'whatsapp' => '+447700900111',
            'address' => 'SECRET-HOUSE-10-DOWNING',
            'lat' => 51.5,
            'lng' => -0.12,
        ]);

        $this->actingAs($founder, 'staff');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('MRR')
            ->assertSee('Cadastros por ofício')
            ->assertSee('Cortesia')
            ->assertDontSee('SECRET-HOUSE-10-DOWNING');
    }

    public function test_basic_plan_refuses_a_team_larger_than_its_seats(): void
    {
        $agency = Agency::query()->firstOrFail();
        foreach ([1, 2] as $index) {
            $user = User::query()->create([
                'name' => 'Extra '.$index,
                'email' => 'extra'.$index.'@wwork.test',
                'password' => 'extra-seat-test',
            ]);
            Membership::query()->create([
                'agency_id' => $agency->id,
                'user_id' => $user->id,
                'role' => MembershipRole::Invited,
            ]);
        }

        try {
            app(AssignPlan::class)($agency, [
                'plan' => PlanCode::Basic->value,
                'reason' => 'complimentary',
                'until' => '2026-12-01',
            ], null);
            $this->fail('Expected plan.seats_exceeded.');
        } catch (ApiException $exception) {
            $this->assertSame(ErrorCodes::PLAN_SEATS_EXCEEDED, $exception->error);
        }
    }

    public function test_support_cannot_assign_or_invite(): void
    {
        $support = $this->staff('support@wwork.app');
        $agency = Agency::query()->firstOrFail();

        $this->actingAs($support, 'staff');

        Livewire::test(ViewAgency::class, ['record' => $agency->getRouteKey()])
            ->assertActionHidden('assignPlan');

        Livewire::test(ListAgencies::class)
            ->assertActionHidden('inviteOwner');

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('MRR');
    }

    public function test_paid_offline_owner_is_invited_in_english_and_a_duplicate_email_is_refused(): void
    {
        Mail::fake();
        $finance = $this->staff('finance@wwork.app');
        $this->actingAs($finance, 'staff');

        Livewire::test(ListAgencies::class)
            ->callAction('inviteOwner', [
                'agency_name' => 'Paid Offline Co',
                'trade' => 'cleaning',
                'name' => 'Offline Owner',
                'email' => 'offline@wwork.test',
                'password' => 'temp-pass-1',
                'plan' => PlanCode::Basic->value,
                'until' => '2026-12-01',
            ])
            ->assertHasNoActionErrors();

        $owner = User::query()->where('email', 'offline@wwork.test')->firstOrFail();
        $this->assertTrue($owner->must_change_password);
        $this->assertNull($owner->terms_accepted_at);
        $this->assertSame('GB', $owner->membership->agency->country);
        $this->assertSame(SubscriptionStatus::PaidOffline, $owner->membership->agency->subscription->status);
        $this->assertSame('Europe/London', $owner->membership->agency->timezone);

        $price = PlanPrice::query()
            ->where('country', 'GB')
            ->where('plan', PlanCode::Basic->value)
            ->where('billing', 'monthly')
            ->where('discount_type', 'none')
            ->firstOrFail();
        $this->assertSame($price->amount_minor, $owner->membership->agency->subscription->amount_minor);

        Mail::assertQueued(OwnerWelcomeMail::class, function (OwnerWelcomeMail $mail): bool {
            return $mail->hasTo('offline@wwork.test')
                && $mail->temporaryPassword === 'temp-pass-1'
                && str_contains($mail->appUrl, '/login');
        });

        try {
            app(InvitePaidOwner::class)([
                'agency_name' => 'Second',
                'trade' => 'cleaning',
                'name' => 'Again',
                'email' => 'offline@wwork.test',
                'password' => 'temp-pass-2',
                'plan' => PlanCode::Basic->value,
            ], $finance->id);
            $this->fail('Expected register.email_taken.');
        } catch (ApiException $exception) {
            $this->assertSame(ErrorCodes::REGISTER_EMAIL_TAKEN, $exception->error);
        }
    }

    private function staff(string $email): User
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $user->forceFill(['must_change_password' => false])->save();

        return $user;
    }
}
