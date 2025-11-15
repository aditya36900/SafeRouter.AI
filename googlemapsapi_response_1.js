const route = $input.first().json.routes?.[0]?.legs?.[0];
if (!route) return [{ json: { points: [] } }];

const points = [];
const steps = route.steps || [];


const interval = Math.max(1, Math.floor(steps.length / 5)); // ~5 evenly spaced
for (let i = 0; i < steps.length; i += interval) {
  const loc = steps[i].start_location;
  if (loc) points.push({ lat: loc.lat, lng: loc.lng });
}


if (steps.at(-1)?.end_location) {
  points.push(steps.at(-1).end_location);
}

// Return one item per coordinate so the next node (Geocoding API) can run per item
return points.map(p => ({ json: p }));
