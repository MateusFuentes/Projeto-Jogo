<?php

class Desafio
{
    private string $nome;
    private int $dificuldade;
    private Dado $dado;

    public function __construct(string $nome, int $dificuldade, ?Dado $dado = null)
    {
        $this->nome = $nome;
        $this->dificuldade = $dificuldade;
        $this->dado = $dado ?? new Dado();
    }

    public function resolver(Personagem $personagem): array
    {
        $valorDado = $this->dado->rolar(20);
        $bonus = (int) floor($personagem->getEnergia() / 10);
        $total = $valorDado + $bonus;
        $sucesso = $total >= $this->dificuldade;

        if ($sucesso) {
            $personagem->ganharPontos(25);
            $personagem->ganharEnergia(10);
            $personagem->recuperarVida(10);
            $mensagem = 'Você venceu o desafio com coragem e ousadia.';
        } else {
            $personagem->perderVida(18);
            $personagem->gastarEnergia(8);
            $personagem->ganharPontos(5);
            $mensagem = 'Você foi pressionado, mas ainda consegue seguir em frente.';
        }

        return [
            'nome' => $this->nome,
            'dado' => $valorDado,
            'bonus' => $bonus,
            'total' => $total,
            'sucesso' => $sucesso,
            'mensagem' => $mensagem,
        ];
    }
}
