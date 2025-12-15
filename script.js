const CHAT_WINDOW_ID = "chat-window";
const CHAT_LAUNCHER_ID = "chat-launcher";
const CHAT_MESSAGES_ID = "chat-messages";
const CHAT_INPUT_ID = "chat-input-field";

// --- Utility Functions ---

/**
 * Creates and appends a message to the chat window.
 * @param {string} text - The message content.
 * @param {string} type - 'sent' (user) or 'received' (admin).
 */
function appendMessage(text, type) {
  const messages = document.getElementById(CHAT_MESSAGES_ID);
  const newMessage = document.createElement("div");
  newMessage.className = `chat-message ${type}`;

  // Build message HTML with timestamp
  const time = new Date();
  const hh = String(time.getHours()).padStart(2, "0");
  const mm = String(time.getMinutes()).padStart(2, "0");
  const timeStr = `${hh}:${mm}`;

  newMessage.innerHTML = `
    <div class="message-bubble">
      <p class="message-text"></p>
      <span class="message-time">${timeStr}</span>
    </div>`;
  newMessage.querySelector('.message-text').innerText = text;

  messages.appendChild(newMessage);

  // Scroll to the bottom of the message area
  messages.scrollTop = messages.scrollHeight;

  return newMessage; // return element so caller can update delivery status
}

// --- FUNCTION: Sends data to PHP for DB saving ---

/**
 * Sends the message (user or admin) to the server to be saved in the database.
 * @param {string} messageText - The message content.
 * @param {string} type - 'sent' (user) or 'received' (admin).
 */
function saveChatToDB(messageText, type) {
  // Safety check for User ID
  if (
    typeof CURRENT_USER_ID === "undefined" ||
    CURRENT_USER_ID === null ||
    CURRENT_USER_ID === ""
  ) {
    console.error(
      "Error: CURRENT_USER_ID is not defined. Cannot save chat by account."
    );
    return Promise.reject(new Error('Missing CURRENT_USER_ID'));
  }

  return fetch("save_chat.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    // Convert the JavaScript object to a JSON string for the body
    body: JSON.stringify({
      message: messageText,
      type: type,
      user_id: CURRENT_USER_ID, // Ensure this is always included
    }),
  })
    .then((response) => {
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return response.json();
    })
    .then((data) => {
      if (data.status === "error") throw new Error(data.message || "Server error");
      return data;
    });
}

// --- Event Handlers ---

/**
 * Handles the send button click.
 */
function sendMessage() {
  const inputField = document.getElementById(CHAT_INPUT_ID);
  const message = inputField.value.trim();

  // FIX: Exit immediately if message or user ID is invalid/missing
  if (
    message === "" ||
    typeof CURRENT_USER_ID === "undefined" ||
    CURRENT_USER_ID === null ||
    CURRENT_USER_ID === ""
  ) {
    console.warn(
      "Cannot send message: Message is empty or User ID is missing."
    );
    // Optionally provide user feedback here
    return;
  }

  // 1. Display user message and mark pending
  const el = appendMessage(message, "sent");
  el.classList.add('pending');

  // 2. Save user message immediately as 'sent' and update delivery state
  saveChatToDB(message, "sent")
    .then(() => {
      el.classList.remove('pending');
      el.classList.add('delivered');
    })
    .catch((err) => {
      el.classList.remove('pending');
      el.classList.add('failed');
      console.error('Failed to save chat:', err);
    });

  // 3. Clear input field
  inputField.value = "";
}

/**
 * Handles 'Enter' key press in the input field.
 * @param {Event} event
 */
function handleChatInput(event) {
  if (event.key === "Enter") {
    event.preventDefault();
    sendMessage();
  }
}

/**
 * Toggles the visibility of the chat window and launcher button,
 * and loads the chat history from CHAT_HISTORY (DB data).
 */
function toggleChatWindow() {
  const chatWindow = document.getElementById(CHAT_WINDOW_ID);
  const chatLauncher = document.getElementById(CHAT_LAUNCHER_ID);

  if (chatWindow && chatLauncher) {
    if (chatWindow.classList.contains("hidden")) {
      // Show the window
      chatWindow.classList.remove("hidden");
      chatLauncher.style.display = "none"; // Hide button

      // Load history from DB on open
      const messages = document.getElementById(CHAT_MESSAGES_ID);
      // Only load history if the chat body is empty
      if (messages && messages.children.length === 0) {
        if (typeof CHAT_HISTORY !== "undefined" && CHAT_HISTORY.length > 0) {
          // Populate messages from the DB data
          CHAT_HISTORY.forEach((msg) => {
            appendMessage(msg.text, msg.type);
          });
        } else {
          // Fallback if DB is empty for this user
          appendMessage(
            "Welcome! You are connected to our Admin Support Chat.",
            "received"
          );
          appendMessage(
            "Please send us your question about an order, delivery, or general inquiry. A staff member will reply shortly.",
            "received"
          );
        }
      }
    } else {
      // Hide the window
      chatWindow.classList.add("hidden");
      chatLauncher.style.display = "block"; // Show button
    }
  }
}

// --- Initialization ---
// Example of the code that causes the message to disappear automatically
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');

    alerts.forEach(function(alert) {
        // This is the line that makes it disappear after 5000 milliseconds (5 seconds)
        setTimeout(function() {
            alert.style.display = 'none'; // Or alert.remove();
        }, 5000); 
    });
});

document.addEventListener("DOMContentLoaded", function () {
  // 1. Define the variables for sharing
  const pageUrl = encodeURIComponent(window.location.href);
  const shareTitle = encodeURIComponent(
    "Baked by the Crater: Artisan Bread You'll Love!"
  );
  const shareText = encodeURIComponent(
    "You have to try this amazing local bakery, Baked by the Crater! Their bread is incredible. Check them out:"
  );
  const sourceName = encodeURIComponent("Baked by the Crater");

  // 2. Get the elements
  const facebookBtn = document.getElementById("share-facebook");
  const twitterBtn = document.getElementById("share-twitter");
  const linkedinBtn = document.getElementById("share-linkedin");
  const emailBtn = document.getElementById("share-email");

  // 3. Set the dynamic share URLs

  // Facebook: https://www.facebook.com/sharer/sharer.php?u=[URL]
  if (facebookBtn) {
    facebookBtn.href = `https://www.facebook.com/sharer/sharer.php?u=${pageUrl}`;
  }

  // Twitter: https://twitter.com/intent/tweet?text=[TEXT]&url=[URL]
  if (twitterBtn) {
    twitterBtn.href = `https://twitter.com/intent/tweet?text=${shareText}&url=${pageUrl}`;
  }

  // LinkedIn: https://www.linkedin.com/shareArticle?mini=true&url=[URL]&title=[TITLE]&summary=[SUMMARY]&source=[SOURCE]
  if (linkedinBtn) {
    linkedinBtn.href = `https://www.linkedin.com/shareArticle?mini=true&url=${pageUrl}&title=${shareTitle}&summary=${shareText}&source=${sourceName}`;
  }

  // Email: mailto:?subject=[TITLE]&body=[BODY_TEXT]
  if (emailBtn) {
    const emailBody = `Check out the amazing local bakery, Baked by the Crater: ${decodeURIComponent(
      pageUrl
    )}`;
    emailBtn.href = `mailto:?subject=${shareTitle}&body=${encodeURIComponent(
      emailBody
    )}`;
  }
});

/**
 * cart.js
 * Handles dynamic updates for the shopping cart page (cart.php).
 * - Updates item subtotal when quantity changes.
 * - Recalculates and updates the Order Summary (Subtotal, Grand Total) dynamically.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Select all quantity input fields within the cart form
    const qtyInputs = document.querySelectorAll('.qty-input');

    // Get the shipping cost from the PHP-rendered summary. We'll use this fixed value for dynamic calcs.
    // We assume the cart-summary div is present if there are items.
    const shippingLine = document.querySelector('.cart-summary .summary-line:nth-child(2) .value');
    // Extracts the float value, removing '₱', ','. Default to 0 if not found.
    const SHIPPING_COST = shippingLine 
        ? parseFloat(shippingLine.textContent.replace(/[₱,]/g, '')) 
        : 0.00; 

    /**
     * Helper function to format a number as a currency string.
     * @param {number} amount
     * @returns {string} Formatted currency string (e.g., "₱1,234.56")
     */
    const formatCurrency = (amount) => {
        return `₱${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }; 

    /**
     * Recalculates the total cost for the entire cart and updates the summary box.
     */
    const updateCartSummary = () => {
        let newTotalCost = 0.00;
        
        // Iterate over all cart items to sum their subtotals
        document.querySelectorAll('.cart-item').forEach(itemElement => {
            const subtotalElement = itemElement.querySelector('.item-subtotal .subtotal-amount');
            // Extract the subtotal amount from the DOM (it should already be updated by updateItemSubtotal)
            const subtotal = parseFloat(subtotalElement.textContent.replace(/[₱,]/g, ''));
            newTotalCost += subtotal;
        });

        // 1. Calculate Grand Total
        const newGrandTotal = newTotalCost + SHIPPING_COST;

        // 2. Update Summary DOM elements
        const subtotalElement = document.querySelector('.cart-summary .summary-line:nth-child(1) .value');
        const grandTotalElement = document.querySelector('.cart-summary .total-line .value');

        if (subtotalElement) {
            subtotalElement.textContent = formatCurrency(newTotalCost);
        }
        if (grandTotalElement) {
            grandTotalElement.textContent = formatCurrency(newGrandTotal);
        }
    };


    /**
     * Updates the subtotal for a single item when its quantity changes.
     * @param {HTMLInputElement} inputElement The quantity input field that changed.
     */
    const updateItemSubtotal = (inputElement) => {
        // Find the parent .cart-item container
        const cartItem = inputElement.closest('.cart-item');
        if (!cartItem) return;

        // Get the current quantity and price
        const quantity = parseInt(inputElement.value) || 0;
        
        // Price is rendered as part of a <p> tag, we need to extract it.
        const priceElement = cartItem.querySelector('.item-price');
        // Regex to find a number after 'Price: ₱'
        const priceMatch = priceElement.textContent.match(/Price:\s*₱([\d,]+\.?\d*)/); 
        const price = priceMatch ? parseFloat(priceMatch[1].replace(/,/g, '')) : 0.00;

        // Find the subtotal display element
        const subtotalElement = cartItem.querySelector('.item-subtotal .subtotal-amount');

        // Calculate and update the subtotal
        const subtotal = price * quantity;
        if (subtotalElement) {
            subtotalElement.textContent = formatCurrency(subtotal);
        }
        
        // After updating the item's subtotal, recalculate the entire cart summary
        updateCartSummary();
    };

    // Attach event listeners to all quantity inputs
    qtyInputs.forEach(input => {
        // Listen for input (immediate change) and change (when focus leaves) events
        input.addEventListener('input', () => updateItemSubtotal(input));
        input.addEventListener('change', () => updateItemSubtotal(input));

        // Ensure minimum quantity is 1 if it's currently 0 or less, as per PHP logic
        input.addEventListener('blur', () => {
            if (parseInt(input.value) <= 0) {
                input.value = 1;
                updateItemSubtotal(input); // Recalculate if it was corrected
            }
        });
    });

    // Optional: Add a confirmation dialog for the 'Remove' links
    document.querySelectorAll('.remove-link').forEach(link => {
        link.addEventListener('click', (event) => {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                event.preventDefault(); // Stop navigation if the user cancels
            }
        });
    });

    // Optional: Confirm Clear Cart action
    const clearCartLink = document.querySelector('.btn-danger[href*="action=clear"]');
    if (clearCartLink) {
      clearCartLink.addEventListener('click', (event) => {
        if (!confirm('Are you sure you want to clear your cart? This action cannot be undone.')) {
          event.preventDefault();
        }
      });
    }
});