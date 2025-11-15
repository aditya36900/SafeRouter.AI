// --- Collect all inputs ---
const inputs = $input.all();

// Separate Gemini (incident) data, weather data, and route link data
let incidentData = [];
let weatherData = [];
let routeInfo = null;

// n8n may pass items in different positions, so detect which is which
for (const item of inputs) {
  const data = item.json;

  if (data.incidentRisk !== undefined) {
    incidentData.push(item);
  } else if (data.weather || data.main) {
    weatherData.push(item);
  } else if (data.googleMapsLink) {
    // store route info for later
    routeInfo = data;
  }
}

// --- Helper: Weather risk from description ---
function getWeatherRisk(description = "") {
  const desc = description.toLowerCase();
  if (desc.includes("thunderstorm") || desc.includes("heavy rain") || desc.includes("flood")) return 4;
  if (desc.includes("rain") || desc.includes("shower")) return 3;
  if (desc.includes("mist") || desc.includes("fog") || desc.includes("haze")) return 2;
  if (desc.includes("heat") || desc.includes("hot")) return 1;
  return 0;
}

// --- Prepare weather info (use first weather node if one city) ---
const weather = weatherData?.[0]?.json || {};
const weatherDesc = weather.weather?.[0]?.description || "clear";
const weatherMain = weather.weather?.[0]?.main || "Clear";
const temperature = weather.main?.temp || null;
const weatherRisk = getWeatherRisk(weatherDesc);

// --- Combine all area-level data ---
const areas = [];

for (const item of incidentData) {
  const d = item.json;

  // Weighted score: 70% incident, 30% weather
  let rawScore = (d.incidentRisk || 0) * 0.7 + weatherRisk * 0.3;

  // ✅ Fix: set to 0 if both incident and weather risks are negligible
  let safetyScore =
    (d.incidentRisk === 0 && weatherRisk <= 2)
      ? 0
      : Math.min(10, Math.round(rawScore));

  // --- Summary text ---
  let summary;
  if (safetyScore >= 8) {
    summary = `🚨 High risk near ${d.area}: ${weatherMain} and unsafe conditions reported.`;
  } else if (safetyScore >= 4) {
    summary = `⚠️ Moderate risk near ${d.area}: ${weatherMain}, possible delays or congestion.`;
  } else {
    summary = `✅ Safe: ${d.area} — clear weather and no incidents.`;
  }

  // --- Push result ---
  areas.push({
    area: d.area,
    incidentRisk: d.incidentRisk,
    weatherCondition: weatherMain,
    weatherDescription: weatherDesc,
    weatherRisk,
    temperature,
    safetyScore,
    summary,
    recentIncidents: d.recentIncidents || []
  });
}

// --- Compute overall metrics ---
const avgSafety =
  areas.length > 0
    ? areas.reduce((a, c) => a + (c.safetyScore || 0), 0) / areas.length
    : 0;

const maxScore = Math.max(...areas.map(a => a.safetyScore || 0));
const maxArea = areas.find(a => (a.safetyScore || 0) === maxScore)?.area || null;

// --- Return final structured output with single map link ---
return [
  {
    json: {
      areas,
      averageSafetyScore: parseFloat(avgSafety.toFixed(1)),
      highestSafetyScore: maxScore,
      maxRiskArea: maxArea,
      origin: routeInfo?.origin || null,
      destination: routeInfo?.destination || null,
      googleMapsLink: routeInfo?.googleMapsLink || null
    }
  }
];
