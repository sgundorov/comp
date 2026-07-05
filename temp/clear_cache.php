<?php
if (function_exists('opcache_reset')) { opcache_reset(); echo 'opcache reset\n'; }
if (function_exists('opcache_invalidate')) { opcache_invalidate(__DIR__ . '/../regcod.php', true); echo 'regcod invalidated\n'; }
echo 'done';
