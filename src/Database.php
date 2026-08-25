<?php

class Database
{
    private PDO $pdo;
    private string $arquivo;
    private string $arquivoCsv;

    public function __construct(string $arquivo = __DIR__ . '/../data/placar.db')
    {
        $this->arquivo = $arquivo;
        $diretorio = dirname($this->arquivo);
        $this->arquivoCsv = $diretorio . '/placar.csv';

        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0777, true);
        }

        $this->pdo = new PDO('sqlite:' . $this->arquivo);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->criarTabela();
        $this->criarArquivoCsvSeNecessario();
    }

    private function criarTabela(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS placar (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                pontos INTEGER NOT NULL,
                vida INTEGER NOT NULL,
                energia INTEGER NOT NULL,
                data TEXT NOT NULL
            )'
        );
    }

    private function criarArquivoCsvSeNecessario(): void
    {
        if (!file_exists($this->arquivoCsv)) {
            file_put_contents($this->arquivoCsv, "nome,pontos,vida,energia,data\n");
        }
    }

    public function salvarPontuacao(Personagem $personagem): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO placar (nome, pontos, vida, energia, data) VALUES (:nome, :pontos, :vida, :energia, :data)'
        );

        $stmt->execute([
            'nome' => $personagem->getNome(),
            'pontos' => $personagem->getPontos(),
            'vida' => $personagem->getVida(),
            'energia' => $personagem->getEnergia(),
            'data' => date('d/m/Y H:i:s'),
        ]);

        $this->exportarCsv();
    }

    public function exportarCsv(): void
    {
        $linhas = $this->lerTodos();
        $conteudo = "nome,pontos,vida,energia,data\n";

        foreach ($linhas as $linha) {
            $conteudo .= implode(',', [
                $linha['nome'],
                $linha['pontos'],
                $linha['vida'],
                $linha['energia'],
                $linha['data'],
            ]) . "\n";
        }

        file_put_contents($this->arquivoCsv, $conteudo);
    }

    public function lerTodos(): array
    {
        $stmt = $this->pdo->query('SELECT nome, pontos, vida, energia, data FROM placar ORDER BY pontos DESC, id DESC LIMIT 10');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
