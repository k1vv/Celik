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

    public function test_a_student_can_authenticate_and_is_redirected_to_the_student_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'role' => 'student',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('student.dashboard'));
        $this->get('/student/dashboard')->assertOk();
    }

    public function test_a_teacher_can_authenticate_and_is_redirected_to_the_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'role' => 'teacher',
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

    public function test_role_selection_rejects_credentials_for_another_account_type(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $response = $this->from('/login')->post('/login', [
            'email' => $teacher->email,
            'password' => 'password',
            'role' => 'student',
        ]);

        $this->assertGuest();
        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'These credentials do not belong to a student account.',
            ]);
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

        $this->actingAs($student)->get('/login')->assertRedirect(route('student.dashboard'));
        $this->actingAs($teacher)->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_users_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['temporary_message' => 'remove me'])
            ->post('/logout');

        $this->assertGuest();
        $response
            ->assertRedirect('/login')
            ->assertSessionMissing('temporary_message');
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
        $this->get('/student/dashboard')->assertRedirect('/login');
        $this->get('/student/homework/submit')->assertRedirect('/login');
    }

    public function test_a_student_cannot_view_the_teacher_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get('/dashboard')->assertForbidden();
    }

    public function test_a_teacher_can_view_the_teacher_dashboard(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    public function test_a_teacher_cannot_view_the_student_practice_page(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get('/practice')->assertForbidden();
    }

    public function test_student_pages_render_their_distinct_inertia_pages(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get('/student/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('StudentDashboard'));

        $this->actingAs($student)->get('/practice')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Practice'));

        $this->actingAs($student)->get('/student/homework/submit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('SubmitHomework'));
    }

    public function test_a_teacher_cannot_view_student_dashboard_or_homework_submission_pages(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get('/student/dashboard')->assertForbidden();
        $this->actingAs($teacher)->get('/student/homework/submit')->assertForbidden();
    }
}
