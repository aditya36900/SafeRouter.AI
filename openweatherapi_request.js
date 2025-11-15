const data = $input.first().json;
const leg = data.routes?.[0]?.legs?.[0];

return [
  {
    json: {
      distance_km: leg?.distance?.value / 1000 || null,
      duration_min: leg?.duration?.value / 60 || null,
      start_address: leg?.start_address || null,
      end_address: leg?.end_address || null
    }
  }
];
