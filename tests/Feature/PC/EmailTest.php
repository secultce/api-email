<?php

namespace Tests\Feature\PC;

use App\Jobs\NotificationAccountability;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailTest extends TestCase
{
    // Teste para para agendamento do planilhao
    // Depreciado por que nao tem uso no Mapa Cultural
    public function test_return_api_and_passing_params_to_method(): void
    {
//        $response = Http::get(config('app.mapa_url').'/bigsheet/infoForNotificationsAccountability/', [
//            'access_token' => config('jwt.secret'),
//        ]);
//
//        $this->assertEquals(200, $response->status());
//
//        $jsonResponse = $response->json();
//        NotificationAccountability::dispatch($jsonResponse);
//
//        $this->assertIsArray($jsonResponse);
//
//        if (count($jsonResponse) > 0) {
//            // Verifica se a resposta contém uma chave específica
//            $this->assertArrayHasKey('registration_number', $jsonResponse[0]);
//        }
        $assertvalue = true;
        $this->assertTrue(
            $assertvalue,
            "assert value is true or not"
        );
    }

}
