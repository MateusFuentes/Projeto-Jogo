<?php

class Personagem
{
    private string $nome;
    private int $vida;
    private int $energia;
    private int $pontos;
    private array $atributos;

    public function __construct(string $nome, int $vida = 100, int $energia = 30, int $pontos = 0, array $atributos = [])
    {
        $this->nome = $nome;
        $this->vida = $vida;
        $this->energia = $energia;
        $this->pontos = $pontos;
        $this->atributos = $this->normalizarAtributos($atributos);
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

    public function getAtributos(): array
    {
        return $this->atributos;
    }

    public function setAtributos(array $atributos): void
    {
        $this->atributos = $this->normalizarAtributos($atributos);
    }

    public function getAtributo(string $nome): int
    {
        return (int) ($this->atributos[$nome] ?? 50);
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

    private function normalizarAtributos(array $atributos): array
    {
        $padrao = [
            'agilidade' => 50,
            'forca' => 50,
            'resistencia' => 50,
            'inteligencia' => 50,
            'sorte' => 50,
        ];

        foreach ($padrao as $nome => $valorPadrao) {
            $padrao[$nome] = isset($atributos[$nome])
                ? max(0, min(100, (int) $atributos[$nome]))
                : $valorPadrao;
        }

        return $padrao;
    }

    public function toArray(): array
    {
        return [
            'nome' => $this->nome,
            'vida' => $this->vida,
            'energia' => $this->energia,
            'pontos' => $this->pontos,
            'atributos' => $this->atributos,
        ];
    }

    public static function fromArray(array $dados): self
    {
        return new self(
            $dados['nome'] ?? 'Herói',
            (int) ($dados['vida'] ?? 100),
            (int) ($dados['energia'] ?? 30),
            (int) ($dados['pontos'] ?? 0),
            $dados['atributos'] ?? []
        );
    }
}
