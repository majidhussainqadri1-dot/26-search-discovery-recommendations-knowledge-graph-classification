<?php
$root = dirname(__DIR__);
$routes = file_get_contents($root . '/includes/class-file26-routes.php');
if ($routes === false) {
    fwrite(STDERR, "FAIL: routes source unavailable\n");
    exit(1);
}
$required = array(
    '$http_status = 404;',
    'status_header( $http_status );',
    "'topic' !== \$route || 200 !== \$http_status",
    "'merged' === \$term['status']",
);
foreach ($required as $needle) {
    if (strpos($routes, $needle) === false) {
        fwrite(STDERR, "FAIL: missing truthful topic status regression: {$needle}\n");
        exit(1);
    }
}
if (strpos($routes, 'status_header( 200 );') !== false) {
    fwrite(STDERR, "FAIL: File 26 routes must not force every route to HTTP 200\n");
    exit(1);
}
echo "PASS: unavailable topic routes fail with truthful HTTP status\n";
