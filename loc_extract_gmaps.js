// Get the first input item
const item = $input.first();

// Safely extract the route data
const route = item.json.routes?.[0]?.legs?.[0];

// Extract start and end addresses
const origin = route?.start_address || "Unknown Origin";
const destination = route?.end_address || "Unknown Destination";

// Generate a Google Maps route link
const googleMapsLink = `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(origin)}&destination=${encodeURIComponent(destination)}`;

// Return data in n8n-compatible format
return [
  {
    json: {
      origin,
      destination,
      googleMapsLink
    }
  }
];
