<?php

namespace Tests\Feature;

use App\Models\LandingLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_can_be_viewed(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Sirati')
            ->assertSee('حمّل التطبيق الآن')
            ->assertDontSee('الوصول المبكر')
            ->assertDontSee('احجز وصولك المبكر');
    }

    public function test_visitor_can_join_early_access_list(): void
    {
        $response = $this->post('/landing-leads', [
            'full_name' => 'Salem Sayer',
            'email' => 'salem@example.com',
            'phone' => '+966591890300',
            'role_interest' => 'both',
            'target_job_title' => 'Ecommerce Specialist',
            'notes' => 'I want to test ATS scoring.',
        ]);

        $response
            ->assertRedirect(route('landing'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('landing_leads', [
            'full_name' => 'Salem Sayer',
            'email' => 'salem@example.com',
            'role_interest' => 'both',
            'source' => 'landing_page',
        ]);

        $this->assertSame(1, LandingLead::count());
    }
}
