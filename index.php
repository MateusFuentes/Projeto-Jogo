<?php
session_start();

require_once __DIR__ . '/src/Game.php';

$heroSelecionado = $_SESSION['hero'] ?? 'guerreiro';
$nomePersonagem = trim((string) ($_SESSION['player_name'] ?? 'Aragor'));
$loginAtivo = !empty($_SESSION['player_name']) && !empty($_SESSION['hero']);
$estadoPadrao = [
    'personagem' => [
        'nome' => $nomePersonagem,
        'vida' => 100,
        'energia' => 30,
        'pontos' => 0,
    ],
    'cenaAtual' => 'inicio',
];

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
        $estadoAtual = $_SESSION['game'] ?? $estadoPadrao;
        $game = new Game($estadoAtual);
        $resultado = $game->processarEscolha($_POST['opcao'] ?? '');
        $_SESSION['ultimoResultado'] = $resultado;
        $_SESSION['game'] = $game->toArray();
    }

}

if (!isset($_SESSION['game'])) {
    $_SESSION['game'] = (new Game($estadoPadrao))->toArray();
}

$game = new Game($_SESSION['game']);
$cena = $game->getCenaAtual();
$imagemCena = $cena->getImagem();
$pastaDoJogo = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$pastaDoJogo = $pastaDoJogo === '.' ? '' : rtrim($pastaDoJogo, '/');
$imagemFundo = $pastaDoJogo . '/images/' . $imagemCena;
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
    <title>As Cinzas da Aurora</title>
    <style>
        :root {
            --paper: #f7f0df;
            --muted: #b8b5ad;
            --ink: #11161d;
            --deep: #0b1017;
            --panel: rgba(20, 27, 36, 0.93);
            --line: rgba(236, 197, 116, 0.34);
            --gold: #e6b85c;
            --gold-light: #ffe09a;
            --cyan: #8fc7d2;
            --danger: #f29b88;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 8% 4%, rgba(230, 184, 92, 0.17), transparent 27rem),
                radial-gradient(circle at 92% 88%, rgba(89, 158, 175, 0.15), transparent 30rem),
                #080c12;
            color: var(--paper);
        }
        .container {
            max-width: 1220px;
            margin: 0 auto;
            padding: 42px 22px 68px;
        }
        .painel {
            position: relative;
            overflow: hidden;
            background: linear-gradient(145deg, rgba(27, 35, 46, 0.95), var(--panel));
            border: 1px solid var(--line);
            border-radius: 26px;
            padding: clamp(24px, 5vw, 58px);
            box-shadow: 0 32px 90px rgba(0, 0, 0, 0.52), inset 0 1px rgba(255, 255, 255, 0.08);
        }
        .painel::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0.28;
            background-image: linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 38px 38px;
            mask-image: linear-gradient(to bottom, black, transparent 70%);
        }
        .cabecalho {
            position: relative;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 32px;
        }
        .titulo {
            max-width: 720px;
            font-family: Georgia, serif;
            font-size: clamp(2.6rem, 6vw, 5rem);
            font-weight: 400;
            letter-spacing: 0.015em;
            line-height: 0.94;
            margin: 0;
            text-shadow: 0 10px 28px rgba(0, 0, 0, 0.42);
        }
        .cabecalho::after {
            content: "UMA AVENTURA DE ESCOLHAS E CONSEQUÊNCIAS";
            align-self: flex-end;
            max-width: 190px;
            color: var(--gold-light);
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.2em;
            line-height: 1.7;
            text-align: right;
        }
        .abas {
            position: relative;
            display: flex;
            gap: 28px;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .aba {
            padding: 0 2px 14px;
            color: #788390;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }
        .aba.ativa {
            color: var(--gold-light);
            border-bottom: 2px solid var(--gold-light);
        }
        .stats {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 18px 0 24px;
        }
        .badge {
            background: rgba(255, 255, 255, 0.055);
            border: 1px solid rgba(255, 255, 255, 0.13);
            border-radius: 10px;
            padding: 11px 15px;
            color: #d5d1c6;
            font-size: 0.86rem;
            font-weight: 600;
        }
        .conteudo {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(260px, 0.75fr);
            gap: 28px;
            margin-top: 20px;
        }
        .imagem {
            position: relative;
            min-height: 390px;
            border-radius: 18px;
            background-size: cover;
            background-position: center;
            border: 1px solid rgba(255, 224, 154, 0.38);
            box-shadow: 0 20px 38px rgba(0, 0, 0, 0.32);
        }
        .imagem::after {
            content: "CENA ATUAL";
            position: absolute;
            left: 18px;
            bottom: 16px;
            padding: 6px 9px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 5px;
            background: rgba(4, 8, 12, 0.62);
            color: var(--gold-light);
            font-size: 0.64rem;
            font-weight: 700;
            letter-spacing: 0.16em;
        }
        h2, h3 { font-family: Georgia, serif; font-weight: 400; }
        h2 { margin: 25px 0 8px; font-size: clamp(1.8rem, 3vw, 2.7rem); }
        .texto {
            max-width: 760px;
            margin-top: 0;
            color: #c7c5bf;
            line-height: 1.8;
            font-size: 1.04rem;
        }
        .opcoes {
            display: grid;
            gap: 12px;
            margin-top: 25px;
        }
        .opcao {
            background: rgba(7, 12, 18, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 11px;
            padding: 16px;
            transition: border-color 160ms ease, background 160ms ease, transform 160ms ease;
        }
        .opcao:has(input:checked), .opcao:hover {
            background: rgba(65, 73, 78, 0.45);
            border-color: var(--gold);
            transform: translateX(3px);
        }
        .opcao label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
        }
        .opcao input {
            accent-color: var(--gold);
            margin-top: 5px;
        }
        .opcao strong {
            display: block;
            margin-bottom: 5px;
            color: var(--paper);
        }
        .resultado {
            margin-top: 22px;
            background: rgba(36, 107, 88, 0.2);
            border: 1px solid rgba(111, 211, 164, 0.46);
            color: #d7f5e5;
            padding: 16px;
            border-radius: 11px;
        }
        .panel-login {
            position: relative;
            display: grid;
            gap: 24px;
            padding: clamp(22px, 4vw, 38px);
            border: 1px solid var(--line);
            border-radius: 18px;
            background: linear-gradient(115deg, rgba(35, 46, 57, 0.9), rgba(15, 21, 29, 0.86));
            box-shadow: 0 24px 45px rgba(0, 0, 0, 0.2);
        }
        .panel-login::before {
            content: "";
            position: absolute;
            right: 0;
            top: 0;
            width: 42%;
            height: 100%;
            pointer-events: none;
            background: linear-gradient(90deg, rgba(15, 21, 29, 0.9), transparent), url('<?= htmlspecialchars($pastaDoJogo . '/images/portal.jpg', ENT_QUOTES, 'UTF-8') ?>') center / cover;
            opacity: 0.27;
        }
        .panel-login > * { position: relative; }
        .panel-login > button { justify-self: start; }
        .panel-login > div:nth-of-type(2) > label {
            color: var(--gold-light);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .grid-heroes {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-top: 10px;
        }
        .hero-card {
            position: relative;
            min-height: 176px;
            overflow: hidden;
            display: block;
            background-position: center;
            background-size: cover;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 13px;
            padding: 16px;
            color: #ddd9ce;
            cursor: pointer;
            transition: transform 180ms ease, border-color 180ms ease, filter 180ms ease;
        }
        .hero-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(5, 8, 12, 0.12), rgba(5, 8, 12, 0.9));
        }
        .hero-card:hover, .hero-card:has(input:checked) {
            border-color: var(--gold-light);
            filter: saturate(1.15);
            transform: translateY(-5px);
        }
        .hero-card > * { position: relative; }
        .hero-card input {
            position: absolute;
            opacity: 0;
        }
        .hero-icon {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 224, 154, 0.58);
            border-radius: 50%;
            background: rgba(8, 12, 17, 0.58);
            color: var(--gold-light);
            font-size: 1.25rem;
        }
        .hero-card strong {
            display: block;
            margin-bottom: 6px;
            color: #fff7e4;
            font-family: Georgia, serif;
            font-size: 1.25rem;
            font-weight: 400;
        }
        .campo {
            display: grid;
            gap: 8px;
            max-width: 460px;
            color: #d2cec4;
            font-size: 0.86rem;
            font-weight: 600;
        }
        .campo input, .campo select {
            background: rgba(5, 9, 14, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 8px;
            padding: 14px;
            color: var(--paper);
            font: inherit;
            outline: none;
        }
        .campo input:focus {
            border-color: var(--gold-light);
            box-shadow: 0 0 0 3px rgba(230, 184, 92, 0.14);
        }
        .acao {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        button {
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            color: var(--ink);
            font-weight: bold;
            border: none;
            padding: 14px 22px;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 10px 22px rgba(230, 184, 92, 0.2);
            transition: transform 180ms ease, filter 180ms ease;
        }
        button:hover {
            filter: brightness(1.08);
            transform: translateY(-2px);
        }
        .secundario {
            background: transparent;
            color: var(--paper);
            border: 1px solid var(--line);
        }
        .placar {
            align-self: start;
            margin-top: 0;
            padding: 22px;
            border-radius: 16px;
            background: rgba(8, 13, 19, 0.62);
            border: 1px solid rgba(255, 255, 255, 0.13);
        }
        .placar h3 {
            margin-top: 0;
            color: var(--gold-light);
            font-size: 1.45rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            padding: 13px 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: left;
        }
        th { color: var(--cyan); font-size: 0.72rem; letter-spacing: 0.12em; text-transform: uppercase; }
        small { color: var(--danger); }
        @media (max-width: 780px) {
            .container { padding: 22px 12px 42px; }
            .conteudo {
                grid-template-columns: 1fr;
            }
            .cabecalho {
                display: block;
            }
            .cabecalho::after {
                display: block;
                margin-top: 16px;
                text-align: left;
            }
            .grid-heroes { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .imagem { min-height: 280px; }
        }
        @media (max-width: 440px) {
            .grid-heroes { grid-template-columns: 1fr; }
            .hero-card { min-height: 140px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="painel">
            <div class="cabecalho">
                <h1 class="titulo">As Cinzas da Aurora</h1>
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
                            <label class="hero-card" style="background-image: url('<?= htmlspecialchars($pastaDoJogo . '/images/guerreiro.jpg', ENT_QUOTES, 'UTF-8') ?>');">
                                <input type="radio" name="heroi" value="guerreiro" checked>
                                <span class="hero-icon" aria-hidden="true">⚔</span>
                                <strong>Guerreiro</strong><br>
                                Mais vida e força bruta
                            </label>
                            <label class="hero-card" style="background-image: url('<?= htmlspecialchars($pastaDoJogo . '/images/arqueiro.jpg', ENT_QUOTES, 'UTF-8') ?>');">
                                <input type="radio" name="heroi" value="arqueiro">
                                <span class="hero-icon" aria-hidden="true">➶</span>
                                <strong>Arqueiro</strong><br>
                                Agilidade e precisão
                            </label>
                            <label class="hero-card" style="background-image: url('<?= htmlspecialchars($pastaDoJogo . '/images/mago.jpg', ENT_QUOTES, 'UTF-8') ?>');">
                                <input type="radio" name="heroi" value="mago">
                                <span class="hero-icon" aria-hidden="true">✦</span>
                                <strong>Mago</strong><br>
                                Energia e magia intensa
                            </label>
                            <label class="hero-card" style="background-image: url('<?= htmlspecialchars($pastaDoJogo . '/images/ladino.jpg', ENT_QUOTES, 'UTF-8') ?>');">
                                <input type="radio" name="heroi" value="ladino">
                                <span class="hero-icon" aria-hidden="true">◈</span>
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
                             style="background-image: linear-gradient(rgba(32,22,15,0.38), rgba(32,22,15,0.55)), url('<?= htmlspecialchars($imagemFundo, ENT_QUOTES, 'UTF-8') ?>');">
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
                                                <?php if (!empty($opcao['fatal'])): ?>
                                                    <br><small>Escolha fatal: encerra a aventura imediatamente.</small>
                                                <?php endif; ?>
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
                                <?php if (isset($ultimoResultado['chanceSucesso'])): ?>
                                    <br><small>Chance de sucesso: <?= (int) round($ultimoResultado['chanceSucesso'] * 100) ?>% | risco de falha: <?= (int) round($ultimoResultado['chanceDerrota'] * 100) ?>%</small>
                                <?php endif; ?>
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