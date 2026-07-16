<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_succeeds_when_otp_email_fails(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with('zakzam677@gmail.com')
            ->andThrow(new RuntimeException('SMTP unavailable'));

        $response = $this->postJson('/api/register', [
            'first_name' => 'Zakaria',
            'last_name' => 'JOHN',
            'email' => 'zakzam677@gmail.com',
            'telephone' => '+22899335584',
            'country' => 'Togo',
            'city' => 'Kpalimé',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('email', 'zakzam677@gmail.com');

        $otpRecord = \App\Models\Otp::where('email', 'zakzam677@gmail.com')->latest()->first();
        $this->assertNotNull($otpRecord);
        $this->assertNotEmpty($otpRecord->code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $otpRecord->code);

        $this->assertDatabaseMissing('users', [
            'email' => 'zakzam677@gmail.com',
        ]);

        $this->assertDatabaseHas('otps', [
            'email' => 'zakzam677@gmail.com',
        ]);
    }
}
