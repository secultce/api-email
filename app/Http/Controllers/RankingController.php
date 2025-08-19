<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GrokPHP\Laravel\Facades\GrokAI;
use GrokPHP\Client\Config\ChatOptions;
use GrokPHP\Client\Enums\Model;
class RankingController extends Controller
{
    public function processar(Request $request)
    {

        $candidatos = $request->input('candidatos'); // Ex.: array de candidatos
        $criterios = $request->input('criterios'); // Ex.: "ordene por idade decrescente"
        $mensagem = [
            [
                'role' => 'system',
                'content' => 'Você é um assistente que organiza listas de candidatos e
                            retorna apenas o resultado final em formato JSON puro, sem marcações de Markdown,
                            sem ```json, sem aspas triplas, e sem explicações.'
            ],
            [
                'role' => 'user',
                'content' => "Lista de candidatos: " . json_encode($candidatos) . ".
                            Critérios: " . $criterios . ".
                            Retorne apenas o JSON com a lista ordenada ou filtrada conforme os critérios."
            ]
        ];

        try {
            $response = GrokAI::chat($mensagem, new ChatOptions(model: Model::GROK_2));
            $content = $response->content();

            // Limpar a resposta para remover marcações de Markdown
            $content = preg_replace('/^```json\n|\n```$|^"""\n|\n"""$/s', '', $content);
            $content = trim($content);

            // Tenta parsear o conteúdo como JSON
            $resultado = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['erro' => 'Resposta inválida do Grok: ' . json_last_error_msg(), 'content' => $content], 500);
            }

            return response()->json(['resultado' => $resultado]);
        } catch (\GrokPHP\Client\Exceptions\GrokException $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    }
}
