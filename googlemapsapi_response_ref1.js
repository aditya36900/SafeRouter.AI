const results = $input.all();
const areas = new Set();

for (const item of results) {
  const res = item.json.results?.[0];
  if (res?.formatted_address) {
    const area = res.formatted_address.split(",")[0];
    areas.add(area);
  }
}

return [{ json: { areas: Array.from(areas) } }];
