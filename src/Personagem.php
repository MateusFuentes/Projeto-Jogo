<?php

class Personagem
{
    private string $nome;
    private int $vida;
    private int $energia;
    private int $pontos;

    public function __construct(string $nome, int $vida = 100, int $energia = 30, int $pontos = 0)
    {
        $this->nome = $nome;
        $this->vida = $vida;
        $this->energia = $energia;
        $this->pontos = $pontos;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getVida(): int
    {
        return $this->vida;
    }

    public function getEnergia(): int
    {
        return $this->energia;
    }

    public function getPontos(): int
    {
        return $this->pontos;
    }

    public function ganharPontos(int $quantidade): void
    {
        $this->pontos += $quantidade;
    }

    public function gastarEnergia(int $quantidade): void
    {
        $this->energia = max(0, $this->energia - $quantidade);
    }

    public function ganharEnergia(int $quantidade): void
    {
        $this->energia = min(100, $this->energia + $quantidade);
    }

    public function perderVida(int $quantidade): void
    {
        $this->vida = max(0, $this->vida - $quantidade);
    }

    public function recuperarVida(int $quantidade): void
    {
        $this->vida = min(100, $this->vida + $quantidade);
    }

    public function estaVivo(): bool
    {
        return $this->vida > 0;
    }

    public function toArray(): array
    {
        return [
            'nome' => $this->nome,
            'vida' => $this->vida,
            'energia' => $this->energia,
            'pontos' => $this->pontos,
        ];
    }

    public static function fromArray(array $dados): self
    {
        return new self(
            $dados['nome'] ?? 'Herói',
            (int) ($dados['vida'] ?? 100),
            (int) ($dados['energia'] ?? 30),
            (int) ($dados['pontos'] ?? 0)
        );
    }
}
