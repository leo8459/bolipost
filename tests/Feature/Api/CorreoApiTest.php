<?php

namespace Tests\Feature\Api;

use App\Mail\ApiCorreoMail;
use App\Models\ExternalApiToken;
use App\Support\ExternalApiJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CorreoApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('external_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('jti')->unique();
            $table->string('token_hash');
            $table->text('token_encrypted')->nullable();
            $table->text('token_plain')->nullable();
            $table->json('abilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_envia_un_correo_con_una_credencial_autorizada(): void
    {
        Mail::fake();
        $jwt = $this->issueToken(['correos:send']);

        $this->withHeader('X-API-Token', $jwt)
            ->postJson('/api/integraciones/correos/enviar', [
                'para' => ['destinatario@example.com'],
                'cc' => ['copia@example.com'],
                'asunto' => 'Prueba API',
                'mensaje' => 'Mensaje de prueba',
                'formato' => 'texto',
                'responder_a' => 'respuesta@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Correo enviado correctamente.')
            ->assertJsonPath('data.para.0', 'destinatario@example.com');

        Mail::assertSent(ApiCorreoMail::class, function (ApiCorreoMail $mail): bool {
            return $mail->asunto === 'Prueba API'
                && $mail->mensaje === 'Mensaje de prueba'
                && $mail->hasTo('destinatario@example.com')
                && $mail->hasCc('copia@example.com');
        });
    }

    public function test_rechaza_credenciales_sin_permiso_de_envio(): void
    {
        Mail::fake();
        $jwt = $this->issueToken(['paquetes-contactos:ems:read']);

        $this->withHeader('X-API-Token', $jwt)
            ->postJson('/api/integraciones/correos/enviar', [
                'para' => ['destinatario@example.com'],
                'asunto' => 'Prueba API',
                'mensaje' => 'Mensaje de prueba',
            ])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'correos:send');

        Mail::assertNothingSent();
    }

    public function test_valida_los_datos_del_correo(): void
    {
        Mail::fake();
        $jwt = $this->issueToken(['correos:send']);

        $this->withHeader('X-API-Token', $jwt)
            ->postJson('/api/integraciones/correos/enviar', [
                'para' => ['correo-invalido'],
                'asunto' => '',
                'mensaje' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['para.0', 'asunto', 'mensaje']);

        Mail::assertNothingSent();
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function issueToken(array $abilities): string
    {
        $token = ExternalApiToken::query()->create([
            'name' => 'ENVIO DE CORREOS',
            'jti' => hash('sha256', uniqid('correo-api-', true)),
            'token_hash' => hash('sha256', 'pendiente'),
            'abilities' => $abilities,
            'is_active' => true,
        ]);

        $jwt = ExternalApiJwt::issue($token);
        $token->forceFill(['token_hash' => hash('sha256', $jwt)])->save();

        return $jwt;
    }
}
