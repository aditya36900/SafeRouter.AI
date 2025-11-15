let text = $json.messages || $json.messageText || "";

// --- Step 1: Fix SafeRouter.AI formatting and prevent newline break ---
text = text.replace(/SafeRouter\s*\.\s*AI/gi, '*SafeRouter.AI*');

// --- Step 2: Headings cleanup and formatting ---
text = text
  .replace(/###\s*🗺️.*?(Area[-\s]wise Breakdown)/gi, '\n\n🗺️ *Area-wise Breakdown*')
  .replace(/###\s*📊.*?(Overall Route Summary)/gi, '\n\n📊 *Overall Route Summary*')
  .replace(/###\s*🧭.*?(Final Recommendation)/gi, '\n\n🧭 *Final Recommendation*')
  .replace(/###\s*🌐.*?(Google Maps Route Link)/gi, '\n\n🌐 *Google Maps Route Link*');

// --- Step 3: Remove extra stars, hashes, and markdown artifacts ---
text = text
  .replace(/\*\*(.*?)\*\*/g, '$1')   // remove markdown bold
  .replace(/#+/g, '')                // remove #
  .replace(/\*/g, '');               // remove stray *

// --- Step 4: Ensure proper spacing between areas (like bullets) ---
text = text
  .replace(/([A-Za-z)])\s+(?=[A-Z][a-z]+\s+(Nagar|Village|Avenue|City|Enclave))/g, '$1\n\n') // add line before each new area
  .replace(/(Weather:)/g, '\n🌤️ $1')     // add weather emoji
  .replace(/(Incidents:)/g, '\n🚦 $1')   // add incident emoji
  .replace(/(Safety Score:)/g, '\n🛡️ $1'); // add score emoji

// --- Step 5: Bold section titles properly ---
text = text
  .replace(/(🗺️ Area-wise Breakdown)/g, '🗺️ *Area-wise Breakdown*')
  .replace(/(📊 Overall Route Summary)/g, '📊 *Overall Route Summary*')
  .replace(/(🧭 Final Recommendation)/g, '🧭 *Final Recommendation*')
  .replace(/(🌐 Google Maps Route Link)/g, '🌐 *Google Maps Route Link*')
  .replace(/(SafeRouter\.AI)/g, '*SafeRouter.AI*');

// --- Step 6: Add clear dividers between major sections ---
text = text
  .replace(/---/g, '\n────────────────────────\n')
  .replace(/\n{3,}/g, '\n\n')
  .trim();

// --- Step 7: WhatsApp message length guard ---
if (text.length > 3900) {
  text = text.slice(0, 3900) + "\n\n...message truncated for WhatsApp display.";
}

// --- Step 8: Output cleaned and formatted text ---
return [{
  json: {
    formattedMessage: text,
    userPhone: $json.userPhone || $json.message?.content?.user || null
  }
}];
