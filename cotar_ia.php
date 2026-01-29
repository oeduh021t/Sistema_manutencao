<?php
// cotar_ia.php - VERSÃO FINAL INTELIGENTE
ob_start(); 
header('Content-Type: application/json');
error_reporting(0); 

$termo = $_GET['termo'] ?? '';
if (empty($termo)) { 
    ob_clean();
    echo json_encode(['erro' => 'Termo vazio']); 
    exit; 
}

// --- CHAVES ---
$serpApiKey = "982d178b5359ca369069a20a3cbd8f69b4229abd96c47c3821e4637088854e6d";
$geminiApiKey = "AIzaSyCvG_GOm2x3cIFqMIdAW0qVS9vQ4kh8YaY";

// --- 1. BUSCA NO GOOGLE SHOPPING ---
// Adicionamos "novo" e "preço" para focar em vendas de produtos inteiros
$urlSerp = "https://serpapi.com/search.json?q=" . urlencode($termo . " novo preço") . "&engine=google_shopping&hl=pt&gl=br&api_key=" . $serpApiKey;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlSerp);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$resSerp = curl_exec($ch);
curl_close($ch);

$jsonSerp = json_decode($resSerp, true);
$produtosBrutos = [];

if (isset($jsonSerp['shopping_results'])) {
    foreach (array_slice($jsonSerp['shopping_results'], 0, 10) as $p) {
        $produtosBrutos[] = [
            'loja' => $p['source'],
            'preco' => $p['price'],
            'titulo' => $p['title'],
            'link' => $p['link'] ?? $p['product_link']
        ];
    }
}

// Se o Google não encontrar nada, encerra
if (empty($produtosBrutos)) {
    ob_clean();
    echo json_encode([]); 
    exit;
}

// --- 2. CURADORIA COM GEMINI AI (O Cérebro) ---
$prompt = "Você é um comprador técnico especializado em ativos hospitalares e eletrônicos. 
Analise esta lista JSON para o item: '$termo'.

REGRAS DE OURO PARA FILTRAGEM:
1. O item DEVE ser o equipamento completo.
2. Descarte IMEDIATAMENTE itens com preços irrisórios (ex: canetas, cabos, suportes ou filtros). Para este nível de equipamento, qualquer valor abaixo de R$ 300,00 é provavelmente um acessório e deve ser ignorado.
3. Ignore resultados de papelaria (Kalunga, canetas BIC, etc) se o termo for um ar-condicionado ou eletrônico.
4. Responda APENAS com um array JSON puro (sem markdown, sem texto extra) com no máximo 5 itens.

Lista: " . json_encode($produtosBrutos);

$urlGemini = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $geminiApiKey;
$dataGemini = ["contents" => [["parts" => [["text" => $prompt]]]]];

$ch = curl_init($urlGemini);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataGemini));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$resGemini = curl_exec($ch);
curl_close($ch);

$jsonGemini = json_decode($resGemini, true);
$textoIA = $jsonGemini['candidates'][0]['content']['parts'][0]['text'] ?? '';

// --- 3. LIMPEZA DE SEGURANÇA ---
// Remove blocos de código markdown ```json e pega apenas o conteúdo entre colchetes [ ]
if (preg_match('/\[.*\]/s', $textoIA, $matches)) {
    $textoIA = $matches[0];
} else {
    $textoIA = trim(str_replace(['```json', '```'], '', $textoIA));
}

// --- 4. RETORNO FINAL ---
ob_clean(); // Garante que nenhum erro de PHP "suje" a saída
if (json_decode($textoIA) === null) {
    // Se a IA falhar na formatação, enviamos os dados brutos filtrados manualmente por preço
    $fallback = array_filter($produtosBrutos, function($p) {
        return (float)str_replace(['R$', '.', ','], ['', '', '.'], $p['preco']) > 100;
    });
    echo json_encode(array_slice(array_values($fallback), 0, 5));
} else {
    echo $textoIA;
}
