<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\NotificationReminderGenerator;
use App\Domain\Notifications\NotificationRuleCatalog;
use App\Domain\Operations\OperationalDataResetService;
use App\Jobs\SendNotificationEmail;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\Customer;
use App\Models\InventoryAudit;
use App\Models\NotificationMessage;
use App\Models\NotificationPreference;
use App\Models\NotificationRule;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationReminderCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_seeds_permissions_rules_and_role_scoped_center(): void
    {
        $branch = $this->foundation();
        $manager = $this->branchUser($branch, 'branch-manager', 'notify.manager@example.test');
        $owner = $this->branchUser($branch, 'owner-management', 'notify.owner@example.test');

        $this->assertSame(
            count(NotificationRuleCatalog::definitions()),
            NotificationRule::query()->where('company_id', $branch->company_id)->count(),
        );

        $this->actingAs($manager)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/index')
                ->where('permissions.manage', false)
                ->has('rules', 0)
                ->where('summary.unread', 0));

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.manage', true)
                ->has('rules', count(NotificationRuleCatalog::definitions())));
    }

    public function test_upcoming_booking_notification_is_idempotent_and_drills_down(): void
    {
        $branch = $this->foundation();
        $manager = $this->branchUser($branch, 'branch-manager', 'booking.notify@example.test');
        $customer = $this->customer($branch);
        $booking = Booking::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_number' => 'PNG-BKG-NOTIFY-001',
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => now(),
            'starts_at' => now()->addHours(6),
            'ends_at' => now()->addDay(),
            'total_amount' => 350000,
            'created_by' => $manager->id,
        ]);

        $first = app(NotificationReminderGenerator::class)->generateForCompany((int) $branch->company_id);
        $second = app(NotificationReminderGenerator::class)->generateForCompany((int) $branch->company_id);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertDatabaseCount('notification_messages', 1);
        $this->assertDatabaseHas('notification_messages', [
            'user_id' => $manager->id,
            'branch_id' => $branch->id,
            'rule_code' => 'booking.starting_soon',
            'source_type' => Booking::class,
            'source_id' => $booking->id,
            'occurrences' => 1,
            'action_url' => '/bookings/'.$booking->id,
        ]);

        $this->actingAs($manager)
            ->get(route('notifications.index', ['state' => 'unread']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.unread', 1)
                ->where('messages.data.0.action_url', '/bookings/'.$booking->id));
    }

    public function test_overdue_reminder_repeats_after_interval_and_resolves_when_source_closes(): void
    {
        $branch = $this->foundation();
        $manager = $this->branchUser($branch, 'branch-manager', 'overdue.notify@example.test');
        $customer = $this->customer($branch);
        $rental = Rental::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rental_number' => 'PNG-RNT-NOTIFY-001',
            'status' => 'active',
            'checked_out_at' => now()->subDays(2),
            'due_at' => now()->subHours(2),
            'total_amount' => 400000,
            'paid_amount' => 400000,
            'balance_due' => 0,
            'created_by' => $manager->id,
        ]);

        app(NotificationReminderGenerator::class)->generateForCompany((int) $branch->company_id);
        $message = NotificationMessage::query()
            ->where('rule_code', 'rental.overdue')
            ->firstOrFail();
        $this->assertSame('critical', $message->severity);

        $message->forceFill([
            'read_at' => now(),
            'last_triggered_at' => now()->subDays(2),
        ])->save();
        app(NotificationReminderGenerator::class)->generateForCompany((int) $branch->company_id);
        $message->refresh();
        $this->assertSame(2, $message->occurrences);
        $this->assertNull($message->read_at);

        $rental->forceFill(['status' => 'completed', 'returned_at' => now()])->save();
        app(NotificationReminderGenerator::class)->generateForCompany((int) $branch->company_id);
        $this->assertNotNull($message->fresh()->resolved_at);
    }

    public function test_branch_recipients_and_inbox_actions_are_strictly_isolated(): void
    {
        $branch = $this->foundation();
        $other = $this->otherBranch($branch);
        $manager = $this->branchUser($branch, 'branch-manager', 'png.notify@example.test');
        $foreign = $this->branchUser($other, 'branch-manager', 'mdn.notify@example.test');
        $customer = $this->customer($branch);
        Booking::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_number' => 'PNG-BKG-ISOLATION-001',
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => now(),
            'starts_at' => now()->addHours(4),
            'ends_at' => now()->addDay(),
            'created_by' => $manager->id,
        ]);

        app(NotificationReminderGenerator::class)->generateForCompany((int) $branch->company_id);
        $message = NotificationMessage::query()->firstOrFail();
        $this->assertSame($manager->id, $message->user_id);
        $this->assertDatabaseMissing('notification_messages', ['user_id' => $foreign->id]);

        $this->actingAs($foreign)
            ->patch(route('notifications.read', $message))
            ->assertNotFound();
        $this->actingAs($foreign)
            ->get(route('notifications.index', ['branch_id' => $branch->id]))
            ->assertNotFound();
    }

    public function test_user_can_read_unread_snooze_dismiss_and_update_preferences(): void
    {
        $branch = $this->foundation();
        $manager = $this->branchUser($branch, 'branch-manager', 'actions.notify@example.test');
        $message = $this->message($manager, $branch, 'system.action-test');

        $this->actingAs($manager)
            ->patch(route('notifications.read', $message))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($message->fresh()->read_at);

        $this->actingAs($manager)
            ->patch(route('notifications.unread', $message))
            ->assertSessionHasNoErrors();
        $this->assertNull($message->fresh()->read_at);

        $this->actingAs($manager)
            ->post(route('notifications.snooze', $message), ['minutes' => 1440])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($message->fresh()->snoozed_until);

        $this->actingAs($manager)
            ->put(route('notifications.preferences.update'), [
                'email_enabled' => true,
                'email_min_severity' => 'warning',
                'muted_categories' => ['booking'],
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '06:00',
            ])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $manager->id,
            'email_enabled' => true,
            'email_min_severity' => 'warning',
        ]);

        $this->actingAs($manager)
            ->delete(route('notifications.dismiss', $message))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($message->fresh()->dismissed_at);
        $this->assertDatabaseHas('activity_logs', ['event' => 'notification.dismissed']);
    }

    public function test_verified_user_with_email_preference_queues_delivery(): void
    {
        Queue::fake();
        $branch = $this->foundation();
        $manager = $this->branchUser($branch, 'branch-manager', 'email.notify@example.test');
        NotificationPreference::query()->create([
            'user_id' => $manager->id,
            'email_enabled' => true,
            'email_min_severity' => 'info',
            'muted_categories' => [],
        ]);
        $customer = $this->customer($branch);
        Booking::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_number' => 'PNG-BKG-EMAIL-001',
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => now(),
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addDay(),
            'created_by' => $manager->id,
        ]);

        app(NotificationReminderGenerator::class)->generateForCompany((int) $branch->company_id);

        Queue::assertPushed(SendNotificationEmail::class, 1);
        $this->assertDatabaseHas('notification_messages', [
            'user_id' => $manager->id,
            'email_status' => 'queued',
        ]);
    }

    public function test_only_management_can_change_rules_or_run_manual_scan(): void
    {
        $branch = $this->foundation();
        $owner = $this->branchUser($branch, 'owner-management', 'rule.owner@example.test');
        $manager = $this->branchUser($branch, 'branch-manager', 'rule.manager@example.test');
        $rule = NotificationRule::query()
            ->where('company_id', $branch->company_id)
            ->where('code', 'rental.overdue')
            ->firstOrFail();
        $payload = [
            'is_enabled' => false,
            'severity' => 'warning',
            'lead_minutes' => 30,
            'repeat_minutes' => 720,
        ];

        $this->actingAs($manager)
            ->put(route('notifications.rules.update', $rule), $payload)
            ->assertForbidden();
        $this->actingAs($owner)
            ->put(route('notifications.rules.update', $rule), $payload)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notification_rules', [
            'id' => $rule->id,
            'is_enabled' => false,
            'severity' => 'warning',
            'lead_minutes' => 30,
            'repeat_minutes' => 720,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($manager)
            ->post(route('notifications.generate'))
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('notifications.generate'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_logs', ['event' => 'notification.scan_completed']);
    }

    public function test_finance_transfer_inventory_triggers_and_operational_reset_cleanup(): void
    {
        $branch = $this->foundation();
        $other = $this->otherBranch($branch);
        $manager = $this->branchUser($branch, 'branch-manager', 'multi.notify@example.test');
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $refund = Refund::query()->create([
            'branch_id' => $branch->id,
            'payment_method_id' => $method->id,
            'refund_number' => 'PNG-RFD-NOTIFY-001',
            'refund_type' => 'partial',
            'amount' => 100000,
            'status' => 'requested',
            'reason' => 'Integration notification test.',
            'requested_by' => $manager->id,
        ]);
        $transfer = BranchTransfer::query()->create([
            'company_id' => $branch->company_id,
            'from_branch_id' => $branch->id,
            'to_branch_id' => $other->id,
            'transfer_number' => 'PNG-TRF-NOTIFY-001',
            'status' => 'pending_approval',
            'reason' => 'Integration notification test.',
            'requested_by' => $manager->id,
            'requested_at' => now(),
        ]);
        $audit = InventoryAudit::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'audit_number' => 'PNG-SO-NOTIFY-001',
            'title' => 'Stock Opname Notification Test',
            'status' => 'submitted',
            'snapshot_item_count' => 0,
            'submitted_by' => $manager->id,
            'submitted_at' => now(),
            'created_by' => $manager->id,
        ]);

        app(NotificationReminderGenerator::class)->generateForCompany((int) $branch->company_id);

        $this->assertDatabaseHas('notification_messages', [
            'rule_code' => 'refund.awaiting_approval',
            'source_id' => $refund->id,
        ]);
        $this->assertDatabaseHas('notification_messages', [
            'rule_code' => 'transfer.awaiting_approval',
            'source_id' => $transfer->id,
        ]);
        $this->assertDatabaseHas('notification_messages', [
            'rule_code' => 'inventory.awaiting_approval',
            'source_id' => $audit->id,
        ]);
        $preview = app(OperationalDataResetService::class)->preview(
            (int) $branch->company_id,
            $branch,
        );
        $this->assertGreaterThanOrEqual(3, $preview['notifications']);

        app(OperationalDataResetService::class)->reset(
            (int) $branch->company_id,
            $branch,
            false,
        );
        $this->assertDatabaseMissing('notification_messages', ['branch_id' => $branch->id]);
    }

    private function foundation(): Branch
    {
        $this->seed(RentalFoundationSeeder::class);

        return Branch::query()->where('code', 'PNG')->firstOrFail();
    }

    private function otherBranch(Branch $branch): Branch
    {
        return Branch::query()->firstOrCreate(
            ['company_id' => $branch->company_id, 'code' => 'MDN'],
            [
                'name' => 'Together Kamera Madiun',
                'city' => 'Madiun',
                'province' => 'Jawa Timur',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
        );
    }

    private function branchUser(Branch $branch, string $roleSlug, string $email): User
    {
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email' => $email,
            'email_verified_at' => now(),
        ]);
        $role = Role::query()
            ->where('company_id', $branch->company_id)
            ->where('slug', $roleSlug)
            ->firstOrFail();
        $user->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id, [
            'branch_id' => $role->scope === 'company' ? null : $branch->id,
            'assigned_at' => now(),
        ]);

        return $user;
    }

    private function customer(Branch $branch): Customer
    {
        return Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'CUS-'.strtoupper(fake()->unique()->bothify('??###')),
            'name' => 'Customer Notification Test',
            'phone' => '081234567890',
            'status' => 'active',
        ]);
    }

    private function message(User $recipient, Branch $branch, string $ruleCode): NotificationMessage
    {
        return NotificationMessage::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'user_id' => $recipient->id,
            'rule_code' => $ruleCode,
            'category' => 'system',
            'severity' => 'info',
            'title' => 'Notification action test',
            'body' => 'Notification lifecycle action integration test.',
            'action_url' => '/notifications',
            'source_type' => Booking::class,
            'source_id' => 999,
            'dedupe_key' => $ruleCode.':999',
            'occurrences' => 1,
            'first_triggered_at' => now(),
            'last_triggered_at' => now(),
            'email_status' => 'not_requested',
        ]);
    }
}
