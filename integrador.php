<?php

declare(strict_types=1);

/**
 * Integrador de Automação de Telecom
 *
 * Lê leads de um CSV, higieniza e valida telefones brasileiros, e envia
 * os contatos válidos via cURL (POST/JSON) para uma API de mailing.
 *
 * Uso: php integrador.php
 *
 * Estrutura esperada do leads.csv (mesma pasta deste script):
 *   nome,telefone
 *   João da Silva,+55 (45) 99999-8888
 */

// ---------------------------------------------------------------------
// Etapa 1: captura — leitura do leads.csv
// ---------------------------------------------------------------------

$caminhoArquivoCsv = __DIR__ . '/leads.csv';

if (!file_exists($caminhoArquivoCsv)) {
    echo "ERRO: arquivo 'leads.csv' não encontrado em {$caminhoArquivoCsv}.\n";
    echo "Crie o arquivo (veja o formato no topo deste script) e tente novamente.\n";
    exit(1);
}

$handle = fopen($caminhoArquivoCsv, 'r');
$leadsBrutos = [];
$primeiraLinha = true; // primeira linha do CSV é o cabeçalho, não um lead

while (($linhaCsv = fgetcsv($handle)) !== false) {
    if ($primeiraLinha) {
        $primeiraLinha = false;
        continue;
    }
    $leadsBrutos[] = ['nome' => $linhaCsv[0], 'telefone' => $linhaCsv[1]];
}

fclose($handle);


// ---------------------------------------------------------------------
// Etapa 2: higienização e validação
// ---------------------------------------------------------------------

// Limpa o telefone: remove o "+55" e qualquer caractere que não seja número.
function higienizarTelefone(string $telefoneBruto): string
{
    $semDDI = preg_replace('/^\+?55/', '', trim($telefoneBruto)); // remove "+55"/"55" do início
    return preg_replace('/\D/', '', (string) $semDDI); // \D = qualquer não-dígito
}

// Valida se o telefone já limpo tem 10 (fixo) ou 11 (celular) dígitos.
function telefoneEhValido(string $telefoneLimpo): bool
{
    $quantidadeDigitos = strlen($telefoneLimpo);
    return $quantidadeDigitos === 10 || $quantidadeDigitos === 11;
}

$leadsValidos = [];
$leadsDescartados = [];

foreach ($leadsBrutos as $lead) {
    $telefoneLimpo = higienizarTelefone($lead['telefone']);

    if (telefoneEhValido($telefoneLimpo)) {
        $leadsValidos[] = ['nome' => $lead['nome'], 'telefone' => $telefoneLimpo];
    } else {
        $leadsDescartados[] = [
            'nome'            => $lead['nome'],
            'telefone_origem' => $lead['telefone'],
            'motivo'          => 'Quantidade de dígitos inválida após limpeza (' . strlen($telefoneLimpo) . ' dígitos)',
        ];
    }
}

echo "===================================================\n";
echo " RELATÓRIO DE HIGIENIZAÇÃO DE LEADS\n";
echo "===================================================\n";
echo "Total recebido do CRM: " . count($leadsBrutos) . "\n";
echo "Total válido para envio: " . count($leadsValidos) . "\n";
echo "Total descartado: " . count($leadsDescartados) . "\n\n";

if (count($leadsDescartados) > 0) {
    echo "--- Leads descartados (motivo) ---\n";
    foreach ($leadsDescartados as $descartado) {
        echo "- {$descartado['nome']} ({$descartado['telefone_origem']}): {$descartado['motivo']}\n";
    }
    echo "\n";
}


// ---------------------------------------------------------------------
// Etapa 3: envio via cURL
// ---------------------------------------------------------------------

// Envia um lead validado via cURL (POST/JSON) para a URL configurada abaixo.
function enviarLeadParaApi3CPlus(array $lead): array
{
    // Troque pela URL gerada em https://webhook.site para homologação.
    // Em produção, aqui vai o endpoint real da API (ex: .../v1/mailing).
    $url = 'https://webhook.site/31240d46-3de4-43a8-ba2b-96f8e0f8568d';

    // Nunca deixe tokens reais hardcoded — use variável de ambiente (getenv) ou .env.
    $tokenFicticio = 'TOKEN_FICTICIO_PARA_TESTE_1234567890';

    $payload = ['name' => $lead['nome'], 'phone' => $lead['telefone']];
    $payloadJson = json_encode($payload);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $tokenFicticio,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $resposta = curl_exec($ch);
    $erroCurl = curl_errno($ch) ? curl_error($ch) : null;
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $respostaDecodificada = json_decode((string) $resposta, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $respostaDecodificada = null;
    }

    return [
        'lead'                  => $lead,
        'http_code'             => $codigoHttp,
        'erro_curl'             => $erroCurl,
        'resposta_api'          => $resposta,
        'resposta_decodificada' => $respostaDecodificada,
    ];
}

if (count($leadsValidos) > 0) {
    echo "===================================================\n";
    echo " ENVIANDO LEADS VÁLIDOS (HOMOLOGAÇÃO VIA WEBHOOK.SITE)\n";
    echo "===================================================\n";

    // Códigos ANSI para colorir a saída do terminal.
    $corVerde = "\033[32m";
    $corVermelha = "\033[31m";
    $corReset = "\033[0m";

    foreach ($leadsValidos as $leadValido) {
        $resultadoEnvio = enviarLeadParaApi3CPlus($leadValido);

        if ($resultadoEnvio['erro_curl'] !== null) {
            echo $corVermelha . "[FALHA DE CONEXÃO] {$leadValido['nome']} ({$leadValido['telefone']}) "
               . "-> Erro: {$resultadoEnvio['erro_curl']}" . $corReset . "\n";
        } elseif ($resultadoEnvio['http_code'] === 200) {
            echo $corVerde . "[ENVIADO] {$leadValido['nome']} enviado com sucesso -> HTTP 200" . $corReset . "\n";

            if (is_array($resultadoEnvio['resposta_decodificada']) && isset($resultadoEnvio['resposta_decodificada']['uuid'])) {
                echo "          -> UUID da requisição no Webhook.site: {$resultadoEnvio['resposta_decodificada']['uuid']}\n";
            }
        } else {
            echo $corVermelha . "[FALHA NO ENVIO] {$leadValido['nome']} ({$leadValido['telefone']}) "
               . "-> HTTP {$resultadoEnvio['http_code']}" . $corReset . "\n";
        }
    }
} else {
    echo "Nenhum lead válido para enviar à API.\n";
}

echo "\nProcesso finalizado.\n";