<?php
// security_config.php — which defenses are active
// false = VULNERABLE, true = SECURED

$SECURITY = [
    'sql_injection'    => false,
    'xss_output'       => false,
    'xss_stored'       => false,
    'path_traversal'   => false,
    'session_fixation' => false,
];

function isSecured(string $key): bool {
    global $SECURITY;
    return $SECURITY[$key] ?? false;
}

function safeOut(string $value): string {
    if (isSecured('xss_output') || isSecured('xss_stored')) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $value;
}