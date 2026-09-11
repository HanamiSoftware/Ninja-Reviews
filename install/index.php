<?php

declare(strict_types=1);

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

$configFile = dirname(__DIR__) . '/config.php';
$installedFile = __DIR__ . '/.installed';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000');
}

if (empty($_SESSION['ninjareviews_csrf'])) {
    session_regenerate_id(true);
    $_SESSION['ninjareviews_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['ninjareviews_csrf'];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


function runSystemChecks(bool $isHttps): array
{
    $checks = [];

    $phpOk = PHP_VERSION_ID >= 80000;
    $checks[] = [
        'key' => 'php',
        'label' => 'PHP 8.0 o superiore',
        'ok' => $phpOk,
        'detail' => PHP_VERSION,
    ];

    $curlOk = extension_loaded('curl');
    $checks[] = [
        'key' => 'curl',
        'label' => 'cURL',
        'ok' => $curlOk,
        'detail' => $curlOk ? 'Attivo' : 'Estensione non disponibile',
    ];

    $jsonOk = extension_loaded('json');
    $checks[] = [
        'key' => 'json',
        'label' => 'JSON',
        'ok' => $jsonOk,
        'detail' => $jsonOk ? 'Attivo' : 'Estensione non disponibile',
    ];

    $httpsOk = $isHttps || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $checks[] = [
        'key' => 'https',
        'label' => 'HTTPS',
        'ok' => $httpsOk,
        'detail' => $httpsOk ? 'Connessione sicura' : 'HTTPS richiesto',
    ];

    $configPath = dirname(__DIR__) . '/config.php';
    $directoryWritable = is_writable(dirname($configPath));
    $configWritable = !file_exists($configPath) || is_writable($configPath);
    $writeOk = $directoryWritable && $configWritable;
    $checks[] = [
        'key' => 'filesystem',
        'label' => 'Permessi di scrittura',
        'ok' => $writeOk,
        'detail' => $writeOk ? 'Configurazione scrivibile' : 'Impossibile scrivere la configurazione',
    ];

    $passed = true;
    foreach ($checks as $check) {
        if (!$check['ok']) {
            $passed = false;
            break;
        }
    }

    return ['success' => true, 'passed' => $passed, 'checks' => $checks];
}

function isInstalled(string $configFile, string $installedFile): bool
{
    if (is_file($installedFile)) {
        return true;
    }

    if (!is_file($configFile)) {
        return false;
    }

    $config = @include $configFile;
    return is_array($config)
        && !empty($config['google_api_key'])
        && !empty($config['place_id']);
}

function getRequestOrigin(): string
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        return $origin;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    return $host !== '' ? $scheme . '://' . $host : '';
}

function getBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
    $installDir = rtrim(str_replace('/index.php', '', $script), '/');
    $basePath = preg_replace('~/install$~', '', $installDir) ?: '';
    return rtrim($scheme . '://' . $host . $basePath, '/');
}

function googleRequest(string $url, string $apiKey, string $method = 'GET', ?array $body = null, string $fieldMask = ''): array
{
    $ch = curl_init($url);
    $headers = [
        'X-Goog-Api-Key: ' . $apiKey,
        'Accept: application/json',
        'Accept-Language: it',
        'User-Agent: NinjaReviews/1.0 Installer',
    ];

    if ($fieldMask !== '') {
        $headers[] = 'X-Goog-FieldMask: ' . $fieldMask;
    }
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/json';
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $method === 'POST' ? json_encode($body ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return [false, 502, 'Impossibile contattare Google in questo momento.'];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return [false, 502, 'Risposta non valida da Google.'];
    }
    if ($httpCode === 401 || $httpCode === 403) {
        return [false, 502, 'Google ha rifiutato la API Key. Verifica che "Places API (New)" sia abilitata sul progetto, che la fatturazione sia attiva e che la chiave non sia ristretta su un altro dominio o API.'];
    }
    if ($httpCode === 429) {
        return [false, 429, 'Google ha temporaneamente limitato le richieste. Riprova tra poco.'];
    }
    if ($httpCode >= 400) {
        return [false, 502, 'Google non ha accettato la richiesta. Controlla API Key e API abilitate.'];
    }

    return [true, 200, $data];
}

function isGoogleHost(string $host): bool
{
    $host = strtolower($host);
    $allowed = [
        'google.com',
        'www.google.com',
        'google.it',
        'www.google.it',
        'maps.google.com',
        'maps.google.it',
        'maps.app.goo.gl',
        'goo.gl'
    ];
    foreach ($allowed as $item) {
        if ($host === $item || str_ends_with($host, '.' . $item)) {
            return true;
        }
    }
    return false;
}

function resolveGoogleMapsUrl(string $url): array
{
    $current = trim($url);
    if ($current === '' || !filter_var($current, FILTER_VALIDATE_URL)) {
        return [false, '', 'Inserisci un URL Google Maps valido.'];
    }

    for ($i = 0; $i < 4; $i++) {
        $parts = parse_url($current);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!isGoogleHost($host)) {
            return [false, '', 'Il link deve provenire da Google Maps.'];
        }

        if (!in_array($host, ['maps.app.goo.gl', 'goo.gl'], true)) {
            return [true, $current, ''];
        }

        $ch = curl_init($current);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'NinjaReviews/1.0 Installer',
        ]);
        $responseHeaders = (string) curl_exec($ch);
        $location = '';
        if (preg_match('/^Location:\s*(.+)$/im', $responseHeaders, $m)) {
            $location = trim($m[1]);
        }
        curl_close($ch);

        if ($location === '') {
            return [false, '', 'Non riesco ad aprire il link Google Maps abbreviato. Usa il link completo dell\'attività .'];
        }

        $locationParts = parse_url($location);
        if (!is_array($locationParts) || !isGoogleHost((string) ($locationParts['host'] ?? ''))) {
            return [false, '', 'Il link Google Maps reindirizza a un dominio non consentito.'];
        }
        $current = $location;
    }

    return [false, '', 'Il link Google Maps contiene troppi reindirizzamenti.'];
}

function parseGoogleMapsUrl(string $url): array
{
    [$ok, $resolved, $error] = resolveGoogleMapsUrl($url);
    if (!$ok) {
        return [false, '', '', $error];
    }

    $parts = parse_url($resolved);
    parse_str((string) ($parts['query'] ?? ''), $query);

    if (!empty($query['query_place_id']) && preg_match('/^[A-Za-z0-9:_-]+$/', (string) $query['query_place_id'])) {
        return [true, 'place_id', (string) $query['query_place_id'], ''];
    }
    if (!empty($query['query'])) {
        $text = trim((string) $query['query']);
        if ($text !== '') {
            return [true, 'query', $text, ''];
        }
    }

    $path = (string) ($parts['path'] ?? '');
    if (preg_match('~/(?:maps/)?place/([^/]+)~i', $path, $matches)) {
        $text = trim(urldecode(str_replace('+', ' ', $matches[1])));
        if ($text !== '') {
            return [true, 'query', $text, ''];
        }
    }

    return [false, '', '', 'Non riesco a ricavare il nome dell\'attività  da questo link. Usa il link completo della pagina Google Maps dell\'attività .'];
}

function normalizePlace(array $place): array
{
    return [
        'id' => (string) ($place['id'] ?? ''),
        'name' => (string) ($place['displayName']['text'] ?? ''),
        'address' => (string) ($place['formattedAddress'] ?? ''),
        'rating' => isset($place['rating']) ? (float) $place['rating'] : null,
        'maps_url' => (string) ($place['googleMapsUri'] ?? ''),
    ];
}

$installed = isInstalled($configFile, $installedFile);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['system_check'])) {
    jsonResponse(runSystemChecks($isHttps));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($installed) {
        jsonResponse(['success' => false, 'message' => 'NinjaReviews è già configurato.'], 409);
    }

    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $csrf)) {
        jsonResponse(['success' => false, 'message' => 'Sessione non valida. Ricarica la pagina e riprova.'], 403);
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'search') {
        $apiKey = trim((string) ($_POST['google_api_key'] ?? ''));
        $mapsUrl = trim((string) ($_POST['google_maps_url'] ?? ''));

        if ($apiKey === '') {
            jsonResponse(['success' => false, 'message' => 'Inserisci la Google API Key.'], 422);
        }
        if (strlen($apiKey) < 20 || strlen($apiKey) > 256 || !preg_match('/^[A-Za-z0-9_-]+$/', $apiKey)) {
            jsonResponse(['success' => false, 'message' => 'La Google API Key non sembra valida.'], 422);
        }

        [$parsedOk, $kind, $value, $parseError] = parseGoogleMapsUrl($mapsUrl);
        if (!$parsedOk) {
            jsonResponse(['success' => false, 'message' => $parseError], 422);
        }

        if ($kind === 'place_id') {
            $url = 'https://places.googleapis.com/v1/places/' . rawurlencode($value);
            [$ok, $status, $result] = googleRequest(
                $url,
                $apiKey,
                'GET',
                null,
                'id,displayName,formattedAddress,rating,googleMapsUri'
            );
            if (!$ok) {
                jsonResponse(['success' => false, 'message' => (string) $result], $status);
            }
            $place = normalizePlace($result);
            if ($place['id'] === '') {
                jsonResponse(['success' => false, 'message' => 'Google non ha restituito un\'attività valida.'], 404);
            }
            $_SESSION['ninjareviews_install'] = ['api_key' => $apiKey, 'places' => [$place]];
            jsonResponse(['success' => true, 'places' => [$place], 'exact' => true]);
        }

        [$ok, $status, $result] = googleRequest(
            'https://places.googleapis.com/v1/places:searchText',
            $apiKey,
            'POST',
            ['textQuery' => $value, 'pageSize' => 5],
            'places.id,places.displayName,places.formattedAddress,places.rating,places.googleMapsUri'
        );
        if (!$ok) {
            jsonResponse(['success' => false, 'message' => (string) $result], $status);
        }

        $places = [];
        foreach (($result['places'] ?? []) as $place) {
            if (!is_array($place))
                continue;
            $normalized = normalizePlace($place);
            if ($normalized['id'] !== '')
                $places[] = $normalized;
        }
        if (!$places) {
            jsonResponse(['success' => false, 'message' => 'Non ho trovato attività corrispondenti. Prova con un link Google Maps più specifico.'], 404);
        }

        $_SESSION['ninjareviews_install'] = ['api_key' => $apiKey, 'places' => $places];
        jsonResponse(['success' => true, 'places' => $places, 'exact' => false]);
    }

    if ($action === 'install') {
        $apiKey = trim((string) ($_SESSION['ninjareviews_install']['api_key'] ?? ''));
        $places = $_SESSION['ninjareviews_install']['places'] ?? [];
        $selectedPlaceId = trim((string) ($_POST['place_id'] ?? ''));

        if ($apiKey === '' || !is_array($places)) {
            jsonResponse(['success' => false, 'message' => 'La sessione di installazione è scaduta. Cerca nuovamente l\'attività.'], 409);
        }

        $selected = null;
        foreach ($places as $place) {
            if (is_array($place) && ($place['id'] ?? '') === $selectedPlaceId) {
                $selected = $place;
                break;
            }
        }
        if (!$selected) {
            jsonResponse(['success' => false, 'message' => 'Attività selezionata non valida.'], 422);
        }

        $origin = getRequestOrigin();
        $config = [
            'google_api_key' => $apiKey,
            'place_id' => $selected['id'],
            'allowed_origins' => $origin !== '' ? [$origin] : [],
        ];
        $php = "<?php\n\n// Generated by NinjaReviews Installer.\nreturn " . var_export($config, true) . ";\n";

        $tempConfig = $configFile . '.tmp-' . bin2hex(random_bytes(8));
        if (@file_put_contents($tempConfig, $php, LOCK_EX) === false) {
            @unlink($tempConfig);
            jsonResponse(['success' => false, 'message' => 'Non riesco a scrivere config.php. Verifica i permessi della cartella principale.'], 500);
        }

        @chmod($tempConfig, 0640);

        if (!@rename($tempConfig, $configFile)) {
            @unlink($tempConfig);
            jsonResponse(['success' => false, 'message' => 'Non riesco a finalizzare config.php. Verifica i permessi della cartella principale.'], 500);
        }

        @chmod($configFile, 0640);
        @file_put_contents($installedFile, date('c'), LOCK_EX);
        @chmod($installedFile, 0600);
        unset($_SESSION['ninjareviews_install']);

        $baseUrl = rtrim(getBaseUrl(), '/');

        $baseUrl = rtrim(getBaseUrl(), '/');
        $embed = '<script src="' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '/js/embed.js"></script>';


        jsonResponse(['success' => true, 'message' => 'NinjaReviews è stato installato correttamente.', 'place' => $selected, 'embed' => $embed]);
    }

    jsonResponse(['success' => false, 'message' => 'Azione non valida.'], 400);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>NinjaReviews - Installazione</title>
<link rel="stylesheet" href="css/installer.css">
</head>
<body>
<main class="nr-installer">
    <div class="nr-installer-card">
        <div class="nr-installer-logo">NinjaReviews</div>
        <h1>Configura NinjaReviews</h1>
        <p class="nr-installer-intro">Collega la tua attività Google Maps in pochi passaggi.</p>

        <?php if ($installed): ?>
            <div class="nr-message nr-message-success is-visible">
                NinjaReviews è già configurato. Per sicurezza, elimina la cartella <code>install/</code> dal server.
            </div>
        <?php else: ?>
            
            <section class="nr-system-check" id="nr-system-check" aria-labelledby="nr-system-check-title">
                <div class="nr-system-check-header">
                    <div>
                        <h2 id="nr-system-check-title">Controllo del server</h2>
                        <p>Verifichiamo che il server soddisfi i requisiti necessari per NinjaReviews.</p>
                    </div>
                    <span class="nr-system-check-status" id="nr-system-check-status">Controllo...</span>
                </div>
                <div class="nr-system-check-list" id="nr-system-check-list"></div>
                <div class="nr-system-check-message" id="nr-system-check-message"></div>
            </section>

            <div id="nr-install-config" class="nr-install-config is-disabled" aria-disabled="true">
<div class="nr-step">
                <span class="nr-step-number">1</span>
                <div>
                    <h2>Google API Key</h2>
                    <p>Inserisci una API Key con Google Places API (New) abilitata.</p>
                    <p class="nr-help-link"><a href="https://console.cloud.google.com/apis/library/places.googleapis.com" target="_blank" rel="noopener noreferrer">Non hai ancora una API Key? Crea un progetto e abilita Places API (New) →</a></p>
                    <input id="google_api_key" type="password" autocomplete="new-password" spellcheck="false" placeholder="AIza...">
                </div>
            </div>

            <div class="nr-step">
                <span class="nr-step-number">2</span>
                <div>
                    <h2>Link Google Maps</h2>
                    <p>Incolla il link della pagina della tua attivitÃ  su Google Maps.</p>
                    <input id="google_maps_url" type="url" autocomplete="off" placeholder="https://www.google.com/maps/place/...">
                </div>
            </div>

            <button id="searchButton" type="button" class="nr-installer-button">Trova attività</button>
            <div id="message" class="nr-message" role="status" aria-live="polite"></div>
            <div id="results" class="nr-results" aria-live="polite"></div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
(() => {
    const systemCheck = document.getElementById('nr-system-check');
    const systemCheckList = document.getElementById('nr-system-check-list');
    const systemCheckStatus = document.getElementById('nr-system-check-status');
    const systemCheckMessage = document.getElementById('nr-system-check-message');
    const installConfig = document.getElementById('nr-install-config');

    const renderSystemCheck = data => {
        if (!systemCheckList || !systemCheckStatus || !installConfig) return;

        systemCheckList.innerHTML = (data.checks || []).map(check => `
            <div class="nr-system-check-item ${check.ok ? 'is-ok' : 'is-error'}">
                <span class="nr-system-check-icon">${check.ok ? '\u2713' : '\u2022'}</span>
                <div>
                    <strong>${String(check.label)}</strong>
                    <span>${String(check.detail || '')}</span>
                </div>
            </div>
        `).join('');

        const passed = data.passed === true;
        systemCheckStatus.textContent = passed ? 'Compatibile' : 'Problemi da risolvere';
        systemCheckStatus.className = 'nr-system-check-status ' + (passed ? 'is-ok' : 'is-error');
        systemCheckMessage.textContent = passed
            ? 'Il server è compatibile con NinjaReviews.'
            : 'Risolvi i requisiti evidenziati prima di continuare.';
        systemCheckMessage.className = 'nr-system-check-message ' + (passed ? 'is-ok' : 'is-error');

        installConfig.classList.toggle('is-disabled', !passed);
        installConfig.setAttribute('aria-disabled', passed ? 'false' : 'true');
        installConfig.querySelectorAll('input, button, select, textarea').forEach(el => {
            el.disabled = !passed;
        });
    };

    if (systemCheck) {
        fetch(window.location.pathname + '?system_check=1', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        })
        .then(response => {
            if (!response.ok) throw new Error('System check failed');
            return response.json();
        })
        .then(renderSystemCheck)
        .catch(() => {
            if (systemCheckStatus) {
                systemCheckStatus.textContent = 'Impossibile verificare';
                systemCheckStatus.className = 'nr-system-check-status is-error';
            }
            if (systemCheckMessage) {
                systemCheckMessage.textContent = 'Non è stato possibile verificare il server.';
                systemCheckMessage.className = 'nr-system-check-message is-error';
            }
        });
    }

    const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
    const apiKeyInput = document.getElementById('google_api_key');
    const mapsUrlInput = document.getElementById('google_maps_url');
    const searchButton = document.getElementById('searchButton');
    const message = document.getElementById('message');
    const results = document.getElementById('results');
    if (!apiKeyInput || !mapsUrlInput || !searchButton) return;

    const escapeHtml = value => String(value ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

    const showMessage = (text, type = 'error') => {
        message.textContent = text;
        message.className = 'nr-message nr-message-' + type + ' is-visible';
    };

    const setBusy = busy => {
        searchButton.disabled = busy;
        searchButton.textContent = busy ? 'Ricerca in corso...' : 'Trova attività';
    };

    const renderPlaces = places => {
        results.innerHTML = places.map(place => {
            const rating = place.rating ? '⭐' + escapeHtml(Number(place.rating).toFixed(1)) : 'Rating non disponibile';
            return `<article class="nr-place-card">
                <div class="nr-place-content">
                    <h3>${escapeHtml(place.name || 'Attività ')}</h3>
                    <p>${escapeHtml(place.address || 'Indirizzo non disponibile')}</p>
                    <div class="nr-place-rating">${rating}</div>
                </div>
                <button type="button" class="nr-select-button" data-place-id="${escapeHtml(place.id)}">
                    ${places.length === 1 ? 'Conferma attività' : 'Seleziona'}
                </button>
            </article>`;
        }).join('');
        results.querySelectorAll('.nr-select-button').forEach(button => {
            button.addEventListener('click', () => installPlace(button.dataset.placeId));
        });
    };

    const installPlace = async placeId => {
        document.querySelectorAll('.nr-select-button').forEach(button => button.disabled = true);
        showMessage('Installazione in corso...', 'info');
        const body = new URLSearchParams({ action: 'install', place_id: placeId, csrf_token: csrfToken });
        try {
            const response = await fetch(window.location.href, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body, cache:'no-store' });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Installazione non riuscita.');
            results.innerHTML = `<div class="nr-install-success">
                <div class="nr-success-icon">&#10003;</div>
                <h2>Installazione completata</h2>
                <p><strong>${escapeHtml(data.place.name)}</strong> è stata configurata correttamente.</p>
                <p class="nr-small">Per sicurezza, elimina ora la cartella <code>install/</code> dal server.</p>
                <h3>Codice da inserire nel sito</h3>
                <p id="nr-embed-code" class="nr-embed-code">${escapeHtml(data.embed)}</p>
                <button type="button" id="nr-copy-code" class="nr-installer-button nr-copy-button">Copia codice</button>
                <p id="nr-copy-status" class="nr-copy-status" aria-live="polite"></p>
            </div>`;
            showMessage('NinjaReviews è pronto.', 'success');
            searchButton.style.display = 'none';
            document.getElementById('nr-copy-code').addEventListener('click', async () => {
                const code = document.getElementById('nr-embed-code').textContent;
                try {
                    await navigator.clipboard.writeText(code);
                    document.getElementById('nr-copy-status').textContent = 'Incolla il Codice generato nel punto dove vuoi che appaiano le recensioni.';
                } catch (_) {
                    document.getElementById('nr-embed-code').select();
                    document.getElementById('nr-copy-status').textContent = 'Seleziona e copia il codice manualmente nel punto dove vuoi che appaiano le recensioni.';
                }
            });
        } catch (error) {
            document.querySelectorAll('.nr-select-button').forEach(button => button.disabled = false);
            showMessage(error.message || 'Si è verificato un errore.', 'error');
        }
    };

    searchButton.addEventListener('click', async () => {
        const apiKey = apiKeyInput.value.trim();
        const mapsUrl = mapsUrlInput.value.trim();
        if (!apiKey || !mapsUrl) {
            showMessage('Inserisci API Key e link Google Maps.', 'error');
            return;
        }
        results.innerHTML = '';
        showMessage('', 'info');
        setBusy(true);
        const body = new URLSearchParams({ action:'search', google_api_key:apiKey, google_maps_url:mapsUrl, csrf_token:csrfToken });
        try {
            const response = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body, cache:'no-store' });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Ricerca non riuscita.');
            renderPlaces(data.places || []);
            showMessage(data.exact ? 'Attività identificata direttamente dal link Google Maps.' : 'Controlla il risultato e conferma l\'attività corretta.', 'success');
        } catch (error) {
            showMessage(error.message || 'Si è verificato un errore.', 'error');
        } finally {
            setBusy(false);
        }
    });
})();
</script>
</body>
</html>
