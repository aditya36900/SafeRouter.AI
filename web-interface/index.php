<?php
// SafeRouter local dashboard + webhook (XAMPP friendly)

date_default_timezone_set('Asia/Kolkata');

$latestFile = __DIR__ . '/data/latest.json';
$route = $_GET['route'] ?? ''; // '', 'webhook', 'latest'

// OPTIONAL: Google Maps Embed API key (for directions iframe)
// If empty, we fall back to embedding googleMapsLink (if present).
$googleMapsApiKey = 'AIzaSyDEJckuWvHO1PZj032JaPxwBO85G2tLd4o'; // e.g. 'AIza...'

// ---------- 1) WEBHOOK: index.php?route=webhook ----------
if ($route === 'webhook') {
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = file_get_contents('php://input');
        $json = json_decode($body, true);

        if ($json === null) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
            exit;
        }

        file_put_contents($latestFile, json_encode($json));
        echo json_encode(['ok' => true, 'method' => 'POST', 'received' => true]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['data'])) {
            $raw = $_GET['data'];
            $decoded = json_decode($raw, true);
            if ($decoded === null) {
                $decoded2 = json_decode(urldecode($raw), true);
                if ($decoded2 !== null) $decoded = $decoded2;
            }
            if ($decoded === null) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Could not parse data param as JSON']);
                exit;
            }
            file_put_contents($latestFile, json_encode($decoded));
            echo json_encode(['ok' => true, 'method' => 'GET', 'received' => true]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No data param provided']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// ---------- 2) LATEST JSON: index.php?route=latest ----------
if ($route === 'latest') {
    header('Content-Type: application/json');

    if (!file_exists($latestFile)) {
        $sample = [
            [
                "areas" => [
                    [
                        "area" => "Hari Vihar Colony",
                        "incidentRisk" => 0,
                        "weatherCondition" => "Haze",
                        "weatherDescription" => "haze",
                        "weatherRisk" => 0,
                        "temperature" => 27.06,
                        "safetyScore" => 0.5,
                        "summary" => "✅ Safe: Hari Vihar Colony — clear weather and no incidents.",
                        "recentIncidents" => []
                    ],
                    [
                        "area" => "Lumbini Avenue",
                        "incidentRisk" => 2,
                        "weatherCondition" => "Haze",
                        "weatherDescription" => "haze",
                        "weatherRisk" => 1,
                        "temperature" => 27.06,
                        "safetyScore" => 4.0,
                        "summary" => "⚠ Moderate risk near Lumbini Avenue: Haze, possible delays or congestion.",
                        "recentIncidents" => []
                    ]
                ],
                "averageSafetyScore" => 1.1,
                "highestSafetyScore" => 4.0,
                "maxRiskArea" => "Lumbini Avenue",
                "origin" => "3-5-1026, Hari Vihar Colony, Bhawani Nagar, Narayanguda, Hyderabad, Telangana 500029, India",
                "destination" => "Qualcomm Building 6, Raheja Mindspace, HITEC City, Hyderabad, Telangana 500081, India",
                "googleMapsLink" => "https://www.google.com/maps/dir/?api=1&origin=3-5-1026%2C%20Hari%20Vihar%20Colony%2C%20Bhawani%20Nagar%2C%20Narayanguda%2C%20Hyderabad%2C%20Telangana%20500029%2C%20India&destination=Gachibowli%2C%20Hyderabad%2C%20Telangana%2C%20India"
            ]
        ];
        echo json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo file_get_contents($latestFile);
    exit;
}

// ---------- 3) DASHBOARD UI ----------
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
        <p class="text-xs text-gray-500">Beta Phase (Under Development)</p>
      </div>
      <button id="refreshBtn" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm">Refresh now</button>
    </header>

    <div class="grid grid-cols-12 gap-4">
      <!-- LEFT: Main info + map + raw JSON -->
      <div class="col-span-12 lg:col-span-7 space-y-4">
        <!-- Origin / Destination + scores -->
        <div class="card p-4">
          <div class="flex items-center justify-between">
            <div class="w-1/2 pr-4 border-r">
              <div class="text-xs text-gray-500 mb-1">Origin</div>
              <div id="origin" class="font-semibold text-sm leading-snug">—</div>
            </div>
            <div class="w-1/2 pl-4">
              <div class="text-xs text-gray-500 mb-1 text-right">Destination</div>
              <div id="destination" class="font-semibold text-sm leading-snug text-right">—</div>
            </div>
          </div>

          <!-- small divider between origin/destination and scores -->
          <hr class="my-3 border-gray-200"/>

          <div class="mt-1 flex items-center justify-between text-sm">
            <div>
              <div class="text-xs text-gray-500 mb-1">Average Safety Score (10 = safest)</div>
              <div class="flex items-center gap-2">
                <div id="avgScore" class="text-2xl font-bold">—</div>
                <span id="avgScoreBadge" class="text-xs"></span>
              </div>
            </div>
            <div class="text-right">
              <div class="text-xs text-gray-500 mb-1">Highest Safety Score</div>
              <div class="flex items-center justify-end gap-2">
                <div id="highScore" class="text-2xl font-bold">—</div>
                <span id="highScoreBadge" class="text-xs"></span>
              </div>
            </div>
          </div>

          <div class="mt-3 text-sm">
            <span class="text-xs text-gray-500">Max Risk Area: </span>
            <span id="maxRiskArea" class="font-semibold">—</span>
          </div>
        </div>

        <!-- Map card -->
        <div class="card p-3">
          <div class="text-xs text-gray-500 mb-2">Route Map (Google Maps embed)</div>
          <iframe id="mapFrame" style="display:none;"></iframe>
          <div id="mapFallback" class="text-xs text-gray-500">No route info available.</div>
        </div>

        <!-- Raw JSON -->
        <div class="card p-4">
          <div class="text-xs text-gray-500">Raw JSON (first object if array)</div>
          <pre id="raw" class="mono mt-2 p-2 bg-slate-50 rounded" style="max-height:260px;overflow:auto;">Loading…</pre>
        </div>
      </div>

      <!-- RIGHT: Areas + Route Safety Snapshot (moved here) -->
      <div class="col-span-12 lg:col-span-5 space-y-4">
        <div class="card p-4">
          <div class="flex items-center justify-between mb-2">
            <div>
              <div class="text-sm font-semibold">Areas breakdown</div>
              <div class="text-xs text-gray-500">SafeRoute analysis</div>
            </div>
            <div class="text-xs text-gray-500">Count: <span id="areaCount">0</span></div>
          </div>
          <div id="areas" class="space-y-2 text-sm" style="max-height:320px;overflow:auto;"></div>
        </div>

        <!-- Route safety + weather tab (now below areas) -->
        <div class="card p-4">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xs text-gray-500 mb-1">Route Safety Snapshot</div>
              <div id="routeSafetyText" class="text-sm font-semibold">—</div>
            </div>
            <div class="text-right">
              <div class="text-xs text-gray-500 mb-1">Route Weather</div>
              <div class="flex items-center justify-end gap-2 text-sm font-semibold">
                <span id="routeWeatherEmoji">🌍</span>
                <span id="routeWeatherTemp">—</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
// convert raw risk (0 good, 10 bad) -> safety (10 good, 0 bad)
function toSafetyScore(raw) {
  if (typeof raw !== 'number') return null;
  let v = 10 - raw;
  if (v < 0) v = 0;
  if (v > 10) v = 10;
  return parseFloat(v.toFixed(1));
}

// badge config for safety score
function getSafetyBadge(scoreNum) {
  if (typeof scoreNum !== 'number') return null;
  if (scoreNum >= 8) {
    return { label: 'Safe', classes: 'bg-emerald-100 text-emerald-700' };
  } else if (scoreNum >= 5) {
    return { label: 'Moderate', classes: 'bg-amber-100 text-amber-700' };
  } else {
    return { label: 'Risky', classes: 'bg-rose-100 text-rose-700' };
  }
}

function renderBadge(elementId, scoreNum) {
  const el = document.getElementById(elementId);
  if (!el) return;
  el.innerHTML = '';
  el.className = 'text-xs';
  const info = getSafetyBadge(scoreNum);
  if (!info) return;
  el.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ' + info.classes;
  el.textContent = info.label;
}

// weather emoji based on condition
function getWeatherEmoji(condition) {
  if (!condition) return '🌍';
  const c = condition.toLowerCase();
  if (c.includes('clear') || c.includes('sun')) return '☀️';
  if (c.includes('cloud')) return '☁️';
  if (c.includes('rain') || c.includes('drizzle') || c.includes('storm')) return '🌧️';
  if (c.includes('snow')) return '❄️';
  if (c.includes('haze') || c.includes('fog') || c.includes('mist') || c.includes('smoke')) return '🌫️';
  return '🌍';
}

// remember last route so the map doesn't reload every refresh
let lastMapSrc = null;

async function loadData() {
  try {
    const res = await fetch('index.php?route=latest&ts=' + Date.now());
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();

    const data = Array.isArray(json) ? (json[0] || {}) : json;
    const areas = Array.isArray(data.areas) ? data.areas : [];

    document.getElementById('raw').textContent = JSON.stringify(data, null, 2);

    // origin / destination
    const origin = data.origin || '—';
    const destination = data.destination || '—';
    document.getElementById('origin').textContent      = origin;
    document.getElementById('destination').textContent = destination;
    document.getElementById('maxRiskArea').textContent = data.maxRiskArea || '—';
    document.getElementById('areaCount').textContent   = areas.length;

    // Average safety (invert route-level risk)
    let avgSafety = null;
    if (typeof data.averageSafetyScore === 'number') {
      avgSafety = toSafetyScore(data.averageSafetyScore);
      document.getElementById('avgScore').textContent = avgSafety.toFixed(1);
    } else {
      document.getElementById('avgScore').textContent = '—';
    }
    renderBadge('avgScoreBadge', avgSafety);

    // Highest safety from min area risk
    let minRisk = null;
    areas.forEach(a => {
      if (typeof a.safetyScore === 'number') {
        if (minRisk === null || a.safetyScore < minRisk) minRisk = a.safetyScore;
      }
    });

    let highSafety = null;
    if (minRisk !== null) {
      highSafety = toSafetyScore(minRisk);
      document.getElementById('highScore').textContent = highSafety.toFixed(1);
    } else if (typeof data.highestSafetyScore === 'number') {
      highSafety = toSafetyScore(data.highestSafetyScore);
      document.getElementById('highScore').textContent = highSafety.toFixed(1);
    } else {
      document.getElementById('highScore').textContent = '—';
    }
    renderBadge('highScoreBadge', highSafety);

    // Route safety summary
    let safetyText = '—';
    if (typeof avgSafety === 'number') {
      if (avgSafety >= 8) safetyText = 'Route is very safe overall.';
      else if (avgSafety >= 5) safetyText = 'Route is moderately safe. Stay alert at a few segments.';
      else safetyText = 'Route has higher-risk segments. Extra caution advised.';
    }
    document.getElementById('routeSafetyText').textContent = safetyText;

    // Route weather summary (average temp + dominant condition)
    let tempSum = 0, tempCount = 0;
    const conditionCounts = {};
    areas.forEach(a => {
      if (typeof a.temperature === 'number') {
        tempSum += a.temperature;
        tempCount++;
      }
      const cond = a.weatherCondition || a.weatherDescription;
      if (cond) {
        conditionCounts[cond] = (conditionCounts[cond] || 0) + 1;
      }
    });

    let avgTemp = null;
    if (tempCount > 0) avgTemp = tempSum / tempCount;

    let dominantCondition = null;
    let maxCount = 0;
    for (const cond in conditionCounts) {
      if (conditionCounts[cond] > maxCount) {
        maxCount = conditionCounts[cond];
        dominantCondition = cond;
      }
    }

    document.getElementById('routeWeatherEmoji').textContent = getWeatherEmoji(dominantCondition);
    document.getElementById('routeWeatherTemp').textContent =
      (avgTemp !== null ? avgTemp.toFixed(1) + '°C' : '—');

    // Map (embed key-first, fallback to googleMapsLink)
    const frame = document.getElementById('mapFrame');
    const fallback = document.getElementById('mapFallback');
    const GOOGLE_MAPS_KEY = "<?php echo $googleMapsApiKey; ?>";
    let newSrc = null;

    if (GOOGLE_MAPS_KEY && data.origin && data.destination) {
      newSrc =
        'https://www.google.com/maps/embed/v1/directions' +
        '?key=' + encodeURIComponent(GOOGLE_MAPS_KEY) +
        '&origin=' + encodeURIComponent(data.origin) +
        '&destination=' + encodeURIComponent(data.destination);
    } else if (data.googleMapsLink) {
      const link = data.googleMapsLink;
      newSrc = link.includes('output=embed') ? link : link + '&output=embed';
    }

    if (newSrc) {
      if (lastMapSrc !== newSrc) {
        frame.src = newSrc; // only reload when route changed
        lastMapSrc = newSrc;
      }
      frame.style.display = 'block';
      fallback.style.display = 'none';
    } else {
      frame.style.display = 'none';
      fallback.style.display = 'block';
      fallback.textContent = 'No route info available.';
    }

    // Areas cards (with emoji + safety badges)
    const areasDiv = document.getElementById('areas');
    areasDiv.innerHTML = '';

    areas.forEach(a => {
      const div = document.createElement('div');
      div.className = 'border rounded p-2 bg-white';

      const title = a.area || '—';
      const temp  = (typeof a.temperature === 'number') ? a.temperature.toFixed(1) + '°C' : '—';
      const weather = a.weatherCondition || a.weatherDescription || '—';
      const rawScore = (typeof a.safetyScore === 'number') ? a.safetyScore : null;
      const safeScore = (rawScore !== null) ? toSafetyScore(rawScore) : null;
      const score   = (safeScore !== null) ? safeScore.toFixed(1) : '—';
      const summary = a.summary || '';
      const badgeInfo = getSafetyBadge(safeScore);
      const badgeHtml = badgeInfo
        ? `<span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold ${badgeInfo.classes}">
             ${badgeInfo.label}
           </span>`
        : '';
      const emoji = getWeatherEmoji(weather);

      div.innerHTML = `
        <div class="flex items-center justify-between">
          <div>
            <div class="font-semibold">${title}</div>
            <div class="text-xs text-gray-500 flex items-center gap-1">
              <span>${emoji}</span>
              <span>Weather: ${weather} • Temp: ${temp}</span>
            </div>
          </div>
          <div class="text-xs text-gray-500 text-right">
            Safety Score<br>
            <span class="text-base font-bold">${score}</span>/10
            <div class="mt-1">${badgeHtml}</div>
          </div>
        </div>
        <div class="mt-2 text-xs text-gray-700">${summary}</div>
      `;
      areasDiv.appendChild(div);
    });

  } catch (e) {
    console.error(e);
    document.getElementById('raw').textContent = 'Error fetching latest: ' + e.message;
  }
}

document.getElementById('refreshBtn').addEventListener('click', loadData);
setInterval(loadData, 5000);   // map only reloads when route changes
window.onload = loadData;
</script>
</body>
</html>
