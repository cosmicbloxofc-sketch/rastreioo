<?php
declare(strict_types=1);
// Vercel: api/* only runs as lambda; pagamento/index.php is static outside api
// Route /pagamento/ -> /api/pagamento-page.php -> delegate to pagamento/index.php
require __DIR__ . '/../pagamento/index.php';
