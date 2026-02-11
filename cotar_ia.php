<?php
// cotar_ia.php - VERSÃO FINAL OTIMIZADA (IA + SHOPPING + FIX LINKS)
header('Content-Type: application/json');

// Desativa exibição de erros para não corromper o JSON
error_reporting(0);
ini_set('display_errors', 0);

$termo = $_GET['termo'] ?? '';
$foto  = $_GET['foto']  ?? '';

if (empty($termo)) {
    echo json_encode(['erro' => 'Termo vazio']);
    exit;
}

// --- CHAVES ---
$serpApiKey = "982d178b5359ca369069a20a3cbd8f69b4229abd96c47c3821e4637088854e6d";
$geminiApiKey = "AIzaSyCvG_GOm2x3cIFqMIdAW0qVS9vQ4kh8YaY";

// --- 1. INTELIGÊNCIA PRÉ-BUSCA (GEMINI) ---
// Otimiza o termo para evitar que o Google trave em códigos internos/datas
$urlGemini = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $geminiApiKey;

$prompt = "Atue como comprador técnico hospitalar. Otimize este termo para busca comercial no Google Shopping Brasil: '$termo'. Remova datas, observações entre parênteses e códigos de série inúteis. Retorne APENAS o nome comercial simplificado.";

$dataGemini = ["contents" => [["parts" => [["text" => $prompt]]]]];

// Se houver foto, ela ajuda a IA a definir o termo melhor
if (!empty($foto) && file_exists($foto)) {
    $imgData = @base64_encode(file_get_contents($foto));
    if ($imgData) {
        $dataGemini["contents"][0]["parts"][] = ["inline_data" => ["mime_type" => "image/jpeg", "data" => $imgData]];
    }
}

$ch = curl_init($urlGemini);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataGemini));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$resGemini = curl_exec($ch);
curl_close($ch);

$resIA = json_decode($resGemini, true);
$termoOtimizado = $resIA['candidates'][0]['content']['parts'][0]['text'] ?? $termo;

// --- 2. BUSCA NO GOOGLE SHOPPING (SERPAPI) ---
$urlSerp = "https://serpapi.com/search.json?q=" . urlencode(trim($termoOtimizado)) . "&engine=google_shopping&hl=pt&gl=br&api_key=" . $serpApiKey;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlSerp);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$resSerp = curl_exec($ch);
curl_close($ch);

$data = json_decode($resSerp, true);
$final = [];

if (isset($data['shopping_results'])) {
    foreach (array_slice($data['shopping_results'], 0, 10) as $p) {
        // CORREÇÃO DOS LINKS: Verifica múltiplos campos de retorno da API
        $link_real = $p['link'] ?? ($p['product_link'] ?? ($p['shopping_results_link'] ?? '#'));
        
        // Se o link for relativo do Google, transforma em absoluto
        if (strpos($link_real, '/') === 0) {
            $link_real = "https://www.google.com" . $link_real;
        }

        $final[] = [
            'loja'   => $p['source'] ?? 'Loja',
            'preco'  => $p['price'] ?? '---',
            'titulo' => $p['title'] ?? '',
            'link'   => $link_real,
            'foto'   => $p['thumbnail'] ?? ''
        ];
    }
}

// Retorno limpo para o JavaScript
echo json_encode($final);
