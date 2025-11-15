// Get the Gemini output (string inside the "output" field)
let raw = $input.first().json.output || "[]";

// Convert non-string (if any) to string
if (typeof raw !== "string") raw = JSON.stringify(raw);

// Clean out Markdown code fences, escapes, and whitespace
let cleaned = raw
  .replace(/```json/g, "")
  .replace(/```/g, "")
  .replace(/\\n/g, "")
  .replace(/\\"/g, '"')
  .trim();

// Parse safely
let data = [];
try {
  data = JSON.parse(cleaned);
} catch (error) {
  console.log("JSON parse error:", error.message);
  data = [];
}

// Return as individual n8n items
return Array.isArray(data)
  ? data.map(a => ({ json: a }))
  : [{ json: data }];
