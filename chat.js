// chat.js
const chatLog = document.getElementById('chatLog');
const userInput = document.getElementById('userInput');

// Replace with your OpenAI API key
const OPENAI_API_KEY = "your-api-key-here";

async function sendMessage() {
    const message = userInput.value.trim();
    if (!message) return;

    addMessage("You", message, "user");
    userInput.value = "";

    const response = await getAIResponse(message);
    addMessage("Bot", response, "bot");
}

function addMessage(sender, message, className) {
    const p = document.createElement('p');
    p.innerHTML = `<span class="${className}">${sender}:</span> ${message}`;
    chatLog.appendChild(p);
    chatLog.scrollTop = chatLog.scrollHeight;
}

async function getAIResponse(prompt) {
    const res = await fetch("https://api.openai.com/v1/chat/completions", {
        method: "POST",
        headers: {
            "Authorization": `Bearer ${OPENAI_API_KEY}`,
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            model: "gpt-3.5-turbo",
            messages: [{ role: "user", content: prompt }]
        })
    });

    const data = await res.json();
    return data.choices[0].message.content;
}