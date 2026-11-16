<?php
declare(strict_types=1);

// Shows the generated website. Until it is paid for, a watermark is drawn over it.

require __DIR__ . '/src/bootstrap.php';

$id = (string) ($_GET['id'] ?? '');
$job = Jobs::isValidId($id) ? Jobs::load($id) : null;
$file = $job ? Jobs::htmlPath($id) : '';

if ($job === null || $job['state'] !== 'done' || !is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Preview not found.';
    exit;
}

$html = (string) file_get_contents($file);

if (empty($job['paid'])) {
    $label = htmlspecialchars(config('site_name') . ' PREVIEW', ENT_QUOTES);
    $svg = rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="220">'
        . '<text x="50%" y="50%" fill="rgba(120,120,120,0.22)" font-family="Arial,sans-serif" font-size="22" font-weight="700" '
        . 'text-anchor="middle" transform="rotate(-24 180 110)">' . $label . '</text></svg>'
    );
    $price = htmlspecialchars(format_price(), ENT_QUOTES);
    $watermark = <<<HTML
<div aria-hidden="true" style="position:fixed;inset:0;z-index:2147483646;pointer-events:none;background-image:url('data:image/svg+xml,{$svg}');background-repeat:repeat;"></div>
<div style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:2147483647;pointer-events:none;background:rgba(17,17,27,.88);color:#fff;font:600 13px/1.2 system-ui,sans-serif;padding:10px 16px;border-radius:999px;box-shadow:0 6px 24px rgba(0,0,0,.25);white-space:nowrap;">Preview only &middot; Pay {$price} to download without watermark</div>
<script>document.addEventListener('contextmenu',function(e){e.preventDefault();});document.addEventListener('copy',function(e){e.preventDefault();});</script>
HTML;
    $pos = strripos($html, '</body>');
    $html = $pos === false ? $html . $watermark : substr($html, 0, $pos) . $watermark . substr($html, $pos);
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
// The generated page runs in a sandbox (its own opaque origin), even if opened directly,
// so its scripts can never touch this site.
header('Content-Security-Policy: sandbox allow-scripts allow-popups allow-forms allow-modals');
echo $html;
