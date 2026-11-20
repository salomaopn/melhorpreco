<?php


use HeadlessChromium\Browser;
use voku\helper\HtmlDomParser;



class MercadoLivreScraper
{
    private int $limite;

    public function __construct(int $limite = 5)
    {
        $this->limite = $limite;
    }

    public function buscar(Browser $browser, string $produto): array
    {
        $resultados = [];
        $termoSlug = urlencode(str_replace(' ', '-', mb_strtolower(trim($produto))));
        $url = "https://lista.mercadolivre.com.br/{$termoSlug}";

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 12,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_HTTPHEADER     => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                    'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                    'Referer: https://www.mercadolivre.com.br/'
                ]
            
            ]);


            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || empty($html)) {
                return [];
            }

            // Extrai dados estruturados JSON-LD embutidos pelo Mercado Livre
            if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $scripts)) {
                foreach ($scripts[1] as $jsonRaw) {
                    $data = json_decode(trim($jsonRaw), true);
                    if (!is_array($data)) {
                        continue;
                    }

                    // Formato ItemList
                    $lista = $data['itemListElement'] ?? ($data['@graph'] ?? []);
                    if (is_array($lista)) {
                        foreach ($lista as $entry) {
                            if (count($resultados) >= $this->limite) {
                                break 2;
                            }
                            $prod = $entry['item'] ?? $entry;
                            $titulo = $prod['name'] ?? '';
                            $preco  = isset($prod['offers']['price']) ? (float) $prod['offers']['price'] : 0;
                            $link   = $prod['offers']['url'] ?? ($prod['url'] ?? '#');


                            if ($preco > 0 && !empty($titulo)) {
                                $resultados[] = [
                                    'fornecedor' => 'Mercado Livre',
                                    'titulo'     => trim($titulo),
                                    'preco'      => $preco,
                                    'link'       => $link
                                ];
                            }
                        }
                    }
                }
            }

            //  Caso o JSON-LD não esteja presente
            if (empty($resultados)) {
                $dom = HtmlDomParser::str_get_html($html);
                $itens = $dom->find('li.ui-search-layout__item');
                foreach ($itens as $item) {
                    if (count($resultados) >= $this->limite) {
                        break;
                    }
                    $tituloEl   = $item->find('.poly-component__title', 0) ?: $item->find('h2', 0);
                    $linkEl     = $item->find('a', 0);
                    $inteiroEl  = $item->find('.andes-money-amount__fraction', 0);
                    $centavosEl = $item->find('.andes-money-amount__cents', 0);

                    if ($tituloEl && $inteiroEl) {
                        $inteiro  = str_replace('.', '', trim($inteiroEl->plaintext));
                        $centavos = $centavosEl ? trim($centavosEl->plaintext) : '00';
                        $preco    = (float) "{$inteiro}.{$centavos}";

                        if ($preco > 0) {
                            $resultados[] = [
                                'fornecedor' => 'Mercado Livre',
                                'titulo'     => trim($tituloEl->plaintext),
                                'preco'      => $preco,
                                'link'       => $linkEl ? $linkEl->href : '#'
                            ];
                        }
                    }
                }
                $dom->clear();
            }
        } catch (Throwable $e) {
            // Silencia falha isolada
        }

        return $resultados;
    }
}