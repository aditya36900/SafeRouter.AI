// Get the first input item safely
const incoming = $input.first().json;

// Check if message data exists


// Extract WhatsApp message info


// Return simplified structure
return [
  {
    json: {
      from: $input.first().json.body.entry[0].changes[0].value.messages[0].from,
      message:  $input.first().json.body.entry[0].changes[0].value.messages[0].text.body|| ""
    }
  }
];
