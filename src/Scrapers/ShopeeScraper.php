<?php

use HeadlessChromium\Browser;
use voku\helper\HtmlDomParser;

class ShopeeScraper
{
    private int $limite;

    public function __construct(int $limite = 5)
    {
        $this->limite = $limite;
    }

    public function buscar(Browser $browser, string $produto): array
    {
        $resultados = [];
        $termo = trim($produto);
        $termoUrl = urlencode($termo);

        // 1ª Tentativa: Sessão direta na Shopee via Chrome
        try {
            $page = $browser->createPage();
            $page->navigate("https://shopee.com.br/search?keyword={$termoUrl}")->waitForNavigation();
            sleep(4);
            $page->evaluate('window.scrollBy(0, 600);');
            sleep(2);

            $html = $page->getHtml();
            $page->close();



            if (!empty($html) && strpos($html, '-i.') !== false) {
                $dom = HtmlDomParser::str_get_html($html);
                $links = $dom->find('a[href*="-i."]');
                $urlsVistas = [];

                foreach ($links as $linkEl) {
                    if (count($resultados) >= $this->limite) {
                        break;
                    }
                    $href = $linkEl->href ?? '';
                    if (empty($href) || isset($urlsVistas[$href])) {
                        continue;
                    }

                    $textoCard = preg_replace('/\s+/u', ' ', html_entity_decode($linkEl->plaintext, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if (preg_match('/R\$\s*([\d\.]+,\d{2})/', $textoCard, $matches)) {
                        $preco = (float) str_replace(['.', ','], ['', '.'], $matches[1]);
                        $titulo = trim(preg_split('/R\$\s*[\d\.]+,\d{2}/', $textoCard)[0]);


                        if ($preco > 0 && mb_strlen($titulo) > 4) {
                            $urlsVistas[$href] = true;
                            $fullUrl = strpos($href, 'http') === 0 ? $href : 'https://shopee.com.br' . $href;

                            $resultados[] = [
                                'fornecedor' => 'Shopee',
                                'titulo'     => mb_substr($titulo, 0, 95),
                                'preco'      => $preco,
                                'link'       => $fullUrl
                            ];
                        }
                    }
                }
                $dom->clear();
            }
        } catch (Throwable $e) {
            
        }

        //2ª Tentativa (Fallback sem necessidade de login): Busca indexada site:shopee.com.br (agora vai!)
        
        if (empty($resultados)) {
            try {
                $query = urlencode("site:shopee.com.br \"{$termo}\" \"R$\"");
                $ch = curl_init("https://www.bing.com/search?q={$query}&cc=BR&setlang=pt-BR");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_ENCODING       => '',
                    CURLOPT_HTTPHEADER     => [
                        'Accept-Language: pt-BR,pt;q=0.9',
                        'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'
                    ]
        
                    ]);
                $bingHtml = curl_exec($ch);
                curl_close($ch);

                if (!empty($bingHtml)) {
                    $dom = HtmlDomParser::str_get_html($bingHtml);
                    
                    $blocos = $dom->find('li.b_algo');



                    foreach ($blocos as $bloco) {
                        if (count($resultados) >= $this->limite) {
                            break;
                        }
                        $a = $bloco->find('h2 a', 0);
                        $href = $a ? ($a->href ?? '') : '';
                        if (strpos($href, 'shopee.com.br') === false) {
                            continue;
                        }

                        $texto = preg_replace('/\s+/u', ' ', html_entity_decode($bloco->plaintext, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        if (preg_match('/R\$\s*([\d\.]+,\d{2})/', $texto, $matches)) {
                            $preco = (float) str_replace(['.', ','], ['', '.'], $matches[1]);
                            $titulo = trim(str_replace(['| Shopee Brasil', '- Shopee Brasil'], '', html_entity_decode($a->plaintext, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

                            if ($preco > 0 && !empty($titulo)) {
                                $resultados[] = [
                                    'fornecedor' => 'Shopee',
                                    'titulo'     => mb_substr($titulo, 0, 95),
                                    'preco'      => $preco,
                                    'link'       => $href
                                ];
                            }
                        }
                    }
                    $dom->clear();
                }
            } catch (Throwable $e) {
                
            }
        }

        return $resultados;
    }
}