<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('otps');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('telephone')->unique();
            $table->string('country');
            $table->string('city');
            $table->string('password');
            $table->string('role')->default('client');
            $table->string('status')->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('code', 6);
            $table->boolean('utilise')->default(false);
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->index('email');
        });
    }

    public function test_smtp_mailer_uses_tls_encryption(): void
    {
        $this->assertSame('tls', config('mail.mailers.smtp.encryption'));
    }

    public function test_failed_otp_verification_does_not_create_user(): void
    {
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

        $response->assertStatus(201);

        $pendingToken = $response->json('pending_token');
        $this->assertNotEmpty($pendingToken);

        $failedVerifyResponse = $this->postJson('/api/verify-otp', [
            'email' => 'zakzam677@gmail.com',
            'otp' => '000000',
            'pending_token' => $pendingToken,
        ]);

        $failedVerifyResponse->assertStatus(422)
            ->assertJsonFragment([
                'success' => false,
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'zakzam677@gmail.com',
        ]);
    }

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
