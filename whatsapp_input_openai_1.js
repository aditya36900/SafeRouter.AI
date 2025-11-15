const incoming = $input.first().json;

const prompt = `
You are a highly accurate travel route extraction assistant.
From the following user message, extract the **source (starting point)** and **destination (ending point)** locations.

Always return **strict JSON only** in this format:
{
  "source": "name of starting location or null",
  "destination": "name of destination or null"
}

Examples:
Message: "From KPHB to Gachibowli" → {"source":"KPHB","destination":"Gachibowli"}
Message: "I'm in KMIT" → {"source":"KMIT","destination":null}
Message: "Leaving to Gachibowli" → {"source":null,"destination":"Gachibowli"}
Message: "At KMIT going to Gachibowli" → {"source":"KMIT","destination":"Gachibowli"}

Now analyze this message and respond with JSON only — no explanations.

Message: "${incoming.message}"

Also include user number in json = "${incoming.user}" 
`;

return [
  {
    json: {
      user: $input.first().json.user,
      message: $input.first().json.message,
      prompt
    }
  }
];
