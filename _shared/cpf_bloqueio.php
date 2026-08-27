<?php
declare(strict_types=1);

/**
 * CPF Bloqueio - Verifica se um CPF está bloqueado
 *
 * Esta é uma implementação stub. Em produção, conecte a um banco de dados
 * ou serviço de verificação de CPF bloqueados.
 */

function cpf_bloqueio_verificar(array $data, string $context = 'default'): ?array
{
    // Lista de CPFs bloqueados (exemplo)
    // Em produção, consulte um banco de dados ou API externa
    $cpfsBloqueados = [];

    $cpf = preg_replace('/\D/', '', $data['cpf'] ?? '');

    if (empty($cpf)) {
        return null; // CPF não informado, deixa passar
    }

    if (in_array($cpf, $cpfsBloqueados, true)) {
        return [
            'error' => 'Este CPF está bloqueado',
            'blocked' => true,
            'context' => $context,
        ];
    }

    return null; // CPF não está bloqueado
}
