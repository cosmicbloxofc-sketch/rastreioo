<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../consulta_receita_lib.php';

$cpf = preg_replace('/\D/', '', $_GET['cpf'] ?? $_POST['cpf'] ?? '');

if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(["error" => "CPF inválido", "status" => 400], JSON_UNESCAPED_UNICODE);
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

// Preservar query params e remover 'cpf'
$queryParams = $_GET;
unset($queryParams['cpf']);

echo json_encode([
    "status" => 200,
    "success" => true,
    "redirect" => "index3.html?" . http_build_query(array_merge($queryParams, [
        'cpf' => $cpf,
        'cpf_formatado' => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf),
        'nome' => $dados['nome'] ?? 'Não encontrado',
        'nascimento' => $nascimento,
        'sexo' => $sexo,
    ])),
    "dados" => [
        "cpf" => $cpf,
        "cpf_formatado" => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf),
        "nome" => $dados['nome'] ?? 'Não encontrado',
        "data_nascimento" => $nascimento,
        "sexo" => $sexo,
    ]
], JSON_UNESCAPED_UNICODE);
