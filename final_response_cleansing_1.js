// Get the raw Gemini output
let text = $input.first().json.output || "";

// Remove escaped newline symbols (\n) and excessive spaces
text = text
  .replace(/\\n/g, ' ')     // Replace \n with a space
  .replace(/\n/g, ' ')      // Just in case there are real newlines too
  .replace(/\s{2,}/g, ' ')  // Collapse multiple spaces into one
  .trim();

// Return cleaned text
return [{ json: { messages: text } }];
