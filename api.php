<?php
header('Content-Type: application/json; charset=utf-8');
set_time_limit(90);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/CacheManager.php';
require_once __DIR__ . '/src/ChromeManager.php';
require_once __DIR__ . '/src/Scrapers/MercadoLivreScraper.php';
require_once __DIR__ . '/src/Scrapers/AtacadaoScraper.php';
require_once __DIR__ . '/src/Scrapers/ShopeeScraper.php';

$config = require __DIR__ . '/config.php';

$produto = isset($_GET['produto']) ? trim($_GET['produto']) : '';

if (empty($produto)) {
    echo json_encode(['erro' => 'Informe o nome de um produto para pesquisar.']);
    exit;
}

// 1. Verifica se a pesquisa já está salva no cache local (JSON)
$cache = new CacheManager($config['cache_dir'], $config['cache_ttl']);
$dadosEmCache = $cache->get($produto);

if ($dadosEmCache !== null) {
    echo $dadosEmCache;
    exit;
}


$resultados = [];
$limite = $config['limite_itens'];

// 2. Inicia o Google Chrome local via chrome-php e consulta os 3 fornecedores
$chromeManager = new ChromeManager($config);

try {
    $browser = $chromeManager->getBrowser();

    // Fornecedor 1: Mercado Livre
    $mlScraper = new MercadoLivreScraper($limite);
    $resultados = array_merge($resultados, $mlScraper->buscar($browser, $produto));

    // Fornecedor 2: Atacadão (Asa Norte - Brasília/DF)
    $atacadaoScraper = new AtacadaoScraper($limite);
    $resultados = array_merge($resultados, $atacadaoScraper->buscar($browser, $produto));

    // Fornecedor 3: Shopee Brasil
    $shopeeScraper = new ShopeeScraper($limite);
    $resultados = array_merge($resultados, $shopeeScraper->buscar($browser, $produto));

} catch (Exception $e) {
    // Em caso de erro ao iniciar o navegador, retorna o erro em JSON para diagnóstico
    if (empty($resultados)) {
        echo json_encode([
            'erro' => 'Falha ao executar o navegador local.',
            'detalhes' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} finally {
    // Garante que o processo do Chrome seja encerrado ao final da consulta
    $chromeManager->close();
}

  // 3.Ordena todos os produtos encontrados do menor preço para o maior
usort($resultados, fn($a, $b) => $a['preco'] <=> $b['preco']);


//  4. Salva o resultado no cache local (se houver resultados) e devolve ao frontend
$jsonFinal = json_encode($resultados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);



if (!empty($resultados)) {
    $cache->set($produto, $jsonFinal);
}


echo $jsonFinal;