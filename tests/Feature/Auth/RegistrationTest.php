<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_registration_page(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('auth/Register'));
    }

    public function test_a_student_can_register_and_is_redirected_to_the_student_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'student',
        ]);

        $this->assertTrue(Auth::check());
        $this->assertDatabaseHas('users', [
            'email' => 'ada@example.com',
            'role' => 'student',
        ]);
        $response->assertRedirect(route('student.dashboard'));
    }

    public function test_a_teacher_can_register_and_is_redirected_to_the_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'teacher',
        ]);

        $this->assertTrue(Auth::check());
        $this->assertDatabaseHas('users', [
            'email' => 'grace@example.com',
            'role' => 'teacher',
        ]);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_registered_passwords_are_hashed(): void
    {
        $this->post('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'student',
        ]);

        $user = User::where('email', 'ada@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_registration_requires_a_unique_email(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'student',
        ]);

        $response->assertRedirect('/register');
        $response->assertInvalid(['email']);
        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'ada@example.com')->count());
    }

    public function test_registration_requires_a_matching_password_confirmation(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'not-password',
            'role' => 'student',
        ]);

        $response->assertInvalid(['password']);
        $this->assertGuest();
    }

    public function test_registration_requires_a_recognised_role(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ]);

        $response->assertInvalid(['role']);
        $this->assertGuest();
    }

    public function test_authenticated_users_cannot_view_or_submit_the_registration_page(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)->get('/register')->assertRedirect(route('student.dashboard'));
        $this->actingAs($user)->post('/register', [])->assertRedirect(route('student.dashboard'));
    }
}
