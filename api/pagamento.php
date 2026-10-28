<?php
declare(strict_types=1);
// Vercel serverless entry — delega para pagamento/handler.php (mantido como fonte)
// Rewrite vercel.json: /pagamento/handler.php -> /api/pagamento.php
require __DIR__ . '/../pagamento/handler.php';
