<?php

namespace Tests\Feature\PC;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Jobs\NotificationAccountability;

class EmailTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_return_api_and_passing_params_to_metod(): void
    {
        $response = Http::get( 'http://172.19.18.161:8088/bigsheet/infoForNotificationsAccountability/', [
            'access_token' => config('jwt.secret')
        ]);
        // Confirma que o status da resposta foi 200 (opcional, dependendo do teste)
        $this->assertEquals(200, $response->status());
         // Converte o corpo da resposta para um array JSON
        $jsonResponse = $response->json();
        NotificationAccountability::dispatch($jsonResponse);

        $this->assertIsArray($jsonResponse); // Confirma que a resposta é um array
        // Se tiver array preenchido no retorno
        if(count($jsonResponse) > 0){
            // Verifica se a resposta contém uma chave específica
            $this->assertArrayHasKey('registration_number', $jsonResponse[0]);
        }
    }
}
