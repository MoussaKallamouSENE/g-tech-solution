<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaintenanceStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_statuses_follow_the_allowed_progression(): void
    {
        [$admin, $request] = $this->prepareScenario();
        $this->actingAs($admin, 'sanctum')->withHeader('Accept', 'application/json');

        $this->patchJson('/api/admin/maintenance-requests/'.$request->id, [
            'status' => 'in_progress',
            'admin_notes' => 'Diagnostic en cours',
        ])->assertOk()
            ->assertJsonPath('status', 'in_progress');

        $this->patchJson('/api/admin/maintenance-requests/'.$request->id, [
            'status' => 'ready',
            'admin_notes' => 'Appareil prêt à récupérer',
        ])->assertOk()
            ->assertJsonPath('status', 'ready');

        $this->patchJson('/api/admin/maintenance-requests/'.$request->id, [
            'status' => 'done',
            'admin_notes' => 'Appareil remis au client',
        ])->assertOk()
            ->assertJsonPath('status', 'done');
    }

    public function test_invalid_transition_does_not_change_status_or_notes(): void
    {
        [$admin, $request] = $this->prepareScenario();

        $this->actingAs($admin, 'sanctum')->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/maintenance-requests/'.$request->id, [
                'status' => 'ready',
                'admin_notes' => 'Ne doit pas être sauvegardé',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Transition impossible : une demande « new » ne peut pas passer au statut « ready ».');

        $this->assertSame('new', $request->fresh()->status);
        $this->assertNull($request->fresh()->admin_notes);
    }

    public function test_terminal_status_cannot_be_changed_but_notes_remain_editable(): void
    {
        [$admin, $request] = $this->prepareScenario('done');
        $this->actingAs($admin, 'sanctum')->withHeader('Accept', 'application/json');

        $this->patchJson('/api/admin/maintenance-requests/'.$request->id, [
            'status' => 'in_progress',
            'admin_notes' => 'Tentative de réouverture',
        ])->assertUnprocessable();

        $this->assertSame('done', $request->fresh()->status);
        $this->assertNull($request->fresh()->admin_notes);

        $this->patchJson('/api/admin/maintenance-requests/'.$request->id, [
            'status' => 'done',
            'admin_notes' => 'Note ajoutée après la remise',
        ])->assertOk()
            ->assertJsonPath('admin_notes', 'Note ajoutée après la remise');
    }

    public function test_only_administrators_can_change_maintenance_status(): void
    {
        [, $request] = $this->prepareScenario();
        $customer = User::create([
            'name' => 'Client',
            'email' => 'maintenance-client@example.test',
            'password' => Hash::make('secret123'),
            'role' => 'customer',
        ]);

        $this->actingAs($customer, 'sanctum')->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/maintenance-requests/'.$request->id, [
                'status' => 'in_progress',
                'admin_notes' => null,
            ])
            ->assertForbidden();

        $this->assertSame('new', $request->fresh()->status);
    }

    public function test_admin_can_filter_maintenance_requests_and_invalid_filters_are_rejected(): void
    {
        [$admin, $request] = $this->prepareScenario();
        MaintenanceRequest::create([
            'number' => 'RM-'.uniqid(),
            'name' => 'Moussa Fall',
            'phone' => '+221 76 444 55 66',
            'device_type' => 'imprimante',
            'problem' => 'Impression impossible.',
            'status' => 'in_progress',
        ]);

        $this->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/maintenance-requests')
            ->assertUnauthorized();

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/maintenance-requests')
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/maintenance-requests?status=new&search=Client&page=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $request->id);

        $this->getJson('/api/admin/maintenance-requests?status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->getJson('/api/admin/maintenance-requests?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page']);

        $this->getJson('/api/admin/maintenance-requests?search='.str_repeat('a', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }

    private function prepareScenario(string $status = 'new'): array
    {
        $admin = User::create([
            'name' => 'Admin réparations',
            'email' => 'maintenance-admin-'.uniqid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $request = MaintenanceRequest::create([
            'number' => 'RM-'.uniqid(),
            'name' => 'Client test',
            'phone' => '+221 77 123 45 67',
            'device_type' => 'portable',
            'problem' => 'L’ordinateur ne démarre plus.',
            'status' => $status,
        ]);

        return [$admin, $request];
    }
}
