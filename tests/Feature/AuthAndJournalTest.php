<?php

namespace Tests\Feature;

use App\Models\Party;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_fetch_me(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'admin@shatbha.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'admin@shatbha.test')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure(['token', 'user']);

        $token = $response->json('token');
        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.company.name', 'شطبة');
    }

    public function test_clerk_cannot_open_income_statement(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = $this->postJson('/api/v1/login', [
            'email' => 'clerk@shatbha.test',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/reports/income-statement')
            ->assertForbidden()
            ->assertJsonPath('message', 'هذا التقرير متاح للمدير فقط');
    }

    public function test_seeded_pnl_nets_nine_hundred_and_job_remaining_is_seven_thousand(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = $this->postJson('/api/v1/login', [
            'email' => 'admin@shatbha.test',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/reports/income-statement')
            ->assertOk()
            ->assertJsonPath('data.net', '900.00')
            ->assertJsonPath('data.supervision_fees', '1000.00')
            ->assertJsonPath('data.office_expenses', '100.00');

        $this->withToken($token)
            ->getJson('/api/v1/jobs')
            ->assertOk()
            ->assertJsonPath('data.0.remaining', '7000.00');
    }

    public function test_can_post_a_customer_journal_entry(): void
    {
        $this->seed(DatabaseSeeder::class);
        $customer = Party::query()->where('name', 'خالد')->firstOrFail();

        $token = $this->postJson('/api/v1/login', [
            'email' => 'admin@shatbha.test',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)
            ->postJson('/api/v1/customer-entries', [
                'customer_id' => $customer->id,
                'entry_date' => '2026-06-01',
                'entry_type' => 'cash',
                'title' => 'دفعة إضافية',
                'amount' => '2500.50',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'دفعة إضافية')
            ->assertJsonPath('data.amount', '2500.50');
    }
}
