<?php
declare(strict_types=1);
// Vercel serverless entry — delega para pagamento/api.php (mantido como fonte)
// Rewrite vercel.json: /pagamento/api.php -> /api/pagamento.php
require __DIR__ . '/../pagamento/api.php';
