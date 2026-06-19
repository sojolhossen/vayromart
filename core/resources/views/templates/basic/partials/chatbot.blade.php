@php
    $general = gs();
    $chatbotSettings = [];
    if ($general->chatbot_settings) {
        $chatbotSettings = is_string($general->chatbot_settings) 
            ? json_decode($general->chatbot_settings, true) 
            : (array)$general->chatbot_settings;
    }
    $botName = $chatbotSettings['bot_name'] ?? 'VayroBot';
    $welcomeMsg = $chatbotSettings['welcome_message'] ?? 'Hello! How can I help you today?';
@endphp

<!-- Chatbot Floating Launcher -->
<div id="ai-chatbot-launcher" class="ai-chatbot-launcher">
    <div class="launcher-icon">
        <i class="las la-comments"></i>
    </div>
    <div class="launcher-close-icon d-none">
        <i class="las la-times"></i>
    </div>
</div>

<!-- Chatbot Container Widget -->
<div id="ai-chatbot-container" class="ai-chatbot-container d-none">
    <!-- Chat Header -->
    <div class="chat-header">
        <div class="d-flex align-items-center gap-2">
            <div class="bot-avatar">
                <i class="las la-robot"></i>
                <span class="bot-status-indicator"></span>
            </div>
            <div>
                <h6 class="bot-name text-white m-0">{{ $botName }}</h6>
                <span class="bot-status-text">AI Support Agent (Online)</span>
            </div>
        </div>
        <button id="ai-chatbot-close" class="chat-close-btn">
            <i class="las la-times"></i>
        </button>
    </div>

    <!-- Chat Body -->
    <div id="chat-body-messages" class="chat-body">
        <!-- Messages will load dynamically -->
    </div>

    <!-- Suggestion Chips Container -->
    <div class="chat-suggestions-container">
        <div class="chat-suggestions" id="chat-suggestions-wrapper">
            <button class="suggestion-chip" data-msg="আমি একটি পণ্য অর্ডার করতে চাই">🛒 Place Order</button>
            <button class="suggestion-chip" data-msg="Track my order OID-">📦 Track Order</button>
            <button class="suggestion-chip" data-msg="Do you have any discounts or coupons?">🎟️ Coupons</button>
            <button class="suggestion-chip" data-msg="What are your popular products?">🔥 Best Sellers</button>
            <button class="suggestion-chip" data-msg="What is your return policy?">📄 Return Policy</button>
            <button class="suggestion-chip" data-msg="How can I contact support?">📞 Support Contact</button>
        </div>
    </div>

    <!-- Chat Footer -->
    <div class="chat-footer">
        <div id="ai-chatbot-form-wrapper" class="w-100">
            <div class="input-group">
                <input type="text" id="ai-chatbot-input" class="form-control chat-input" placeholder="Type your message..." autocomplete="off">
                <button type="button" class="btn chat-send-btn" id="ai-chatbot-send">
                    <i class="las la-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
    (function($) {
        "use strict";

        const launcher = $('#ai-chatbot-launcher');
        const container = $('#ai-chatbot-container');
        const closeBtn = $('#ai-chatbot-close');
        const sendBtn = $('#ai-chatbot-send');
        const chatInput = $('#ai-chatbot-input');
        const messagesWrapper = $('#chat-body-messages');
        const suggestionsWrapper = $('.chat-suggestions-container');
        
        let isOpen = false;
        const storageKey = 'vayromart_ai_chat_history';

        // Load chat from localStorage or show welcome message
        function initChat() {
            const history = getLocalHistory();
            if (history && history.length > 0) {
                history.forEach(function(msg) {
                    appendMessageHtml(msg.sender, msg.message, false);
                });
            } else {
                // Show default welcome message
                const welcomeMessage = "{{ $welcomeMsg }}";
                appendMessageHtml('bot', welcomeMessage, true);
            }
            scrollToBottom();
        }

        // Toggle chat window visibility
        launcher.on('click', function() {
            isOpen = !isOpen;
            if (isOpen) {
                container.removeClass('d-none').addClass('chat-slide-up');
                launcher.find('.launcher-icon').addClass('d-none');
                launcher.find('.launcher-close-icon').removeClass('d-none');
                chatInput.focus();
                scrollToBottom();
            } else {
                container.addClass('d-none').removeClass('chat-slide-up');
                launcher.find('.launcher-icon').removeClass('d-none');
                launcher.find('.launcher-close-icon').addClass('d-none');
            }
        });

        closeBtn.on('click', function() {
            launcher.trigger('click');
        });

        let isBusy = false;

        function handleMessageSubmit() {
            if (isBusy) return;

            const message = chatInput.val().trim();
            if (!message) return;

            isBusy = true;
            chatInput.prop('disabled', true);
            sendBtn.prop('disabled', true).addClass('opacity-50');

            // Append user message
            appendMessageHtml('user', message, true);
            chatInput.val('');
            scrollToBottom();

            // Show typing indicator
            showTypingIndicator();
            scrollToBottom();

            // Hide suggestions chips temporarily during response load
            suggestionsWrapper.addClass('d-none');

            // API request to Laravel chatbot controller
            let absoluteUrl = "{{ route('chatbot.message') }}";
            let ajaxUrl = absoluteUrl;
            if (absoluteUrl.startsWith('http://') || absoluteUrl.startsWith('https://')) {
                try {
                    let parsedUrl = new URL(absoluteUrl);
                    ajaxUrl = window.location.protocol + '//' + window.location.host + parsedUrl.pathname + parsedUrl.search;
                } catch(e) {
                    ajaxUrl = absoluteUrl.replace(/^https?:\/\/[^\/]+/i, '');
                }
            }

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    message: message
                },
                dataType: 'json',
                success: function(response) {
                    isBusy = false;
                    chatInput.prop('disabled', false);
                    sendBtn.prop('disabled', false).removeClass('opacity-50');
                    chatInput.focus();

                    removeTypingIndicator();
                    suggestionsWrapper.removeClass('d-none');

                    if (response.success) {
                        appendMessageHtml('bot', response.message, true);
                    } else {
                        appendMessageHtml('bot', "দুঃখিত, কোনো ত্রুটি ঘটেছে। অনুগ্রহ করে আবার চেষ্টা করুন।", true);
                    }
                    scrollToBottom();
                },
                error: function(xhr, status, error) {
                    isBusy = false;
                    chatInput.prop('disabled', false);
                    sendBtn.prop('disabled', false).removeClass('opacity-50');
                    chatInput.focus();

                    removeTypingIndicator();
                    suggestionsWrapper.removeClass('d-none');
                    
                    let errorMsg = "কানেকশন এরর! অনুগ্রহ করে ইন্টারনেট কানেকশন চেক করুন।";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    appendMessageHtml('bot', errorMsg, true);
                    scrollToBottom();
                }
            });
        }

        // Click on suggestion chips
        $(document).on('click', '.suggestion-chip', function() {
            const query = $(this).data('msg');
            chatInput.val(query);
            chatInput.focus();
            
            // If the suggestion is order tracking, don't send immediately, let them input order ID
            if (query === 'Track my order OID-') {
                return;
            }

            handleMessageSubmit();
        });

        // Trigger on button click
        sendBtn.on('click', function() {
            handleMessageSubmit();
        });

        // Trigger on Enter keypress
        chatInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                handleMessageSubmit();
            }
        });

        // Helper to format text/links
        function formatMarkdownLinks(text) {
            if (typeof text !== 'string') text = String(text || '');
            // Match markdown links: [link text](url)
            const mdLinkRegex = /\[([^\]]+)\]\((https?:\/\/[^\s]+)\)/g;
            let formatted = text.replace(mdLinkRegex, '<a href="$2" target="_blank" class="chat-link">$1</a>');
            
            // Convert plain urls if any remaining
            const urlRegex = /(?<!href="|">)(https?:\/\/[^\s<]+)/g;
            formatted = formatted.replace(urlRegex, '<a href="$1" target="_blank" class="chat-link">$1</a>');
            
            // Line breaks
            formatted = formatted.replace(/\n/g, '<br>');
            
            return formatted;
        }

        // Append message to HTML
        function appendMessageHtml(sender, message, saveToStorage = true) {
            const isBot = sender === 'bot';
            const alignClass = isBot ? 'justify-content-start' : 'justify-content-end';
            const bubbleClass = isBot ? 'bot-bubble' : 'user-bubble';
            const formattedMessage = isBot ? formatMarkdownLinks(message) : escapeHtml(message);

            const messageHtml = `
                <div class="d-flex ${alignClass} mb-2 animated fadeIn">
                    <div class="message-bubble ${bubbleClass}">
                        <div class="message-text">${formattedMessage}</div>
                    </div>
                </div>
            `;
            messagesWrapper.append(messageHtml);

            if (saveToStorage) {
                saveToLocalStorage(sender, message);
            }
        }

        // Typing Indicator Helpers
        function showTypingIndicator() {
            const typingHtml = `
                <div id="chatbot-typing-indicator" class="d-flex justify-content-start mb-2 animated fadeIn">
                    <div class="message-bubble bot-bubble typing-bubble">
                        <div class="typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            `;
            messagesWrapper.append(typingHtml);
        }

        function removeTypingIndicator() {
            $('#chatbot-typing-indicator').remove();
        }

        // Scroll helper
        function scrollToBottom() {
            messagesWrapper.scrollTop(messagesWrapper[0].scrollHeight);
        }

        // Escaping helper
        function escapeHtml(text) {
            if (typeof text !== 'string') text = String(text || '');
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // LocalStorage helpers
        function getLocalHistory() {
            const data = localStorage.getItem(storageKey);
            return data ? JSON.parse(data) : [];
        }

        function saveToLocalStorage(sender, message) {
            const history = getLocalHistory();
            history.push({ sender: sender, message: message });
            // Cap history to 50 items
            if (history.length > 50) {
                history.shift();
            }
            localStorage.setItem(storageKey, JSON.stringify(history));
        }

        // Initialize Chat
        initChat();

    })(jQuery);
</script>
@endpush
