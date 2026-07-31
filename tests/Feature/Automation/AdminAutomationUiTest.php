<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Models\AutomationRule;
use Core\Automation\Models\Workflow;
use Core\Automation\Support\AutomationEvent;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAutomationUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-automation',
            'corepanel.themes.auto_load_active' => false,
        ]);

        $this->withoutVite();
    }

    public function test_admin_can_manage_workflows(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.automation.workflows.index'))
            ->assertOk()
            ->assertSee(__('Workflows'));

        $this->actingAs($admin)
            ->get(route('admin.automation.workflows.create'))
            ->assertOk()
            ->assertSee(__('Create workflow'));

        $this->actingAs($admin)
            ->post(route('admin.automation.workflows.store'), [
                'name' => 'Invoice paid flow',
                'slug' => 'invoice-paid-flow',
                'trigger_event' => AutomationEvent::INVOICE_PAID,
                'conditions_json' => json_encode(['all' => [
                    ['field' => 'amount', 'operator' => 'gte', 'value' => 1],
                ]], JSON_THROW_ON_ERROR),
                'steps_json' => json_encode([
                    ['type' => 'log', 'message' => 'paid'],
                    ['type' => 'noop'],
                ], JSON_THROW_ON_ERROR),
                'fallback_json' => json_encode([
                    'type' => 'manual_intervention',
                    'message' => 'Check invoice',
                ], JSON_THROW_ON_ERROR),
                'priority' => 10,
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $workflow = Workflow::query()->where('slug', 'invoice-paid-flow')->firstOrFail();
        $this->assertSame(AutomationEvent::INVOICE_PAID, $workflow->trigger_event);
        $this->assertCount(2, $workflow->steps);
        $this->assertSame('manual_intervention', $workflow->fallback['type'] ?? null);

        $this->actingAs($admin)
            ->get(route('admin.automation.workflows.show', $workflow))
            ->assertOk()
            ->assertSee('Invoice paid flow')
            ->assertSee('invoice.paid');

        $this->actingAs($admin)
            ->put(route('admin.automation.workflows.update', $workflow), [
                'name' => 'Invoice paid flow updated',
                'slug' => 'invoice-paid-flow',
                'trigger_event' => AutomationEvent::INVOICE_PAID,
                'conditions_json' => '',
                'steps_json' => json_encode([['type' => 'noop']], JSON_THROW_ON_ERROR),
                'priority' => 5,
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.automation.workflows.show', $workflow));

        $workflow->refresh();
        $this->assertSame('Invoice paid flow updated', $workflow->name);
        $this->assertFalse($workflow->is_active);
        $this->assertSame(5, $workflow->priority);

        $this->actingAs($admin)
            ->delete(route('admin.automation.workflows.destroy', $workflow))
            ->assertRedirect(route('admin.automation.workflows.index'));

        $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
    }

    public function test_admin_can_manage_rules(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.automation.rules.store'), [
                'name' => 'Suspend overdue services',
                'condition_json' => json_encode([
                    'event' => AutomationEvent::INVOICE_OVERDUE,
                    'all' => [
                        ['field' => 'days_overdue', 'operator' => 'gt', 'value' => 3],
                    ],
                ], JSON_THROW_ON_ERROR),
                'action_json' => json_encode([
                    'type' => 'suspend_service',
                    'fallback' => ['type' => 'log', 'message' => 'fallback'],
                ], JSON_THROW_ON_ERROR),
                'priority' => 20,
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $rule = AutomationRule::query()->where('name', 'Suspend overdue services')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.automation.rules.show', $rule))
            ->assertOk()
            ->assertSee('Suspend overdue services')
            ->assertSee('suspend_service');

        $this->actingAs($admin)
            ->put(route('admin.automation.rules.update', $rule), [
                'name' => 'Suspend overdue services v2',
                'condition_json' => json_encode(['all' => []], JSON_THROW_ON_ERROR),
                'action_json' => json_encode(['type' => 'noop'], JSON_THROW_ON_ERROR),
                'priority' => 30,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.automation.rules.show', $rule));

        $this->assertSame('Suspend overdue services v2', $rule->fresh()->name);
        $this->assertSame('noop', $rule->fresh()->action_json['type'] ?? null);
    }

    public function test_admin_can_view_automation_logs(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $workflow = Workflow::factory()->create([
            'name' => 'Logged workflow',
            'trigger_event' => AutomationEvent::TICKET_CREATED,
        ]);
        $log = AutomationLog::factory()->forWorkflow($workflow)->succeeded()->create([
            'trigger_event' => AutomationEvent::TICKET_CREATED,
            'error_message' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.automation.logs.index'))
            ->assertOk()
            ->assertSee('Logged workflow')
            ->assertSee(AutomationEvent::TICKET_CREATED);

        $this->actingAs($admin)
            ->get(route('admin.automation.logs.show', $log))
            ->assertOk()
            ->assertSee(__('Succeeded'))
            ->assertSee((string) $log->id);
    }

    public function test_support_cannot_access_automation_ui(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.automation.workflows.index'))
            ->assertForbidden();

        $this->actingAs($support)
            ->get(route('admin.automation.rules.index'))
            ->assertForbidden();

        $this->actingAs($support)
            ->get(route('admin.automation.logs.index'))
            ->assertForbidden();
    }

    public function test_invalid_workflow_json_is_rejected(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.automation.workflows.store'), [
                'name' => 'Broken',
                'trigger_event' => AutomationEvent::NODE_ONLINE,
                'steps_json' => '{not-json',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('steps_json');
    }

    public function test_navigation_exposes_automation_section_for_admin(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('Automation'))
            ->assertSee(__('Workflows'))
            ->assertSee(__('Run logs'));
    }
}
