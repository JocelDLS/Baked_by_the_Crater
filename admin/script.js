/**
 * dashboard_script.js
 * Basic JavaScript for the Dashboard Chat Functionality (Always Visible)
 */

function handleChatInput(event) {
  // Check if the Enter key was pressed (key code 13)
  if (event.key === "Enter") {
    event.preventDefault();
    sendMessage();
  }
}

function sendMessage() {
  const inputField = document.getElementById("chat-input-field");
  const messageText = inputField.value.trim();

  if (messageText === "") {
    return; // Don't send empty messages
  }

  const chatBody = document.getElementById("chatMessages");

  // 1. Create and append the user message element
  const userMessageDiv = document.createElement("div");
  userMessageDiv.classList.add("message", "user-msg");
  userMessageDiv.textContent = messageText;
  chatBody.appendChild(userMessageDiv);

  // 2. Clear the input field
  inputField.value = "";

  // 3. Scroll to the bottom of the chat window
  chatBody.scrollTop = chatBody.scrollHeight;

  // POST the admin message to the server asynchronously (avoids full page reload)
  const form = document.querySelector('.chat-input-form');
  if (form) {
    fetch(form.action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'message_text=' + encodeURIComponent(messageText)
    }).then(resp => {
      if (!resp.ok) throw new Error('Network response was not ok');
      // message saved on the server; no further action required here
    }).catch(err => {
      console.error('Failed to save admin message:', err);
    });
  }
}
