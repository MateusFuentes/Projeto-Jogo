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

$resolver = $ref->getMethod('resolverDestino');
$resolver->setAccessible(true);
$destino = $resolver->invoke($game, ['proximoSucesso' => 'bosque', 'proximoFalha' => 'bosque'], false);

if ($destino !== 'inicio') {
    throw new RuntimeException('Quando sucesso e falha levam ao mesmo próximo cenário, o jogo não deve pular para outra cena.');
}

print "OK\n";
