<?php

require_once __DIR__ . '/Dado.php';
require_once __DIR__ . '/Personagem.php';
require_once __DIR__ . '/Desafio.php';
require_once __DIR__ . '/Cena.php';
require_once __DIR__ . '/Database.php';

class Game
{
    private Personagem $personagem;
    private array $cenas;
    private string $cenaAtual;
    private ?Database $database;

    public function __construct(array $estado = [])
    {
        $this->personagem = isset($estado['personagem'])
            ? Personagem::fromArray($estado['personagem'])
            : new Personagem('Aragor');

        $this->cenaAtual = $estado['cenaAtual'] ?? 'inicio';
        $this->database = new Database();
        $this->cenas = $this->definirCenas();
    }

    public function getPersonagem(): Personagem
    {
        return $this->personagem;
    }

    public function getCenaAtual(): Cena
    {
        return $this->cenas[$this->cenaAtual];
    }

    public function getCenaAtualId(): string
    {
        return $this->cenaAtual;
    }

    public function getCenas(): array
    {
        return $this->cenas;
    }

    public function processarEscolha(string $opcaoId): array
    {
        if (in_array($this->cenaAtual, ['vitoria', 'derrota'], true)) {
            return [
                'sucesso' => false,
                'mensagem' => 'A aventura já terminou. Reinicie para jogar novamente.',
            ];
        }

        $opcoes = $this->getCenaAtual()->getOpcoes();
        if (!isset($opcoes[$opcaoId])) {
            return [
                'sucesso' => false,
                'mensagem' => 'Escolha inválida para esta cena.',
            ];
        }

        $opcao = $opcoes[$opcaoId];
        $chanceSucesso = (float) ($opcao['chanceSucesso'] ?? 0.5);
        $chanceDerrota = (float) ($opcao['chanceDerrota'] ?? (1 - $chanceSucesso));
        $rolagem = random_int(1, 100);
        $percentual = $chanceSucesso * 100;
        $sucesso = $rolagem <= $percentual;

        $resultado = [
            'opcao' => $opcao['titulo'],
            'chanceSucesso' => $chanceSucesso,
            'chanceDerrota' => $chanceDerrota,
            'sucesso' => $sucesso,
            'mensagem' => '',
            'proximo' => $opcao['proximo'] ?? $this->cenaAtual,
        ];

        if ($sucesso) {
            $this->personagem->ganharPontos((int) ($opcao['pontos'] ?? 15));
            $this->personagem->ganharEnergia((int) ($opcao['energia'] ?? 10));
            $this->personagem->recuperarVida((int) ($opcao['vida'] ?? 5));
            $this->cenaAtual = $opcao['proximoSucesso'] ?? ($opcao['proximo'] ?? $this->cenaAtual);
            $resultado['mensagem'] = $opcao['mensagemSucesso'] ?? 'Você concluiu a ação com sucesso.';
        } else {
            $this->personagem->ganharPontos((int) ($opcao['pontosFalha'] ?? 5));
            $this->personagem->gastarEnergia((int) ($opcao['energiaFalha'] ?? 12));
            $this->personagem->perderVida((int) ($opcao['vidaFalha'] ?? 20));
            $this->cenaAtual = $opcao['proximoFalha'] ?? ($opcao['proximo'] ?? $this->cenaAtual);
            $resultado['mensagem'] = $opcao['mensagemFalha'] ?? 'A ação falhou e você sofreu as consequências.';
        }

        if ($this->personagem->getVida() <= 0) {
            $this->cenaAtual = 'derrota';
            $resultado['mensagem'] = 'Você caiu em combate e a escuridão venceu.';
        }

        if ($this->cenaAtual === 'vitoria' || $this->cenaAtual === 'derrota') {
            $this->salvarPontuacaoSeNecessario();
        }

        return $resultado;
    }

    public function salvarPontuacaoSeNecessario(): void
    {
        if (in_array($this->cenaAtual, ['vitoria', 'derrota'], true)) {
            $this->database->salvarPontuacao($this->personagem);
        }
    }

    public function toArray(): array
    {
        return [
            'personagem' => $this->personagem->toArray(),
            'cenaAtual' => $this->cenaAtual,
        ];
    }

    private function definirCenas(): array
    {
        return [
            'inicio' => new Cena('inicio', 'Capela da Aurora', 'Você acorda em uma capela antiga, com o cheiro de velas apagadas e pedra úmida no ar. A luz do amanhecer entra pela janela quebrada e revela um reino em silêncio, como se o mundo inteiro estivesse esperando por uma decisão. O sino da torre ecoa ao longe, e você entende que a missão não é apenas sobreviver, mas recuperar a coragem que o povo perdeu.', 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?auto=format&fit=crop&w=1200&q=80', 'normal', 'bosque', [
                'seguir_luz' => [
                    'titulo' => 'Seguir a luz do altar',
                    'descricao' => 'Você se entrega ao brilho sagrado do altar, sente o calor da fé em suas mãos e escolhe o caminho mais seguro, mesmo que o destino ainda esteja oculto pela névoa.',
                    'chanceSucesso' => 0.72,
                    'chanceDerrota' => 0.28,
                    'proximoSucesso' => 'bosque',
                    'proximoFalha' => 'bosque',
                    'pontos' => 20,
                    'pontosFalha' => 5,
                    'energia' => 10,
                    'energiaFalha' => 10,
                    'vida' => 8,
                    'vidaFalha' => 15,
                    'mensagemSucesso' => 'A luz guia seus passos e a jornada começa com confiança; cada pedra do caminho parece lhe dar uma promessa de vitória.',
                    'mensagemFalha' => 'A luz vacila por um instante e você cai em um caminho perigoso antes mesmo de deixar a capela, como se o destino o tivesse testado antes do primeiro passo.',
                ],
                'examinar_ruinas' => [
                    'titulo' => 'Examinar as ruínas',
                    'descricao' => 'Você se abaixa entre os escombros, passa a mão pelas paredes rachadas e descobre rastros de uma batalha antiga que ainda contêm a memória do reino.',
                    'chanceSucesso' => 0.58,
                    'chanceDerrota' => 0.42,
                    'proximoSucesso' => 'bosque',
                    'proximoFalha' => 'bosque',
                    'pontos' => 18,
                    'pontosFalha' => 4,
                    'energia' => 8,
                    'energiaFalha' => 12,
                    'vida' => 6,
                    'vidaFalha' => 22,
                    'mensagemSucesso' => 'As ruínas revelam uma rota esquecida, como um mapa invisível desenhado pela história e pela dor dos antigos guerreiros.',
                    'mensagemFalha' => 'A passagem sombria se fecha sobre você, e por um momento a escuridão parece mais viva do que qualquer criatura do reino.',
                ],
            ]),
            'bosque' => new Cena('bosque', 'Bosque da Bruma', 'Você atravessa um bosque fechado, onde as árvores se curvam como antigas sentinelas e a névoa parece respirar junto com você. Ao longe, entre os troncos, um lobo enorme o observa com olhos brilhantes, sem medo nem desejo de fugir. O mundo parece abafado, como se o próprio ar estivesse esperando a sua próxima decisão.', 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80', 'desafio', 'ponte', [
                'atacar_lobo' => [
                    'titulo' => 'Atacar o lobo',
                    'descricao' => 'Com a espada firme na mão, você avança contra o lobo antes que ele dê o primeiro salto, ouvindo o estalo seco das folhas sob seus pés.',
                    'chanceSucesso' => 0.5,
                    'chanceDerrota' => 0.5,
                    'proximoSucesso' => 'ponte',
                    'proximoFalha' => 'ponte',
                    'pontos' => 25,
                    'pontosFalha' => 6,
                    'energia' => 12,
                    'energiaFalha' => 15,
                    'vida' => 10,
                    'vidaFalha' => 25,
                    'mensagemSucesso' => 'Você derrota o lobo e abre caminho pela floresta, deixando para trás o cheiro de sangue, medo e um céu que parece finalmente respirar novamente.',
                    'mensagemFalha' => 'O lobo ataca com ferocidade e você é empurrado para o rio, sentindo o golpe de dentes e o peso do pânico lhe puxando para baixo.',
                ],
                'esconderse' => [
                    'titulo' => 'Esconder-se na névoa',
                    'descricao' => 'Você se mistura à bruma, movendo-se em silêncio entre os troncos, ouvindo o farfalhar das folhas e geando ao sentir olhos invisíveis sobre você.',
                    'chanceSucesso' => 0.7,
                    'chanceDerrota' => 0.3,
                    'proximoSucesso' => 'ponte',
                    'proximoFalha' => 'ponte',
                    'pontos' => 20,
                    'pontosFalha' => 8,
                    'energia' => 8,
                    'energiaFalha' => 14,
                    'vida' => 6,
                    'vidaFalha' => 18,
                    'mensagemSucesso' => 'A névoa te protege e a rota para a ponte fica clara, como se a própria floresta reconhecesse a sua coragem silenciosa.',
                    'mensagemFalha' => 'Você é encontrado por criaturas da floresta e arrastado até a mina, sem tempo de gritar, apenas de lutar pela própria sobrevivência.',
                ],
                'seguir_rasto' => [
                    'titulo' => 'Seguir o rastro no chão',
                    'descricao' => 'Você observa as pegadas molhadas no chão, procurando o caminho mais rápido entre os troncos retorcidos e a lama que já engoliu alguma batalha.',
                    'chanceSucesso' => 0.62,
                    'chanceDerrota' => 0.38,
                    'proximoSucesso' => 'ponte',
                    'proximoFalha' => 'ponte',
                    'pontos' => 18,
                    'pontosFalha' => 7,
                    'energia' => 10,
                    'energiaFalha' => 13,
                    'vida' => 5,
                    'vidaFalha' => 20,
                    'mensagemSucesso' => 'Os rastros levam você à ponte, e o vento da madrugada parece cessar por um instante, como se o mundo aguardasse sua passagem.',
                    'mensagemFalha' => 'O rastro te leva até uma caverna esquecida, onde o eco dos passos parece ser o único som vivo no lugar.',
                ],
            ]),
            'ponte' => new Cena('ponte', 'Ponte do Abismo', 'A ponte de pedra cruza um abismo negro e profundo, onde o vento sobe como um suspiro de morte. Cada faixa de madeira e cada corrimão enferrujado tremem sob seus pés, e a distância entre o mundo conhecido e o desconhecido parece se abrir com cada passo. O céu acima está pálido, mas o vazio abaixo parece ter memória do que perdeu.', 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=1200&q=80', 'desafio', 'vila', [
                'correr_rapido' => [
                    'titulo' => 'Correr rapidamente',
                    'descricao' => 'Você se lança na travessia com toda a velocidade que o medo permite, sentindo a ponte tremer sob o peso do seu corpo e do abismo abaixo.',
                    'chanceSucesso' => 0.55,
                    'chanceDerrota' => 0.45,
                    'proximoSucesso' => 'vila',
                    'proximoFalha' => 'vila',
                    'pontos' => 22,
                    'pontosFalha' => 6,
                    'energia' => 10,
                    'energiaFalha' => 12,
                    'vida' => 8,
                    'vidaFalha' => 20,
                    'mensagemSucesso' => 'Você atravessa a ponte em um impulso feroz e chega à vila com o coração batendo forte, como se tivesse roubado um pedaço da noite.',
                    'mensagemFalha' => 'A ponte cede e você cai em uma mina abandonada, sentindo o ar frio e o baque das pedras quando o mundo escurece de repente.',
                ],
                'usar_corrente' => [
                    'titulo' => 'Usar a corrente de ferro',
                    'descricao' => 'Você agarra a corrente de ferro pendurada no lado da ponte, usando-a como suporte para manter o equilíbrio enquanto o abismo murmura lá embaixo.',
                    'chanceSucesso' => 0.68,
                    'chanceDerrota' => 0.32,
                    'proximoSucesso' => 'vila',
                    'proximoFalha' => 'vila',
                    'pontos' => 20,
                    'pontosFalha' => 5,
                    'energia' => 8,
                    'energiaFalha' => 10,
                    'vida' => 7,
                    'vidaFalha' => 18,
                    'mensagemSucesso' => 'A corrente resiste ao peso do corpo e da decisão, e você chega ao outro lado com a sensação de ter vencido uma pequena morte.',
                    'mensagemFalha' => 'O ferro quebra com um som metálico e você cai no rio das almas, onde as águas parecem refletir todas as tragédias do reino.',
                ],
                'esperar_uma_racha' => [
                    'titulo' => 'Esperar a rachadura melhorar',
                    'descricao' => 'Você observa a ponte, espera a rachadura se abrir e se fechar no ritmo do vento, tentando decidir se o momento perfeito finalmente chegou.',
                    'chanceSucesso' => 0.45,
                    'chanceDerrota' => 0.55,
                    'proximoSucesso' => 'vila',
                    'proximoFalha' => 'vila',
                    'pontos' => 12,
                    'pontosFalha' => 4,
                    'energia' => 6,
                    'energiaFalha' => 18,
                    'vida' => 5,
                    'vidaFalha' => 30,
                    'mensagemSucesso' => 'Você aguarda o momento perfeito e atravessa com precisão, como se o destino tivesse finalmente escolhido o seu nome.',
                    'mensagemFalha' => 'A ponte quebra completamente e você cai no abismo, ouvindo o silêncio profundo do vazio com a última certeza de que a coragem nem sempre basta.',
                ],
            ]),
            'vila' => new Cena('vila', 'Vila de Sable', 'Ao chegar à vila, você encontra casas queimadas, janelas quebradas e uma fumaça fina que ainda não se dissipou. As pessoas escondem a dor, mas os olhares carregam o peso de civis que viram reféns da escuridão. Em meio ao pânico, um homem de rosto marcado aponta para o topo da colina e sussurra que um portal antigo foi aberto dentro do castelo.', 'https://images.unsplash.com/photo-1473448912268-2022ce9509d8?auto=format&fit=crop&w=1200&q=80', 'normal', 'templo', [
                'pesquisar_aliados' => [
                    'titulo' => 'Buscar ajuda dos aliados',
                    'descricao' => 'Você fala com os sobreviventes, escuta histórias de perdas e percebe que, entre o medo, há pessoas que ainda acreditam na esperança do reino.',
                    'chanceSucesso' => 0.75,
                    'chanceDerrota' => 0.25,
                    'proximoSucesso' => 'templo',
                    'proximoFalha' => 'templo',
                    'pontos' => 25,
                    'pontosFalha' => 8,
                    'energia' => 10,
                    'energiaFalha' => 12,
                    'vida' => 8,
                    'vidaFalha' => 18,
                    'mensagemSucesso' => 'Os moradores revelam a direção do templo e o caminho fica mais claro, como se a fé deles tivesse se transformado em um mapa para você.',
                    'mensagemFalha' => 'Você perde tempo e parte para o castelo sem o apoio da vila, deixando para trás a única chance real de ser acolhido no fim da jornada.',
                ],
                'comprar_provisoes' => [
                    'titulo' => 'Comprar provisões',
                    'descricao' => 'Você compra pão duro, água e ferramentas simples em um mercado quase vazio, percebendo que cada sábado de sobrevivência agora vale ouro.',
                    'chanceSucesso' => 0.8,
                    'chanceDerrota' => 0.2,
                    'proximoSucesso' => 'templo',
                    'proximoFalha' => 'templo',
                    'pontos' => 20,
                    'pontosFalha' => 6,
                    'energia' => 8,
                    'energiaFalha' => 15,
                    'vida' => 6,
                    'vidaFalha' => 10,
                    'mensagemSucesso' => 'As provisões ajudam a sustentar sua força, e a jornada para o templo parece menos cruel quando a barriga não lateja de fome.',
                    'mensagemFalha' => 'A negociação sai ruim, e você acaba indo para a mina com a mochila leve mas o corpo cansado, como quem foi empurrado por um destino impaciente.',
                ],
            ]),
            'templo' => new Cena('templo', 'Templo da Lua', 'Você entra em um templo em ruínas, onde as colunas de pedra guardam o silêncio de séculos. O chão está coberto de poeira antiga e a lua, refletida em um espelho quebrado, projeta uma luz triste sobre a estátua central. Há algo de sagrado no lugar, e também algo de ameaçador: como se a própria divindade estivesse esperando para ver se você merece o poder que vem pela frente.', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80', 'desafio', 'castelo', [
                'invocar_luz' => [
                    'titulo' => 'Invocar a luz celestial',
                    'descricao' => 'Você levanta os braços e chama a luz antiga do santuário, sentindo uma energia sagrada percorrer seu corpo como um fogo frio e glorioso.',
                    'chanceSucesso' => 0.66,
                    'chanceDerrota' => 0.34,
                    'proximoSucesso' => 'castelo',
                    'proximoFalha' => 'castelo',
                    'pontos' => 24,
                    'pontosFalha' => 7,
                    'energia' => 10,
                    'energiaFalha' => 18,
                    'vida' => 10,
                    'vidaFalha' => 20,
                    'mensagemSucesso' => 'A luz do templo protege você e conduz ao castelo, como uma bênção que não pede nada em troca além da coragem.',
                    'mensagemFalha' => 'O ritual falha e você é derrubado até o rio das almas, onde as águas parecem recordar todas as promessas que foram quebradas.',
                ],
                'descobrir_segredo' => [
                    'titulo' => 'Descobrir o segredo oculto',
                    'descricao' => 'Você observa a estátua com atenção, passa os dedos sobre a pedra e descobre uma chave escondida em uma rachadura antiga.',
                    'chanceSucesso' => 0.58,
                    'chanceDerrota' => 0.42,
                    'proximoSucesso' => 'castelo',
                    'proximoFalha' => 'castelo',
                    'pontos' => 22,
                    'pontosFalha' => 6,
                    'energia' => 8,
                    'energiaFalha' => 14,
                    'vida' => 7,
                    'vidaFalha' => 18,
                    'mensagemSucesso' => 'O segredo da lua revela a rota certa para o castelo, e a pedra sob os seus pés parece vibrar com uma memória antiga.',
                    'mensagemFalha' => 'A estátua responde com uma ameaça e você cai na caverna, sentindo a terra escura engolir o som dos seus passos.',
                ],
            ]),
            'rio' => new Cena('rio', 'Rio das Almas', 'O rio corre entre montanhas negras e a água parece não refletir o céu, mas sim os círculos escuros daquilo que foi perdido. Cada onda leva consigo o murmúrio de vozes antigas, como se o rio guardasse todas as almas que não conseguiram escapar da escuridão. A travessia não parece apenas física; parece uma prova de memória e coragem.', 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=1200&q=80', 'desafio', 'caverna', [
                'nadar_contra_corrente' => [
                    'titulo' => 'Nadar contra a corrente',
                    'descricao' => 'Você mergulha na correnteza e usa todas as forças para avançar contra a água, sentindo o peso do rio empurrando você para o fundo.',
                    'chanceSucesso' => 0.5,
                    'chanceDerrota' => 0.5,
                    'proximoSucesso' => 'caverna',
                    'proximoFalha' => 'caverna',
                    'pontos' => 18,
                    'pontosFalha' => 4,
                    'energia' => 14,
                    'energiaFalha' => 20,
                    'vida' => 8,
                    'vidaFalha' => 30,
                    'mensagemSucesso' => 'Você vence a correnteza e chega à caverna com os braços pesados, mas com a certeza de que o rio não venceu o seu destino.',
                    'mensagemFalha' => 'A corrente toma conta de você e sua jornada termina aqui, como se o rio tivesse reconhecido a sua fraqueza naquele instante.',
                ],
                'construir_pontes' => [
                    'titulo' => 'Construir um caminho improvisado',
                    'descricao' => 'Você reúne troncos secos, pedras e um pouco de coragem, improvisando um caminho quiçá precário, mas capaz de te tirar do rio antes que ele te consuma.',
                    'chanceSucesso' => 0.62,
                    'chanceDerrota' => 0.38,
                    'proximoSucesso' => 'caverna',
                    'proximoFalha' => 'caverna',
                    'pontos' => 20,
                    'pontosFalha' => 7,
                    'energia' => 12,
                    'energiaFalha' => 16,
                    'vida' => 7,
                    'vidaFalha' => 18,
                    'mensagemSucesso' => 'O caminho improvisado funciona e você ultrapassa o rio com a sensação de ter vencido não só as águas, mas também a própria dúvida.',
                    'mensagemFalha' => 'Você só consegue chegar até a mina, ferido e exausto, como quem saiu vivo de um pesadelo, mas ainda não venceu a guerra.',
                ],
            ]),
            'caverna' => new Cena('caverna', 'Caverna do Eco', 'No fundo da caverna, cada passo ecoa contra as paredes como se o próprio chão estivesse repetindo as suas dúvidas. O ar está frio e pesado, e um guardião antigo parece te observar de dentro das sombras, imóvel como uma estátua viva. Há uma passagem secreta adiante, mas ela exige mais do que força: exige coragem e inteligência.', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80', 'desafio', 'portal', [
                'resolver_enigma' => [
                    'titulo' => 'Resolver o enigma ancestral',
                    'descricao' => 'Você observa os símbolos antigos gravados na pedra, entende que não são apenas desenhos, mas uma linguagem esquecida, e tenta despertar a porta da caverna.',
                    'chanceSucesso' => 0.72,
                    'chanceDerrota' => 0.28,
                    'proximoSucesso' => 'portal',
                    'proximoFalha' => 'mina',
                    'pontos' => 28,
                    'pontosFalha' => 8,
                    'energia' => 10,
                    'energiaFalha' => 18,
                    'vida' => 8,
                    'vidaFalha' => 20,
                    'mensagemSucesso' => 'O enigma cede e você alcança o portal com vantagem, como se os próprios ancestrais tivessem decidido te deixar passar.',
                    'mensagemFalha' => 'A resposta está errada e você é arrastado de volta até a mina, com o som dos ecos da caverna lhe perseguindo pela escuridão.',
                ],
                'enfrentar_guardiao' => [
                    'titulo' => 'Enfrentar o guardião',
                    'descricao' => 'Você se prepara para o combate com a vontade de quebrar o feitiço à força, mesmo sabendo que o guardião antigo não cede facilmente.',
                    'chanceSucesso' => 0.48,
                    'chanceDerrota' => 0.52,
                    'proximoSucesso' => 'portal',
                    'proximoFalha' => 'portal',
                    'pontos' => 26,
                    'pontosFalha' => 4,
                    'energia' => 12,
                    'energiaFalha' => 20,
                    'vida' => 10,
                    'vidaFalha' => 35,
                    'mensagemSucesso' => 'Você derrota o guardião e avança ao portal, sentindo que a própria caverna se inclina em reverência ao seu valor.',
                    'mensagemFalha' => 'A criatura sobrepuja você e a jornada termina ali, envolvida no manto de pedra e silêncio que a caverna guarda tão bem.',
                ],
            ]),
            'mina' => new Cena('mina', 'Mina da Escuridão', 'Você entra em uma mina abandonada, onde o ar cheira a ferro, mofo e pedra antiga. Lanternas quebradas se balançam em correntes oxidadas e o chão está repleto de ferramentas esquecidas por trabalhadores que nunca voltaram. No fundo, há um caminho estreito que parece levar diretamente ao portal, como se a própria terra apontasse para a última chance do reino.', 'https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1200&q=80', 'normal', 'portal', [
                'pegar_arma_antiga' => [
                    'titulo' => 'Pegar a arma antiga',
                    'descricao' => 'Você pega uma espada enferrujada entre as rochas, sentindo o peso do passado em cada golpe que a arma parece ter recebido antes de você.',
                    'chanceSucesso' => 0.64,
                    'chanceDerrota' => 0.36,
                    'proximoSucesso' => 'portal',
                    'proximoFalha' => 'derrota',
                    'pontos' => 22,
                    'pontosFalha' => 6,
                    'energia' => 10,
                    'energiaFalha' => 15,
                    'vida' => 6,
                    'vidaFalha' => 25,
                    'mensagemSucesso' => 'A arma antiga te dá vantagem na última etapa, como se a história do reino estivesse te lançando uma última ajuda antes do fim.',
                    'mensagemFalha' => 'A arma quebra e você quase sucumbe no caminho, sentindo o peso da derrota se aproximar como uma sombra que conhece o seu nome.',
                ],
                'seguir_tunel' => [
                    'titulo' => 'Seguir o túnel de luz',
                    'descricao' => 'Você segue a luz fraca que cresce ao fundo da mina, como um fio de esperança desenhado em meio à escuridão mais profunda.',
                    'chanceSucesso' => 0.7,
                    'chanceDerrota' => 0.3,
                    'proximoSucesso' => 'portal',
                    'proximoFalha' => 'portal',
                    'pontos' => 20,
                    'pontosFalha' => 7,
                    'energia' => 8,
                    'energiaFalha' => 12,
                    'vida' => 8,
                    'vidaFalha' => 20,
                    'mensagemSucesso' => 'A luz guia você até o portal sem perder o ritmo, e a escuridão recua a cada passo, como se reconhecesse a sua vontade.',
                    'mensagemFalha' => 'O túnel escorre para o rio e você se perde em terra sombria, onde cada sombra parece levar consigo uma lembrança daquilo que você ainda não salvou.',
                ],
            ]),
            'castelo' => new Cena('castelo', 'Castelo de Gelo', 'O castelo aparece entre as nuvens como uma fortaleza de vidro e geada, com torres que brilham como espadas congeladas. O vento corta a pele, e o chão parece um espelho quebrado. Lá dentro, no centro da torre mais alta, repousa a última chave que ainda pode salvar o reino, mas o caminho até ela está envolto em gelo, silêncio e promessas antigas.', 'https://images.unsplash.com/photo-1511818966892-d7d671e672a2?auto=format&fit=crop&w=1200&q=80', 'normal', 'portal', [
                'subir_torre' => [
                    'titulo' => 'Subir até a torre',
                    'descricao' => 'Você avança pelas escadas geladas em busca da chave, sentindo a temperatura cair com cada degrau e ouvindo o eco dos seus próprios passos como se a torre estivesse te avaliando.',
                    'chanceSucesso' => 0.66,
                    'chanceDerrota' => 0.34,
                    'proximoSucesso' => 'portal',
                    'proximoFalha' => 'portal',
                    'pontos' => 28,
                    'pontosFalha' => 5,
                    'energia' => 12,
                    'energiaFalha' => 18,
                    'vida' => 10,
                    'vidaFalha' => 30,
                    'mensagemSucesso' => 'A torre entrega a chave e você chega ao portal com a sensação de ter conquistado o coração congelado do castelo.',
                    'mensagemFalha' => 'O gelo vence e você cai em um fim cruel, onde a última luz do reino se apaga antes mesmo de você alcançar o que procurava.',
                ],
                'liberar_chave' => [
                    'titulo' => 'Liberar a chave da muralha',
                    'descricao' => 'Você usa a magia do castelo para quebrar o feitiço da muralha, sentindo o mundo inteiro vibrar quando a pedra começa a ceder diante da sua vontade.',
                    'chanceSucesso' => 0.78,
                    'chanceDerrota' => 0.22,
                    'proximoSucesso' => 'portal',
                    'proximoFalha' => 'portal',
                    'pontos' => 30,
                    'pontosFalha' => 8,
                    'energia' => 10,
                    'energiaFalha' => 16,
                    'vida' => 8,
                    'vidaFalha' => 20,
                    'mensagemSucesso' => 'A muralha abre e a chave vibra em sua mão, como se o castelo reconhecesse finalmente que a hora da salvação havia chegado.',
                    'mensagemFalha' => 'A parede se fecha e você cai por um caminho mais escuro, onde as sombras parecem se lembrar de todas as derrotas que já aconteceram no reino.',
                ],
            ]),
            'portal' => new Cena('portal', 'Portal do Rei', 'No centro do reino, o portal pulsa como um coração maldito, espalhando luz vermelha e sombras tremeluzentes. O ar vibra, o chão treme, e tudo ao redor parece preparado para a última disputa. O destino do reino está em jogo, e o silêncio agora é mais pesado do que qualquer batalha anterior.', 'https://images.unsplash.com/photo-1516574187841-cb9cc2ca948b?auto=format&fit=crop&w=1200&q=80', 'desafio', 'vitoria', [
                'enfrentar_rei' => [
                    'titulo' => 'Enfrentar o rei das trevas',
                    'descricao' => 'Você entra na batalha final com o coração em chamas, pronto para decidir o destino do reino e o futuro das pessoas que ainda confiam em você.',
                    'chanceSucesso' => 0.55,
                    'chanceDerrota' => 0.45,
                    'proximoSucesso' => 'vitoria',
                    'proximoFalha' => 'derrota',
                    'pontos' => 35,
                    'pontosFalha' => 5,
                    'energia' => 12,
                    'energiaFalha' => 20,
                    'vida' => 10,
                    'vidaFalha' => 40,
                    'mensagemSucesso' => 'Você derrota o rei das trevas e salva o reino da escuridão, deixando para trás não apenas uma vitória, mas a promessa de um novo amanhecer.',
                    'mensagemFalha' => 'A última batalha te consome e o reino se entrega ao caos, como se a noite tivesse vencido não por força, mas por ter esperado o momento certo.',
                ],
                'fechar_portal' => [
                    'titulo' => 'Fechar o portal à força',
                    'descricao' => 'Você usa toda sua força para selar a entrada infernal, sentindo o peso do portal empurrar contra sua alma enquanto o mundo treme ao seu redor.',
                    'chanceSucesso' => 0.68,
                    'chanceDerrota' => 0.32,
                    'proximoSucesso' => 'vitoria',
                    'proximoFalha' => 'derrota',
                    'pontos' => 30,
                    'pontosFalha' => 6,
                    'energia' => 14,
                    'energiaFalha' => 18,
                    'vida' => 8,
                    'vidaFalha' => 35,
                    'mensagemSucesso' => 'O portal se fecha e a luz retorna ao reino, como se a própria terra tivesse respirado aliviada depois de um longo pesadelo.',
                    'mensagemFalha' => 'O selo falha e a escuridão toma conta de tudo, tornando a última visão do reino um símbolo de uma jornada que não conseguiu salvar o que era mais precioso.',
                ],
            ]),
            'vitoria' => new Cena('vitoria', 'Vitória', 'O reino se ilumina com uma luz antiga e acolhedora. As pessoas saem de suas casas, os campos voltam a reverdecer e os aliados se reúnem em torno de você como se a história finalmente tivesse escolhido o caminho certo. A escuridão recua, e o nome do herói passa a ser cantado nos muros de todas as cidades.', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80', 'final', null),
            'derrota' => new Cena('derrota', 'Derrota', 'A escuridão vence, mas o reino não cai em silêncio. As sombras se espalham pelas muralhas, as lanternas apagam-se uma a uma e o mundo parece perder a última lembrança de esperança. Ainda assim, a história foi escrita por você, e sua jornada permanecerá nos sonhos de quem um dia ousar seguir o mesmo caminho.', 'https://images.unsplash.com/photo-1501854140801-50d01698950b?auto=format&fit=crop&w=1200&q=80', 'final', null),
        ];
    }
}
