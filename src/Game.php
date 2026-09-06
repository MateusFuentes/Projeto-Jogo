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
    private array $historico;
    private ?Database $database;

    public function __construct(array $estado = [])
    {
        $this->personagem = isset($estado['personagem'])
            ? Personagem::fromArray($estado['personagem'])
            : new Personagem('Aragor');

        $this->cenaAtual = $estado['cenaAtual'] ?? 'inicio';
        $this->historico = array_values(array_filter(
            $estado['historico'] ?? [],
            fn ($cenaId) => is_string($cenaId)
        ));
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

    private function ajustarChanceSucesso(float $chanceBase, string $cenaId): float
    {
        $chance = max(0.50, min(0.75, $chanceBase - 0.02));

        if ($this->personagem->getEnergia() < 10) {
            $chance -= 0.12;
        }

        if ($this->personagem->getVida() < 35) {
            $chance -= 0.15;
        }

        if (in_array($cenaId, ['portal', 'castelo', 'caverna', 'mina'], true)) {
            $chance -= 0.10;
        }

        if ($cenaId === 'vitoria' || $cenaId === 'derrota') {
            $chance = 0.45;
        }

        return max(0.50, min(0.75, $chance));
    }

    private function resolverDestino(array $opcao, bool $sucesso): string
    {
        $destinoSucesso = $opcao['proximoSucesso'] ?? ($opcao['proximo'] ?? $this->cenaAtual);
        $destinoFalha = $opcao['proximoFalha'] ?? ($opcao['proximo'] ?? $this->cenaAtual);

        if ($sucesso) {
            return $destinoSucesso;
        }

        if ($destinoFalha === $destinoSucesso) {
            return $this->cenaAtual;
        }

        return $destinoFalha;
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
        $chanceBase = (float) ($opcao['chanceSucesso'] ?? 0.5);
        $chanceSucesso = $this->ajustarChanceSucesso($chanceBase, $this->cenaAtual);
        $chanceDerrota = 1 - $chanceSucesso;
        $rolagem = random_int(1, 100);
        $percentual = $chanceSucesso * 100;
        $sucesso = $rolagem <= $percentual;

        $resultado = [
            'opcao' => $opcao['titulo'],
            'chanceSucesso' => round($chanceSucesso, 2),
            'chanceDerrota' => round($chanceDerrota, 2),
            'sucesso' => $sucesso,
            'mensagem' => '',
            'proximo' => $opcao['proximo'] ?? $this->cenaAtual,
        ];

        if (!empty($opcao['fatal'])) {
            $this->personagem->perderVida($this->personagem->getVida());
            $this->cenaAtual = 'derrota';
            $resultado['chanceSucesso'] = 0;
            $resultado['chanceDerrota'] = 1;
            $resultado['sucesso'] = false;
            $resultado['mensagem'] = $opcao['mensagemFatal'] ?? 'A escolha fatal encerrou sua jornada imediatamente.';
            $this->salvarPontuacaoSeNecessario();
            return $resultado;
        }

        if ($sucesso) {
            $this->personagem->ganharPontos((int) ($opcao['pontos'] ?? 15));
            $this->personagem->ganharEnergia((int) ($opcao['energia'] ?? 10));
            $this->personagem->recuperarVida((int) ($opcao['vida'] ?? 5));
            $proximaCena = $this->resolverDestino($opcao, true);
            if ($proximaCena !== $this->cenaAtual) {
                $this->historico[] = $this->cenaAtual;
                $this->cenaAtual = $proximaCena;
            }
            $resultado['mensagem'] = $opcao['mensagemSucesso'] ?? 'Você concluiu a ação com sucesso.';
        } else {
            $this->personagem->ganharPontos((int) ($opcao['pontosFalha'] ?? 5));
            $this->personagem->gastarEnergia((int) ($opcao['energiaFalha'] ?? 12));
            $danoFalha = min(20, (int) ($opcao['vidaFalha'] ?? 20));
            $vidaAntesDaFalha = $this->personagem->getVida();
            $danoFalha = min($danoFalha, max(0, $vidaAntesDaFalha - 1));
            $this->personagem->perderVida($danoFalha);
            while (!empty($this->historico) && end($this->historico) === $this->cenaAtual) {
                array_pop($this->historico);
            }

            $deveRetornar = random_int(1, 100) <= 30;

            if (!empty($this->historico) && $deveRetornar) {
                $this->cenaAtual = array_pop($this->historico);
                $resultado['mensagem'] = ($opcao['mensagemFalha'] ?? 'A ação falhou e você sofreu as consequências.')
                    . ' Você percebe que tomou o caminho errado e retorna automaticamente à cena anterior.';
            } else {
                $resultado['mensagem'] = ($opcao['mensagemFalha'] ?? 'A ação falhou e você sofreu as consequências.')
                    . ' Você permanece nesta cena e precisa tentar outro caminho.';
            }
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
            'historico' => $this->historico,
        ];
    }

    private function getImagemCena(string $id): string
    {
        $imagens = [
            'inicio' => 'capela.jpg',
            'bosque' => 'bosque.jpg',
            'ponte' => 'ponte.jpg',
            'vila' => 'vila.jpg',
            'templo' => 'templo.jpg',
            'rio' => 'rio.jpg',
            'caverna' => 'caverna.jpg',
            'mina' => 'mina.jpg',
            'castelo' => 'castelo.jpg',
            'portal' => 'portal.jpg',
            'vitoria' => 'portal.jpg',
            'derrota' => 'derrota.jpg',
        ];

        return $imagens[$id] ?? 'capela.jpg';
    }

    private function definirCenas(): array
    {
        return [
            'inicio' => new Cena('inicio', 'Capela da Aurora', 'Você desperta sobre as lajes frias da Capela da Aurora, com gosto de cinza na boca e o cheiro de cera queimada preso às paredes. Durante a noite, alguém apagou todas as velas, menos uma: sua chama azulada se inclina sempre na direção da porta, como se apontasse para o perigo. Pelos vitrais quebrados, o amanhecer revela campos cobertos por uma névoa escura e, além deles, torres sem bandeiras. O sino toca uma única vez, embora a corda esteja partida. No altar, o brasão real foi riscado por uma lâmina. Você se lembra da promessa feita antes da queda do reino: encontrar a origem da escuridão antes que o último sino toque.', $this->getImagemCena('inicio'), 'normal', 'bosque', [
                'seguir_luz' => [
                    'titulo' => 'Seguir a luz do altar',
                    'descricao' => 'Você se entrega ao brilho sagrado do altar, sente o calor da fé em suas mãos e escolhe o caminho mais seguro, mesmo que o destino ainda esteja oculto pela névoa.',
                    'chanceSucesso' => 0.92,
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
                'tocar_sino' => [
                    'titulo' => 'Tocar o sino proibido',
                    'descricao' => 'Você puxa a corda do sino rachado e desperta um eco que pode chamar ajuda ou denunciar sua presença aos inimigos.',
                    'chanceSucesso' => 0.42,
                    'proximoSucesso' => 'bosque',
                    'proximoFalha' => 'rio',
                    'pontos' => 24,
                    'pontosFalha' => 2,
                    'energiaFalha' => 16,
                    'vidaFalha' => 24,
                    'mensagemSucesso' => 'O sino convoca um antigo guardião, que revela uma trilha escondida para o bosque.',
                    'mensagemFalha' => 'O sino atrai uma onda de sombras, e você foge para o rio antes que a capela desabe.',
                ],
                'seguir_vela' => [
                    'titulo' => 'Seguir a vela azul',
                    'descricao' => 'Você protege a pequena chama com as mãos e acompanha sua luz pelas ruínas da capela.',
                    'chanceSucesso' => 0.88,
                    'proximoSucesso' => 'bosque',
                    'proximoFalha' => 'rio',
                    'pontos' => 22,
                    'pontosFalha' => 2,
                    'energiaFalha' => 16,
                    'vidaFalha' => 20,
                    'mensagemSucesso' => 'A vela azul revela uma passagem segura e deixa no ar o perfume das antigas cerimônias da Aurora.',
                    'mensagemFalha' => 'A chama se apaga e as ruínas cedem; você escapa por uma passagem subterrânea em direção ao rio.',
                ],
            ]),
            'bosque' => new Cena('bosque', 'Bosque da Bruma', 'A trilha desaparece poucos passos depois da capela. O Bosque da Bruma é formado por árvores antigas, retorcidas pelo frio, cujas raízes levantam a terra como costelas de um animal enterrado. Gotas escorrem das folhas mesmo onde não chove, e a névoa traz vozes que imitam pessoas conhecidas. Entre os troncos, um lobo enorme observa você; há uma cicatriz prateada atravessando seu focinho e um pequeno medalhão real preso à coleira. Ao norte, a ponte range. Ao sul, uma fumaça avermelhada sobe da direção da mina. O bosque parece oferecer vários caminhos, mas nenhum deles parece querer deixá-lo sair.', $this->getImagemCena('bosque'), 'desafio', 'ponte', [
                'atacar_lobo' => [
                    'titulo' => 'Atacar o lobo',
                    'descricao' => 'Com a espada firme na mão, você avança contra o lobo antes que ele dê o primeiro salto, ouvindo o estalo seco das folhas sob seus pés.',
                    'chanceSucesso' => 0.46,
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
                    'chanceSucesso' => 0.86,
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
                'beber_agua_negra' => [
                    'titulo' => 'Beber a água negra',
                    'descricao' => 'Uma poça escura promete força imediata, mas sua superfície não reflete seu rosto.',
                    'fatal' => true,
                    'mensagemFatal' => 'A água negra apaga sua vontade, e a floresta guarda seu corpo como mais um segredo.',
                ],
                'seguir_lobos' => [
                    'titulo' => 'Seguir o lobo ferido',
                    'descricao' => 'Você acompanha o animal marcado pelo medalhão real, confiando que ele conhece uma saída do bosque.',
                    'chanceSucesso' => 0.9,
                    'proximoSucesso' => 'ponte',
                    'proximoFalha' => 'caverna',
                    'pontos' => 24,
                    'pontosFalha' => 3,
                    'energiaFalha' => 14,
                    'vidaFalha' => 18,
                    'mensagemSucesso' => 'O lobo o conduz por uma trilha de caçadores até a ponte, e o medalhão confirma que ele já protegeu este reino.',
                    'mensagemFalha' => 'O animal desaparece na névoa e você acaba seguindo suas pegadas até uma caverna desconhecida.',
                ],
            ]),
            'ponte' => new Cena('ponte', 'Ponte do Abismo', 'A Ponte do Abismo foi construída com pedra negra e tábuas substituídas às pressas, muitas delas marcadas pelos símbolos dos soldados que nunca voltaram. Lá embaixo, o vazio não é silencioso: correntes de ar sobem carregando sussurros, o som distante de água e, às vezes, o chamado de alguém pronunciando seu nome. Do outro lado, as primeiras casas da Vila de Sable brilham sob uma luz amarela e fraca. A ponte se move mesmo quando você fica imóvel. Cada passo será uma negociação com o vento, a madeira e o medo.', $this->getImagemCena('ponte'), 'desafio', 'vila', [
                'correr_rapido' => [
                    'titulo' => 'Correr rapidamente',
                    'descricao' => 'Você se lança na travessia com toda a velocidade que o medo permite, sentindo a ponte tremer sob o peso do seu corpo e do abismo abaixo.',
                    'chanceSucesso' => 0.45,
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
                    'chanceSucesso' => 0.9,
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
                'saltar_abismo' => [
                    'titulo' => 'Saltar diretamente sobre o abismo',
                    'descricao' => 'Você decide confiar apenas nas próprias pernas e ignora a ponte instável sob seus pés.',
                    'fatal' => true,
                    'mensagemFatal' => 'O salto não alcança o outro lado. O abismo encerra sua jornada antes que você possa se arrepender.',
                ],
                'testar_tabua' => [
                    'titulo' => 'Testar as tábuas com uma pedra',
                    'descricao' => 'Você examina cada parte da ponte antes de avançar, procurando o trecho que ainda suporta peso.',
                    'chanceSucesso' => 0.84,
                    'proximoSucesso' => 'vila',
                    'proximoFalha' => 'mina',
                    'pontos' => 23,
                    'pontosFalha' => 3,
                    'energiaFalha' => 14,
                    'vidaFalha' => 22,
                    'mensagemSucesso' => 'A análise encontra um caminho firme e você atravessa enquanto o abismo ruge abaixo.',
                    'mensagemFalha' => 'A pedra revela uma rachadura escondida. Você cai por uma abertura lateral e chega à mina.',
                ],
            ]),
            'vila' => new Cena('vila', 'Vila de Sable', 'A Vila de Sable não foi destruída de uma vez; cada rua mostra uma camada diferente do ataque. Há portas trancadas por dentro, marcas de mãos na fuligem e pratos ainda postos em mesas onde ninguém voltou para comer. Os sobreviventes se escondem atrás de cortinas, observando sua espada antes de decidir se você merece confiança. Um homem de rosto marcado, antigo mensageiro do rei, aponta para a colina: o castelo não abriu um portal, ele explica, foi o portal que abriu o castelo por dentro. Antes de seguir, você precisa descobrir em quem confiar e qual parte da história foi enterrada junto com os mortos.', $this->getImagemCena('vila'), 'normal', 'templo', [
                'pesquisar_aliados' => [
                    'titulo' => 'Buscar ajuda dos aliados',
                    'descricao' => 'Você fala com os sobreviventes, escuta histórias de perdas e percebe que, entre o medo, há pessoas que ainda acreditam na esperança do reino.',
                    'chanceSucesso' => 0.9,
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
                'seguir_sussurros' => [
                    'titulo' => 'Seguir os sussurros da casa queimada',
                    'descricao' => 'Você entra numa casa em ruínas para descobrir quem ainda está pedindo ajuda entre as paredes.',
                    'chanceSucesso' => 0.36,
                    'proximoSucesso' => 'templo',
                    'proximoFalha' => 'caverna',
                    'pontos' => 30,
                    'pontosFalha' => 3,
                    'energiaFalha' => 18,
                    'vidaFalha' => 26,
                    'mensagemSucesso' => 'Você encontra uma sobrevivente, que entrega um símbolo capaz de abrir o templo.',
                    'mensagemFalha' => 'Os sussurros eram uma armadilha. Você escapa por um túnel que termina na caverna.',
                ],
                'observar_torre' => [
                    'titulo' => 'Observar a torre antes de agir',
                    'descricao' => 'Você sobe no telhado de uma casa e procura sinais, bandeiras ou movimentos na colina do castelo.',
                    'chanceSucesso' => 0.86,
                    'proximoSucesso' => 'templo',
                    'proximoFalha' => 'mina',
                    'pontos' => 21,
                    'pontosFalha' => 3,
                    'energiaFalha' => 13,
                    'vidaFalha' => 16,
                    'mensagemSucesso' => 'Do alto, você identifica o caminho protegido até o templo e evita uma patrulha sombria.',
                    'mensagemFalha' => 'Uma sentinela o avista e você foge pelos fundos da vila, encontrando uma entrada para a mina.',
                ],
            ]),
            'templo' => new Cena('templo', 'Templo da Lua', 'O Templo da Lua permanece de pé apenas porque as colunas parecem sustentar umas às outras. A poeira cobre os degraus, mas não consegue esconder pegadas recentes que terminam diante da estátua central. Um espelho partido reflete a lua em dezenas de fragmentos, e cada reflexo mostra o santuário em uma época diferente: cheio de fiéis, coberto de sangue, vazio. Nas paredes, sacerdotes antigos registraram que o portal só poderia ser fechado por alguém capaz de carregar a luz sem confundi-la com poder. Quando você entra, os sinos subterrâneos começam a tocar.', $this->getImagemCena('templo'), 'desafio', 'castelo', [
                'invocar_luz' => [
                    'titulo' => 'Invocar a luz celestial',
                    'descricao' => 'Você levanta os braços e chama a luz antiga do santuário, sentindo uma energia sagrada percorrer seu corpo como um fogo frio e glorioso.',
                    'chanceSucesso' => 0.9,
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
                'seguir_inscricao' => [
                    'titulo' => 'Seguir a inscrição da lua',
                    'descricao' => 'Você decifra a sequência de símbolos no chão e pisa somente nas pedras marcadas pela luz do espelho.',
                    'chanceSucesso' => 0.88,
                    'proximoSucesso' => 'castelo',
                    'proximoFalha' => 'rio',
                    'pontos' => 25,
                    'pontosFalha' => 3,
                    'energiaFalha' => 15,
                    'vidaFalha' => 18,
                    'mensagemSucesso' => 'A inscrição acende uma passagem secreta que conduz ao castelo sem despertar os guardiões.',
                    'mensagemFalha' => 'Um símbolo se parte e o chão se abre sob seus pés, lançando você de volta às margens do rio.',
                ],
            ]),
            'rio' => new Cena('rio', 'Rio das Almas', 'O Rio das Almas corta as montanhas como uma ferida aberta. A água é escura, mas não por causa da profundidade: sob a superfície passam rostos, lanternas e cenas de pessoas que desapareceram durante a queda do reino. A corrente muda de direção sem aviso, como se obedecesse a uma vontade própria. Na margem oposta, a entrada da Caverna do Eco pulsa com uma luz pálida. Para alcançá-la, você terá de atravessar não apenas a água, mas as lembranças que o rio tenta devolver.', $this->getImagemCena('rio'), 'desafio', 'caverna', [
                'nadar_contra_corrente' => [
                    'titulo' => 'Nadar contra a corrente',
                    'descricao' => 'Você mergulha na correnteza e usa todas as forças para avançar contra a água, sentindo o peso do rio empurrando você para o fundo.',
                    'chanceSucesso' => 0.42,
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
                'seguir_luzes' => [
                    'titulo' => 'Seguir as lanternas flutuantes',
                    'descricao' => 'Você ignora as vozes do rio e acompanha pequenas luzes que avançam contra a correnteza.',
                    'chanceSucesso' => 0.86,
                    'proximoSucesso' => 'caverna',
                    'proximoFalha' => 'mina',
                    'pontos' => 23,
                    'pontosFalha' => 3,
                    'energiaFalha' => 14,
                    'vidaFalha' => 18,
                    'mensagemSucesso' => 'As lanternas pertencem a antigos barqueiros e formam uma passagem segura até a caverna.',
                    'mensagemFalha' => 'As luzes eram iscas. A corrente o arrasta para um túnel inundado da mina.',
                ],
            ]),
            'caverna' => new Cena('caverna', 'Caverna do Eco', 'A entrada se fecha atrás de você com um estalo profundo, e o som continua viajando pelas galerias muito depois de o silêncio voltar. Nas paredes da Caverna do Eco há inscrições feitas por exploradores de várias eras; algumas terminam no meio de uma frase, outras repetem a mesma palavra em línguas diferentes. O ar cheira a pedra molhada e ferro. Mais adiante, um guardião coberto por placas de basalto protege uma porta sem maçaneta. Ele não parece guardar um tesouro, mas uma decisão que alguém tentou esquecer.', $this->getImagemCena('caverna'), 'desafio', 'portal', [
                'resolver_enigma' => [
                    'titulo' => 'Resolver o enigma ancestral',
                    'descricao' => 'Você observa os símbolos antigos gravados na pedra, entende que não são apenas desenhos, mas uma linguagem esquecida, e tenta despertar a porta da caverna.',
                    'chanceSucesso' => 0.9,
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
                'escutar_ecos' => [
                    'titulo' => 'Escutar a direção dos ecos',
                    'descricao' => 'Você apaga a lanterna e usa o retorno dos sons para localizar a passagem que respira atrás da parede.',
                    'chanceSucesso' => 0.86,
                    'proximoSucesso' => 'portal',
                    'proximoFalha' => 'mina',
                    'pontos' => 24,
                    'pontosFalha' => 3,
                    'energiaFalha' => 15,
                    'vidaFalha' => 20,
                    'mensagemSucesso' => 'Os ecos formam um mapa e revelam uma saída escondida diretamente para o portal.',
                    'mensagemFalha' => 'O silêncio engana seus sentidos, e você acaba em um túnel antigo que leva à mina.',
                ],
            ]),
            'mina' => new Cena('mina', 'Mina da Escuridão', 'A Mina da Escuridão foi abandonada às pressas. Picaretas continuam fincadas nas paredes, carrinhos permanecem carregados de minério e uma fileira de capacetes enferrujados marca o ponto onde os trabalhadores pararam de fugir. O minério nas rochas pulsa com uma luz vermelha, quente o bastante para aquecer as mãos e fria o bastante para causar arrepios. Em túneis laterais, você encontra mapas rasgados que mostram a mina sob o castelo e uma passagem desenhada em direção ao portal. Algo respira no fundo, no ritmo lento das pedras cedendo.', $this->getImagemCena('mina'), 'normal', 'portal', [
                'pegar_arma_antiga' => [
                    'titulo' => 'Pegar a arma antiga',
                    'descricao' => 'Você pega uma espada enferrujada entre as rochas, sentindo o peso do passado em cada golpe que a arma parece ter recebido antes de você.',
                    'chanceSucesso' => 0.43,
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
                    'chanceSucesso' => 0.88,
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
                'ler_mapa' => [
                    'titulo' => 'Estudar o mapa dos mineiros',
                    'descricao' => 'Você compara os túneis desenhados com as marcas nas paredes antes de escolher a rota iluminada.',
                    'chanceSucesso' => 0.86,
                    'proximoSucesso' => 'portal',
                    'proximoFalha' => 'rio',
                    'pontos' => 25,
                    'pontosFalha' => 3,
                    'energiaFalha' => 14,
                    'vidaFalha' => 18,
                    'mensagemSucesso' => 'O mapa revela o caminho mais curto e você chega ao portal sem despertar as criaturas da mina.',
                    'mensagemFalha' => 'O mapa foi alterado por alguém. O túnel termina no rio, longe da passagem que você procurava.',
                ],
            ]),
            'castelo' => new Cena('castelo', 'Castelo de Gelo', 'O Castelo de Gelo surge entre nuvens baixas, preso à montanha por correntes de gelo que parecem raízes. Nenhuma bandeira se move nas torres, mas há silhuetas atrás das janelas, imóveis como retratos. O frio não vem apenas do vento: ele escapa pelas pedras e congela pensamentos, nomes e lembranças. No alto da torre central está a Chave da Aurora, último artefato capaz de enfrentar o rei das trevas. Para alcançá-la, você deverá atravessar salões onde o castelo conserva ecos de seus antigos moradores.', $this->getImagemCena('castelo'), 'normal', 'portal', [
                'subir_torre' => [
                    'titulo' => 'Subir até a torre',
                    'descricao' => 'Você avança pelas escadas geladas em busca da chave, sentindo a temperatura cair com cada degrau e ouvindo o eco dos seus próprios passos como se a torre estivesse te avaliando.',
                    'chanceSucesso' => 0.9,
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
                'seguir_estandartes' => [
                    'titulo' => 'Seguir os estandartes congelados',
                    'descricao' => 'Você observa as bandeiras presas no gelo e segue a antiga rota usada pelos guardas reais.',
                    'chanceSucesso' => 0.85,
                    'proximoSucesso' => 'portal',
                    'proximoFalha' => 'caverna',
                    'pontos' => 24,
                    'pontosFalha' => 3,
                    'energiaFalha' => 16,
                    'vidaFalha' => 20,
                    'mensagemSucesso' => 'Os estandartes indicam uma passagem de serviço e você alcança a chave sem atravessar o salão principal.',
                    'mensagemFalha' => 'O gelo quebra sob seus pés, e você cai nas galerias profundas que ligam o castelo à caverna.',
                ],
            ]),
            'portal' => new Cena('portal', 'Portal do Rei', 'No centro das ruínas da capital, o Portal do Rei rasga o céu como uma ferida vertical. De um lado, você vê o salão do trono; do outro, um vazio cheio de estrelas mortas. A luz vermelha pulsa no mesmo ritmo do seu coração, e cada pulso faz as pedras do reino se lembrarem de quando ainda havia música nas praças. O rei das trevas espera no limiar, não como uma criatura distante, mas como alguém que conhece seu nome, sua história e o motivo secreto que o trouxe até ali. Atrás dele, o portal começa a fechar-se sobre a última esperança do reino.', $this->getImagemCena('portal'), 'desafio', 'vitoria', [
                'enfrentar_rei' => [
                    'titulo' => 'Enfrentar o rei das trevas',
                    'descricao' => 'Você entra na batalha final com o coração em chamas, pronto para decidir o destino do reino e o futuro das pessoas que ainda confiam em você.',
                    'chanceSucesso' => 0.44,
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
                    'chanceSucesso' => 0.9,
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
                'aceitar_pacto' => [
                    'titulo' => 'Aceitar o pacto do rei das trevas',
                    'descricao' => 'O rei oferece poder suficiente para salvar o reino, mas exige que você entregue sua própria vontade em troca.',
                    'fatal' => true,
                    'mensagemFatal' => 'O pacto consome sua alma. O portal permanece aberto e sua jornada termina como parte da escuridão.',
                ],
                'usar_chave' => [
                    'titulo' => 'Usar a Chave da Aurora',
                    'descricao' => 'Você ergue a chave conquistada e procura a fechadura escondida no coração do portal.',
                    'chanceSucesso' => 0.92,
                    'proximoSucesso' => 'vitoria',
                    'proximoFalha' => 'derrota',
                    'pontos' => 38,
                    'pontosFalha' => 4,
                    'energiaFalha' => 18,
                    'vidaFalha' => 30,
                    'mensagemSucesso' => 'A Chave da Aurora encontra a fechadura e transforma o portal em uma chuva de luz sobre o reino.',
                    'mensagemFalha' => 'A chave racha antes de completar o selo, e o portal devolve uma onda de escuridão sobre a capital.',
                ],
            ]),
            'vitoria' => new Cena('vitoria', 'Vitória', 'Quando o portal se fecha, o silêncio dura tempo suficiente para que você ouça a primeira gota de chuva cair sobre a praça. Depois, a luz retorna em ondas: as janelas se abrem, os sinos respondem uns aos outros e as pessoas saem das casas carregando os nomes de quem perderam. O reino não volta a ser o que era, mas agora pode escolher o que será. A Chave da Aurora se desfaz em suas mãos, transformando-se em pequenas faíscas que pousam sobre os campos. Sua história será contada, não como a de alguém que nunca teve medo, mas como a de quem continuou caminhando enquanto o medo apontava o caminho contrário.', $this->getImagemCena('vitoria'), 'final', null),
            'derrota' => new Cena('derrota', 'Derrota', 'A escuridão não chega como uma explosão, mas como uma maré paciente. Primeiro, as últimas luzes da capital se apagam; depois, o frio alcança as vilas e o céu perde a cor. O portal permanece aberto, respirando sobre as ruínas como uma boca que nunca se sacia. Em algum lugar, alguém ainda contará que um herói tentou atravessar a noite. Talvez a história seja lembrada como um aviso, talvez como uma promessa: enquanto alguém se lembrar do caminho até a Capela da Aurora, o reino ainda não estará completamente perdido.', $this->getImagemCena('derrota'), 'final', null),
        ];
    }
}
