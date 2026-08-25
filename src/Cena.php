<?php

class Cena
{
    private string $id;
    private string $titulo;
    private string $descricao;
    private string $imagem;
    private string $tipo;
    private ?string $proximo;
    private array $opcoes;

    public function __construct(
        string $id,
        string $titulo,
        string $descricao,
        string $imagem,
        string $tipo = 'normal',
        ?string $proximo = null,
        array $opcoes = []
    ) {
        $this->id = $id;
        $this->titulo = $titulo;
        $this->descricao = $descricao;
        $this->imagem = $imagem;
        $this->tipo = $tipo;
        $this->proximo = $proximo;
        $this->opcoes = $opcoes;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function getDescricao(): string
    {
        return $this->descricao;
    }

    public function getImagem(): string
    {
        return $this->imagem;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function getProximo(): ?string
    {
        return $this->proximo;
    }

    public function getOpcoes(): array
    {
        return $this->opcoes;
    }
}
