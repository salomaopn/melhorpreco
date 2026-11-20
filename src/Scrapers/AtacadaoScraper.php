
<?php


use HeadlessChromium\Browser;
use voku\helper\HtmlDomParser;

class AtacadaoScraper
{
    private int $limite;

    public function __construct(int $limite = 5)
    {
        $this->limite = $limite;
    }

    public function buscar(Browser $browser, string $produto): array
    {
        $resultados = [];
        $termoUrl = urlencode($produto);

        try {
            $page = $browser->createPage();
            $page->navigate("https://www.atacadao.com.br/s?q={$termoUrl}")->waitForNavigation();
            sleep(5);

            $html = $page->getHtml();
            $page->close();

            if (empty($html)) {
                return [];
            }

            $dom = HtmlDomParser::str_get_html($html);
            $cards = $dom->find('article');

            $count = 0;
            foreach ($cards as $card) {
                if ($count >= $this->limite) {
                    break;
                }

                $tituloEl = $card->find('h3', 0);
                if (!$tituloEl) {
                    $tituloEl = $card->find('h2', 0);
                }
                if (!$tituloEl) {
                    $tituloEl = $card->find('a', 0);
                }

                $linkEl = $card->find('a', 0);
                // Converte &nbsp; e espaços múltiplos em espaço simples
                $textoCard = preg_replace('/\s+/u', ' ', html_entity_decode($card->plaintext, ENT_QUOTES | ENT_HTML5, 'UTF-8') );

               
                if ($tituloEl && preg_match('/R\$\s*([\d\.]+,\d{2})/', $textoCard, $matches)) {
                    $preco = (float) str_replace(['.', ','], ['', '.'], $matches[1]);
                    $titulo = trim(html_entity_decode($tituloEl->plaintext, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    // Se houver condição de atacado ("A partir de X unid."), anexa ao título para clareza
                    if (preg_match('/(A partir de \d+ unid\.)/i', $textoCard, $condMatch)) {
                        $titulo .= " ({$condMatch[1]})";
               
                        }

                    if ($preco > 0 && !empty($titulo)) {
                        $href = $linkEl ? $linkEl->href : '#';
                        if ($href !== '#' && strpos($href, 'http') !== 0) {
                            $href = 'https://www.atacadao.com.br' . $href;
                        }

                        $resultados[] = [
                            'fornecedor' => 'Atacadão Asa Norte',
                            'titulo'     => $titulo,
                            'preco'      => $preco,
                            'link'       => $href
                        ];
                        $count++;
                    }
                }
            }
            $dom->clear();
        } catch (Throwable $e) {
            
        }

        return $resultados;
    }
}