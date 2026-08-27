<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/consulta_receita_lib.php';

$cpf = preg_replace('/\D/', '', $_GET['cpf'] ?? $_POST['cpf'] ?? '');

if (strlen($cpf) !== 11) {
    echo "<script>alert('CPF inválido.'); window.location.href='consulta.html';</script>";
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

$queryParams = $_GET;
unset($queryParams['cpf']);

$query = http_build_query(array_merge($queryParams, [
    'cpf' => receita26FormatarCpf($cpf),
    'cpf_limpo' => $cpf,
    'nome' => $dados['nome'],
    'nascimento' => $nascimento,
    'sexo' => $sexo,
]));

header("Location: index3.html?$query");
exit;
