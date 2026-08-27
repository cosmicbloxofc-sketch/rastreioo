<?php
header("Content-Type: application/json");

// Webhook só confirma recebimento — não grava dados do cliente no projeto
echo json_encode([
    "ok" => true
]);
