<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
  http_response_code(403);
  exit('Forbidden');
}

// Optional secret key for extra safety even in CLI tooling
$expected = $_ENV['ACA_API_CRON_KEY'] ?? getenv('ACA_API_CRON_KEY') ?? null;
if ($expected) {
  $provided = $argv[1] ?? null; // pass as first arg
  if (!$provided || !hash_equals($expected, $provided)) {
    exit("Invalid key\n");
  }
}
