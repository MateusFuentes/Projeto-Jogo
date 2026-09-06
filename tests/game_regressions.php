<?php
require_once __DIR__ . '/../src/Game.php';

$game = new Game([
    'personagem' => [
        'nome' => 'Aragor',
        'vida' => 30,
        'energia' => 8,
        'pontos' => 0,
    ],
    'cenaAtual' => 'inicio',
]);

$ref = new ReflectionClass($game);
$ajustar = $ref->getMethod('ajustarChanceSucesso');
$ajustar->setAccessible(true);
$chance = $ajustar->invoke($game, 0.8, 'inicio');

if ($chance >= 0.6) {
    throw new RuntimeException('A chance de sucesso ainda está acima do limite esperado para personagem debilitado.');
}

$chanceMinima = $ajustar->invoke($game, 0.1, 'inicio');
if ($chanceMinima < 0.50) {
    throw new RuntimeException('A chance mínima de sucesso está baixa demais.');
}

$falhaComVidaRestanteConfirmada = false;
for ($tentativa = 0; $tentativa < 100; $tentativa++) {
    $gameComPoucaVida = new Game([
        'personagem' => [
            'nome' => 'Aragor',
            'vida' => 10,
            'energia' => 30,
            'pontos' => 0,
        ],
        'cenaAtual' => 'bosque',
    ]);

    $resultado = $gameComPoucaVida->processarEscolha('atacar_lobo');
    if (!$resultado['sucesso']) {
        if ($gameComPoucaVida->getPersonagem()->getVida() < 1) {
            throw new RuntimeException('Uma falha comum não pode zerar toda a vida do personagem.');
        }

        $falhaComVidaRestanteConfirmada = true;
        break;
    }
}

if (!$falhaComVidaRestanteConfirmada) {
    throw new RuntimeException('O teste não conseguiu produzir uma falha comum.');
}

$resolver = $ref->getMethod('resolverDestino');
$resolver->setAccessible(true);
$destino = $resolver->invoke($game, ['proximoSucesso' => 'bosque', 'proximoFalha' => 'bosque'], false);

if ($destino !== 'inicio') {
    throw new RuntimeException('Quando sucesso e falha levam ao mesmo próximo cenário, o jogo não deve pular para outra cena.');
}

$retornoAutomaticoConfirmado = false;
for ($tentativa = 0; $tentativa < 100; $tentativa++) {
    $gameComHistorico = new Game([
        'personagem' => [
            'nome' => 'Aragor',
            'vida' => 80,
            'energia' => 30,
            'pontos' => 0,
        ],
        'cenaAtual' => 'bosque',
        'historico' => ['inicio'],
    ]);

    $resultado = $gameComHistorico->processarEscolha('atacar_lobo');
    if (!$resultado['sucesso'] && $gameComHistorico->getCenaAtualId() === 'inicio') {
        $retornoAutomaticoConfirmado = true;
        break;
    }
}

if (!$retornoAutomaticoConfirmado) {
    throw new RuntimeException('Uma escolha errada deveria retornar automaticamente à cena anterior.');
}

$falhaPermaneceNaCenaConfirmada = false;
for ($tentativa = 0; $tentativa < 100; $tentativa++) {
    $gameSemRetorno = new Game([
        'personagem' => [
            'nome' => 'Aragor',
            'vida' => 80,
            'energia' => 30,
            'pontos' => 0,
        ],
        'cenaAtual' => 'bosque',
        'historico' => ['inicio'],
    ]);

    $resultado = $gameSemRetorno->processarEscolha('atacar_lobo');
    if (!$resultado['sucesso'] && $gameSemRetorno->getCenaAtualId() === 'bosque') {
        $falhaPermaneceNaCenaConfirmada = true;
        break;
    }
}

if (!$falhaPermaneceNaCenaConfirmada) {
    throw new RuntimeException('A maioria das falhas deveria manter o herói na cena atual.');
}

$falhaNaPrimeiraCenaConfirmada = false;
for ($tentativa = 0; $tentativa < 100; $tentativa++) {
    $gameNaPrimeiraCena = new Game([
        'personagem' => [
            'nome' => 'Aragor',
            'vida' => 80,
            'energia' => 30,
            'pontos' => 0,
        ],
        'cenaAtual' => 'inicio',
    ]);

    $resultado = $gameNaPrimeiraCena->processarEscolha('examinar_ruinas');
    if (!$resultado['sucesso']) {
        if ($gameNaPrimeiraCena->getCenaAtualId() !== 'inicio') {
            throw new RuntimeException('A primeira cena não pode retornar para outro cenário.');
        }

        $falhaNaPrimeiraCenaConfirmada = true;
        break;
    }
}

if (!$falhaNaPrimeiraCenaConfirmada) {
    throw new RuntimeException('O teste não conseguiu produzir uma falha na primeira cena.');
}

$opcoesBosque = $game->getCenas()['bosque']->getOpcoes();
if (empty($opcoesBosque['beber_agua_negra']['fatal'])) {
    throw new RuntimeException('A cena do bosque deveria conter uma escolha fatal.');
}

$idsDasCenas = array_keys($game->getCenas());
foreach ($game->getCenas() as $cena) {
    foreach ($cena->getOpcoes() as $opcao) {
        foreach (['proximo', 'proximoSucesso', 'proximoFalha'] as $chave) {
            if (isset($opcao[$chave]) && !in_array($opcao[$chave], $idsDasCenas, true)) {
                throw new RuntimeException('Uma escolha aponta para uma cena inexistente.');
            }
        }
    }
}

print "OK\n";
