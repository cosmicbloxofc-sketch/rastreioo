<?php
declare(strict_types=1);
// Vercel: api/* only runs as lambda; pagamento/page.php is static outside api
// Route /pagamento/ -> /api/pagamento-page.php -> delegate to pagamento/page.php
require __DIR__ . '/../pagamento/page.php';
