<?php
session_start();

require_once __DIR__ . '/src/Game.php';

$heroSelecionado = $_SESSION['hero'] ?? 'guerreiro';
$nomePersonagem = trim((string) ($_SESSION['player_name'] ?? 'Aragor'));
$loginAtivo = !empty($_SESSION['player_name']) && !empty($_SESSION['hero']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'reiniciar') {
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if ($acao === 'login') {
        $heroSelecionado = strtolower(trim((string) ($_POST['heroi'] ?? 'guerreiro')));
        $nomePersonagem = trim((string) ($_POST['nome'] ?? 'Aragor'));

        if ($nomePersonagem === '') {
            $nomePersonagem = 'Aragor';
        }

        $perfil = [
            'guerreiro' => ['vida' => 110, 'energia' => 25, 'pontos' => 0],
            'arqueiro'  => ['vida' => 90,  'energia' => 35, 'pontos' => 5],
            'mago'      => ['vida' => 80,  'energia' => 45, 'pontos' => 10],
            'ladino'    => ['vida' => 95,  'energia' => 32, 'pontos' => 15],
        ];

        $dadosHeroi = $perfil[$heroSelecionado] ?? $perfil['guerreiro'];
        $_SESSION['hero'] = $heroSelecionado;
        $_SESSION['player_name'] = $nomePersonagem;
        $_SESSION['player_ready'] = true;
        $_SESSION['game'] = [
            'personagem' => [
                'nome'    => $nomePersonagem,
                'vida'    => $dadosHeroi['vida'],
                'energia' => $dadosHeroi['energia'],
                'pontos'  => $dadosHeroi['pontos'],
            ],
            'cenaAtual' => 'inicio',
        ];

        $loginAtivo = true;
    }

    if ($acao === 'escolher') {
        $estadoAtual = $_SESSION['game'] ?? [
            'personagem' => [
                'nome' => $nomePersonagem,
                'vida' => 100,
                'energia' => 30,
                'pontos' => 0
            ],
            'cenaAtual' => 'inicio'
        ];
        $game = new Game($estadoAtual);
        $resultado = $game->processarEscolha($_POST['opcao'] ?? '');
        $_SESSION['ultimoResultado'] = $resultado;
        $_SESSION['game'] = $game->toArray();
    }
}

if (!$loginAtivo) {
    $_SESSION['game'] = $_SESSION['game'] ?? (new Game())->toArray();
}

$estadoAtual = $_SESSION['game'] ?? [
    'personagem' => [
        'nome' => $nomePersonagem,
        'vida' => 100,
        'energia' => 30,
        'pontos' => 0
    ],
    'cenaAtual' => 'inicio'
];

$game = new Game($estadoAtual);
$cena = $game->getCenaAtual();
$placar = (new Database())->lerTodos();
$ultimasVitorias = array_slice($placar, 0, 5);
$ultimoResultado = $_SESSION['ultimoResultado'] ?? null;
unset($_SESSION['ultimoResultado']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPG Web — Aventura em PHP</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1d120d, #3b2418, #120d0a);
            color: #f3ead9;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px 52px;
        }
        .painel {
            background: rgba(40, 28, 19, 0.9);
            border: 1px solid rgba(191, 146, 86, 0.8);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.45);
        }
        .cabecalho {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 18px;
        }
        .titulo {
            font-size: 2rem;
            margin: 0;
        }
        .abas {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
        }
        .aba {
            background: #2b1d12;
            color: #f3ead9;
            border: 1px solid #b77d3c;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: bold;
        }
        .aba.ativa {
            background: #c9893a;
            color: #1a120f;
        }
        .stats {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 18px 0;
        }
        .badge {
            background: #2f1f17;
            border: 1px solid #b77d3c;
            padding: 10px 16px;
            border-radius: 999px;
            font-weight: bold;
        }
        .conteudo {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .imagem {
            min-height: 320px;
            border-radius: 16px;
            background-size: cover;
            background-position: center;
            background-color: #3c2b1d;
            border: 1px solid #b88d52;
        }
        .texto {
            line-height: 1.7;
            font-size: 1rem;
        }
        .opcoes {
            display: grid;
            gap: 12px;
            margin-top: 20px;
        }
        .opcao {
            background: rgba(25, 18, 13, 0.96);
            border: 1px solid #a8723d;
            border-radius: 12px;
            padding: 14px;
        }
        .opcao label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
        }
        .opcao input {
            margin-top: 4px;
        }
        .opcao strong {
            display: block;
            margin-bottom: 5px;
        }
        .resultado {
            margin-top: 20px;
            background: rgba(22, 163, 74, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.5);
            color: #dcfce7;
            padding: 12px 14px;
            border-radius: 10px;
        }
        .panel-login {
            display: grid;
            gap: 18px;
            padding: 18px;
            border: 1px solid #b77d3c;
            border-radius: 16px;
            background: rgba(31, 22, 17, 0.82);
        }
        .grid-heroes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }
        .hero-card {
            background: rgba(18, 12, 9, 0.9);
            border: 1px solid #8e6236;
            border-radius: 12px;
            padding: 12px;
        }
        .hero-card input {
            margin-right: 8px;
        }
        .campo {
            display: grid;
            gap: 8px;
        }
        .campo input, .campo select {
            background: #120d0a;
            border: 1px solid #b77d3c;
            border-radius: 8px;
            padding: 10px 12px;
            color: #f3ead9;
        }
        .acao {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        button {
            background: #c9893a;
            color: #1a120f;
            font-weight: bold;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
        }
        .secundario {
            background: #2b1d12;
            color: #f3ead9;
            border: 1px solid #b77d3c;
        }
        .placar {
            margin-top: 28px;
            padding: 18px;
            border-radius: 16px;
            background: rgba(25, 18, 13, 0.92);
            border: 1px solid rgba(191, 146, 86, 0.7);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #334155;
            text-align: left;
        }
        @media (max-width: 780px) {
            .conteudo {
                grid-template-columns: 1fr;
            }
            .cabecalho {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="painel">
            <div class="cabecalho">
                <h1 class="titulo">RPG Web — Aventura em PHP</h1>
            </div>

            <div class="abas">
                <div class="aba <?= !$loginAtivo ? 'ativa' : '' ?>">Login</div>
                <div class="aba <?= $loginAtivo ? 'ativa' : '' ?>">Jogo</div>
            </div>

            <?php if (!$loginAtivo): ?>
                <form method="post" class="panel-login">
                    <input type="hidden" name="acao" value="login">

                    <div class="campo">
                        <label for="nome">Nome do herói</label>
                        <input id="nome" name="nome" type="text" value="Aragor" maxlength="30" placeholder="Digite seu nome" required>
                    </div>

                    <div>
                        <label>Escolha seu herói</label>
                        <div class="grid-heroes">
                            <label class="hero-card">
                                <input type="radio" name="heroi" value="guerreiro" checked>
                                <strong>Guerreiro</strong><br>
                                Mais vida e força bruta
                            </label>
                            <label class="hero-card">
                                <input type="radio" name="heroi" value="arqueiro">
                                <strong>Arqueiro</strong><br>
                                Agilidade e precisão
                            </label>
                            <label class="hero-card">
                                <input type="radio" name="heroi" value="mago">
                                <strong>Mago</strong><br>
                                Energia e magia intensa
                            </label>
                            <label class="hero-card">
                                <input type="radio" name="heroi" value="ladino">
                                <strong>Ladino</strong><br>
                                Sorte e furtividade
                            </label>
                        </div>
                    </div>

                    <button type="submit">Entrar na jornada</button>
                </form>
            <?php else: ?>
                <div class="stats">
                    <div class="badge">Herói: <?= htmlspecialchars($game->getPersonagem()->getNome()) ?> (<?= ucfirst(htmlspecialchars($heroSelecionado)) ?>)</div>
                    <div class="badge">Vida: <?= $game->getPersonagem()->getVida() ?></div>
                    <div class="badge">Energia: <?= $game->getPersonagem()->getEnergia() ?></div>
                    <div class="badge">Pontos: <?= $game->getPersonagem()->getPontos() ?></div>
                    <div class="badge">Cena: <?= htmlspecialchars($cena->getTitulo()) ?></div>
                </div>

                <div class="conteudo">
                    <div>
                        <div class="imagem"
                             title="<?= htmlspecialchars($cena->getTitulo()) ?>"
                             style="background-image: linear-gradient(rgba(32,22,15,0.38), rgba(32,22,15,0.55)), url('images/<?= htmlspecialchars($cena->getImagem()) ?>');">
                        </div>

                        <h2><?= htmlspecialchars($cena->getTitulo()) ?></h2>
                        <p class="texto"><?= htmlspecialchars($cena->getDescricao()) ?></p>

                        <?php if (!in_array($game->getCenaAtualId(), ['vitoria', 'derrota'], true)): ?>
                            <form method="post" class="opcoes">
                                <input type="hidden" name="acao" value="escolher">
                                <?php foreach ($cena->getOpcoes() as $id => $opcao): ?>
                                    <div class="opcao">
                                        <label>
                                            <input type="radio" name="opcao" value="<?= htmlspecialchars($id) ?>" required>
                                            <span>
                                                <strong><?= htmlspecialchars($opcao['titulo']) ?></strong>
                                                <?= htmlspecialchars($opcao['descricao']) ?>
                                            </span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <div class="acao">
                                    <button type="submit">Escolher ação</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <form method="post" class="acao">
                                <input type="hidden" name="acao" value="reiniciar">
                                <button type="submit" class="secundario">Reiniciar aventura</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($ultimoResultado): ?>
                            <div class="resultado">
                                <strong><?= htmlspecialchars($ultimoResultado['opcao'] ?? 'Ação') ?></strong><br>
                                <?= htmlspecialchars($ultimoResultado['mensagem']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="placar">
                        <h3>Melhores pontuações</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Jogador</th>
                                    <th>Pontos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ultimasVitorias)): ?>
                                    <tr><td colspan="2">Nenhuma pontuação salva ainda.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($ultimasVitorias as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['nome']) ?></td>
                                            <td><?= (int) $item['pontos'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>