<?php
// === CONFIG ===
date_default_timezone_set('Asia/Kolkata');
$latestFile = __DIR__ . '/data/latest.json';

// Small helper to send JSON
function send_json($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Determine request path + method
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ===========================
// 1) WEBHOOK: /webhook
// ===========================
if ($path === '/webhook') {

    if ($method === 'POST') {
        $body = file_get_contents('php://input');
        $json = json_decode($body, true);

        if ($json === null) {
            send_json(['ok' => false, 'error' => 'Invalid JSON body'], 400);
        }

        // Save as-is, no transformation
        file_put_contents($latestFile, json_encode($json));

        send_json(['ok' => true, 'method' => 'POST', 'received' => true]);
    }

    if ($method === 'GET') {
        // Optional GET support: /webhook?data=<urlencoded-json>
        if (isset($_GET['data'])) {
            $raw    = $_GET['data'];
            $decoded = json_decode($raw, true);
            if ($decoded === null) {
                // Try urldecode
                $decoded2 = json_decode(urldecode($raw), true);
                if ($decoded2 !== null) {
                    $decoded = $decoded2;
                }
            }
            if ($decoded === null) {
                send_json(['ok' => false, 'error' => 'Could not parse data param as JSON'], 400);
            }
            file_put_contents($latestFile, json_encode($decoded));
            send_json(['ok' => true, 'method' => 'GET', 'received' => true]);
        }

        send_json(['ok' => false, 'error' => 'No data param provided'], 400);
    }

    send_json(['ok' => false, 'error' => 'Method not allowed'], 405);
}

// ===========================
// 2) LATEST JSON: /latest.json
// ===========================
if ($path === '/latest.json') {
    header('Content-Type: application/json');

    if (!file_exists($latestFile)) {
        // Fallback sample (your n8n-like output)
        $sample = [
            [
                "areas" => [
                    [
                        "area" => "Hari Vihar Colony",
                        "incidentRisk" => 0,
                        "weatherCondition" => "Clear",
                        "weatherDescription" => "clear sky",
                        "weatherRisk" => 0,
                        "temperature" => 24.99,
                        "safetyScore" => 10,
                        "summary" => "✅ Safe: Hari Vihar Colony — clear weather and no incidents.",
                        "recentIncidents" => []
                    ],
                    [
                        "area" => "Venkata swamy Nagar",
                        "incidentRisk" => 0,
                        "weatherCondition" => "Clear",
                        "weatherDescription" => "clear sky",
                        "weatherRisk" => 0,
                        "temperature" => 24.99,
                        "safetyScore" => 10,
                        "summary" => "✅ Safe: Venkata swamy Nagar — clear weather and no incidents.",
                        "recentIncidents" => []
                    ]
                ],
                "averageSafetyScore" => 9.4,
                "highestSafetyScore" => 10,
                "maxRiskArea" => "Fateh Maidan",
                "origin" => "3-5-1026, Hari Vihar Colony, Bhawani Nagar, Narayanguda, Hyderabad, Telangana 500029, India",
                "destination" => "Gachibowli, Hyderabad, Telangana, India",
                "googleMapsLink" => "https://www.google.com/maps/dir/?api=1&origin=3-5-1026%2C%20Hari%20Vihar%20Colony%2C%20Bhawani%20Nagar%2C%20Narayanguda%2C%20Hyderabad%2C%20Telangana%20500029%2C%20India&destination=Gachibowli%2C%20Hyderabad%2C%20Telangana%2C%20India"
            ]
        ];
        echo json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo file_get_contents($latestFile);
    exit;
}

// ===========================
// 3) DASHBOARD UI: /
// ===========================
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>SafeRouter — Local Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background:#f3f6fb; }
    .card { background:#fff;border-radius:10px;box-shadow:0 6px 18px rgba(15,23,42,0.06); }
    pre.mono { font-family: ui-monospace, Menlo, Monaco, "Roboto Mono", monospace; font-size:12px; }
    #mapFrame { width:100%; height:320px; border:0; border-radius:8px; }
  </style>
</head>
<body class="text-slate-800">
  <div class="max-w-6xl mx-auto p-6">
    <header class="mb-4 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold">SafeRouter.AI — Local User Dashboard</h1>
        <p class="text-xs text-gray-500">Data from n8n → webhook → latest.json → this dashboard.</p>
      </div>
      <button id="refreshBtn" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm">Refresh now</button>
    </header>

    <div class="grid grid-cols-12 gap-4">
      <!-- LEFT: Main info + map -->
      <div class="col-span-12 lg:col-span-7 space-y-4">
        <div class="card p-4">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xs text-gray-500">Origin</div>
              <div id="origin" class="font-semibold">—</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 text-right">Destination</div>
              <div id="destination" class="font-semibold text-right">—</div>
            </div>
          </div>
          <div class="mt-3 flex items-center justify-between text-sm">
            <div>
              <div class="text-xs text-gray-500">Average Safety Score</div>
              <div id="avgScore" class="text-lg font-bold">—</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 text-right">Highest Safety Score</div>
              <div id="highScore" class="text-lg font-bold text-right">—</div>
            </div>
          </div>
          <div class="mt-3 text-sm">
            <span class="text-xs text-gray-500">Max Risk Area: </span>
            <span id="maxRiskArea" class="font-semibold">—</span>
          </div>
        </div>

        <div class="card p-3">
          <div class="text-xs text-gray-500 mb-2">Route Map (Google Maps embed if link is present)</div>
          <iframe id="mapFrame" style="display:none;"></iframe>
          <div id="mapFallback" class="text-xs text-gray-500">No googleMapsLink in the latest payload.</div>
        </div>

        <div class="card p-4">
          <div class="text-xs text-gray-500">Raw JSON (first object if array)</div>
          <pre id="raw" class="mono mt-2 p-2 bg-slate-50 rounded" style="max-height:260px;overflow:auto;">Loading…</pre>
        </div>
      </div>

      <!-- RIGHT: Areas -->
      <div class="col-span-12 lg:col-span-5 space-y-4">
        <div class="card p-4">
          <div class="flex items-center justify-between mb-2">
            <div>
              <div class="text-sm font-semibold">Areas breakdown</div>
              <div class="text-xs text-gray-500">From your n8n SafeRoute analysis</div>
            </div>
            <div class="text-xs text-gray-500">Count: <span id="areaCount">0</span></div>
          </div>
          <div id="areas" class="space-y-2 text-sm max-h-[420px] overflow-auto"></div>
        </div>
      </div>
    </div>
  </div>

<script>
async function loadData() {
  try {
    const res = await fetch('latest.json?ts=' + Date.now());
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();

    const data = Array.isArray(json) ? (json[0] || {}) : json;

    // Raw JSON
    document.getElementById('raw').textContent = JSON.stringify(data, null, 2);

    // Top-level fields
    document.getElementById('origin').textContent      = data.origin      || '—';
    document.getElementById('destination').textContent = data.destination || '—';
    document.getElementById('avgScore').textContent    = (data.averageSafetyScore ?? '—');
    document.getElementById('highScore').textContent   = (data.highestSafetyScore ?? '—');
    document.getElementById('maxRiskArea').textContent = data.maxRiskArea || '—';

    // Map
    const frame = document.getElementById('mapFrame');
    const fallback = document.getElementById('mapFallback');
    if (data.googleMapsLink) {
      // Use &output=embed to embed the directions page
      const src = data.googleMapsLink.includes('output=embed')
        ? data.googleMapsLink
        : data.googleMapsLink + '&output=embed';
      frame.src = src;
      frame.style.display = 'block';
      fallback.style.display = 'none';
    } else {
      frame.style.display = 'none';
      fallback.style.display = 'block';
      fallback.textContent = 'No googleMapsLink in the latest payload.';
    }

    // Areas
    const areasDiv = document.getElementById('areas');
    areasDiv.innerHTML = '';
    const areas = Array.isArray(data.areas) ? data.areas : [];
    document.getElementById('areaCount').textContent = areas.length;

    areas.forEach(a => {
      const div = document.createElement('div');
      div.className = 'border rounded p-2 bg-white';

      const title = a.area || '—';
      const temp  = (typeof a.temperature === 'number') ? a.temperature.toFixed(2) + '°C' : '—';
      const weather = a.weatherCondition || a.weatherDescription || '—';
      const score   = (a.safetyScore ?? '—');
      const summary = a.summary || '';

      div.innerHTML = `
        <div class="flex items-center justify-between">
          <div>
            <div class="font-semibold">${title}</div>
            <div class="text-xs text-gray-500">Weather: ${weather} • Temp: ${temp}</div>
          </div>
          <div class="text-xs text-gray-500 text-right">
            Safety Score<br><span class="text-base font-bold">${score}</span>/10
          </div>
        </div>
        <div class="mt-2 text-xs text-gray-700">${summary}</div>
      `;
      areasDiv.appendChild(div);
    });

  } catch (e) {
    console.error(e);
    document.getElementById('raw').textContent = 'Error fetching latest.json: ' + e.message;
  }
}

document.getElementById('refreshBtn').addEventListener('click', loadData);

// auto-refresh every 5 seconds
setInterval(loadData, 5000);
window.onload = loadData;
</script>
</body>
</html>
