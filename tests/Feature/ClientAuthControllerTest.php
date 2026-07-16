<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClientAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_login_before_email_verification(): void
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Client',
            'email' => 'client-unverified@example.com',
            'telephone' => '+22890000001',
            'country' => 'Togo',
            'city' => 'Lomé',
            'password' => Hash::make('password'),
            'role' => 'client',
            'status' => 'active',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/client/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Veuillez vérifier votre email avec le code OTP avant de vous connecter.',
            ]);
    }

    public function test_registration_and_otp_verification_create_user_only_after_success(): void
    {
        Mail::fake();

        $registerResponse = $this->postJson('/api/register', [
            'first_name' => 'Zacharie',
            'last_name' => 'JOHN',
            'email' => 'zakzam677@gmail.com',
            'telephone' => '+22890000001',
            'country' => 'Togo',
            'city' => 'Lomé',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $registerResponse->assertStatus(201);

        $this->assertDatabaseMissing('users', [
            'email' => 'zakzam677@gmail.com',
        ]);

        $pendingToken = $registerResponse->json('pending_token');
        $this->assertNotEmpty($pendingToken);

        $otpRecord = \App\Models\Otp::where('email', 'zakzam677@gmail.com')->latest()->first();
        $this->assertNotNull($otpRecord);
        $otpCode = $otpRecord->code;

        $verifyResponse = $this->postJson('/api/verify-otp', [
            'email' => 'zakzam677@gmail.com',
            'otp' => $otpCode,
            'pending_token' => $pendingToken,
        ]);

        $verifyResponse->assertStatus(201)
            ->assertJsonFragment([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'zakzam677@gmail.com',
            'first_name' => 'Zacharie',
            'last_name' => 'JOHN',
        ]);
    }
}
