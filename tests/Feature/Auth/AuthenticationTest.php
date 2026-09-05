<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_login_page(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('auth/Login'));
    }

    public function test_a_student_can_authenticate_and_is_redirected_to_practice(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('practice'));
    }

    public function test_a_teacher_can_authenticate_and_is_redirected_to_the_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_cannot_authenticate_with_an_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertInvalid(['email']);
    }

    public function test_users_cannot_authenticate_with_an_unknown_email(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertInvalid(['email']);
    }

    public function test_login_regenerates_the_session(): void
    {
        $user = User::factory()->create();

        $this->get('/login');
        $originalSessionId = session()->getId();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertNotSame($originalSessionId, session()->getId());
    }

    public function test_authenticated_users_are_redirected_away_from_the_login_page(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($student)->get('/login')->assertRedirect(route('practice'));
        $this->actingAs($teacher)->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_users_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_guests_cannot_log_out(): void
    {
        $response = $this->post('/logout');

        $response->assertRedirect('/login');
    }

    public function test_guests_are_redirected_to_login_from_protected_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/practice')->assertRedirect('/login');
    }

    public function test_a_student_cannot_view_the_teacher_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get('/dashboard')->assertForbidden();
    }

    public function test_a_teacher_cannot_view_the_student_practice_page(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get('/practice')->assertForbidden();
    }
}
