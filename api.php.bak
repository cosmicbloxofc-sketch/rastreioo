<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/consulta_receita_lib.php';

$cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? $_GET['cpf'] ?? '');

if (strlen($cpf) !== 11) {
    echo json_encode(["status" => 400, "message" => "CPF inválido"], JSON_UNESCAPED_UNICODE);
    exit;
}

$dados = consultarCpfReceita26($cpf);

$nascimento = trim((string)($dados['data_nascimento'] ?? ''));
if ($nascimento === '') {
    $nascimento = 'Não informado';
}

$sexo = trim((string)($dados['sexo'] ?? ''));
if ($sexo === '') {
    $sexo = (intval(substr($cpf, -1)) % 2 == 0) ? 'F' : 'M';
}

echo json_encode([
    "status" => 200,
    "message" => "CPF válido",
    "dados" => [
        "cpf" => $cpf,
        "nome" => $dados['nome'],
        "data_nascimento" => $nascimento,
        "sexo" => $sexo,
    ]
], JSON_UNESCAPED_UNICODE);
