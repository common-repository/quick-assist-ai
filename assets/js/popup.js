function checkAdminStatus(callback) {
    jQuery.post(
        wpcoChat.ajaxurl,
        { action: 'wpco_check_admin_status' },
        function(response) {
            if (response.success) {
                callback(response.data);
            } else {
                callback(false);
            }
        }
    );
}

function toggleSendIcon(value) {
    const sendButton = document.getElementById('wpco-chat-send');
    sendButton.disabled = value.trim() === '';
}


function setWithExpiry(key, value) {
    const now = new Date();
    const expiry = new Date();
    expiry.setHours(23, 59, 59, 999); // Set expiry time to 11:59 PM
    const ttl = expiry.getTime() - now.getTime(); // Calculate the time to live (TTL)
    
    const item = {
        value: value,
        expiry: expiry.getTime(),
    }
    localStorage.setItem(key, JSON.stringify(item));
}

// Function to get an item and check its expiry time from localStorage
function getWithExpiry(key) {
    const itemStr = localStorage.getItem(key);
    if (!itemStr) {
        return null;
    }
    const item = JSON.parse(itemStr);
    const now = new Date();
    if (now.getTime() > item.expiry) {
        localStorage.removeItem(key);
        return null;
    }
    return item.value;
}


function storeChatHistory(query, response, original = null) {
    var chatHistory = getChatHistory();
    chatHistory.push({ role: "user", content: query});
    chatHistory.push({ role: "assistant", content: response});

    if (original)
        {
            chatHistory.push({role: "original_query", content: original});

        }
    setWithExpiry('wpco-chat-history', JSON.stringify(chatHistory));
}

function getChatHistory() {
    var history = getWithExpiry('wpco-chat-history');
    var parsedHistory = history ? JSON.parse(history) : [];
    var filteredHistory = parsedHistory.filter(entry => entry.role !== "original_query");
    return filteredHistory;
}

function getChatHistory_gpt() {
    var history = getWithExpiry('wpco-chat-history');
    var parsedHistory = history ? JSON.parse(history) : [];
    var parsedHistory1 = parsedHistory.filter(entry => entry.role !== "original_query");
    // Filter out items where the content is blank
    var filteredHistory = [];
    for (var i = 0; i < parsedHistory1.length; i += 2) {
        if (i + 1 < parsedHistory1.length) {
            var userEntry = parsedHistory1[i];
            var assistantEntry = parsedHistory1[i + 1];
            if (userEntry.content.trim() !== '' && assistantEntry.content.trim() !== '') {
                filteredHistory.push(userEntry);
                filteredHistory.push(assistantEntry);
            }
        }
    }
    
    // Get the latest 10 items
    var latestHistory = filteredHistory.slice(-6);
    return latestHistory;
}

window.onerror = function(message, source, lineno, colno, error) {
    console.log('Got Client Side Error'+message + ' at ' + source + ':' + lineno + ':' + colno);
};


jQuery(document).ready(function($) {

    const chatInput = document.getElementById('wpco-chat-input');

chatInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
});

    const processingMessage = document.getElementById('processingMessage');
    const progressBar = document.getElementById('progress-bar');

    function updateProgress(step) {
        const percentage = (step / 5) * 100;
        progressBar.style.width = percentage + '%';
    }

    function unescapeHtml(escapedStr) {
        
        if (typeof escapedStr === 'undefined' || escapedStr === null || escapedStr === '') {
            return 'Please wait, still processing';
        }

        if (typeof escapedStr !== 'string') {
            return escapedStr;
        }
    
        return escapedStr
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#039;/g, "'");
    }        
        
    function startListeningForUpdates(query, query_id) {
        //console.log("SSE connection started. ");
        
        function initiateSSE() {

        if (!!window.EventSource) {
            var source = new EventSource(wpcoChat.ajaxurl + '?action=wpco_sse&query_id=' + query_id + '&_wpnonce=' + wpcoChat.nonce);
            processingMessage.style.display = 'block';
            

            source.onmessage = function(event) {

                let data = unescapeHtml(event.data);

                $('#wpco-chat-output').append('<div class="wpco-chat-bubble-container"><img class="assistant-img" src="' + wpcoChat.plugin_url + 'assets/media/Assistant.jpeg" width="20" height="20" alt="Assistant"><div class="wpco-chat-bubble assistant">' + data + '</div></div>');
                var chatOutput = $('#wpco-chat-output');
                chatOutput.scrollTop(chatOutput[0].scrollHeight);
            };
    
            source.addEventListener('step', function(event) {
                let data = unescapeHtml(event.data);

                updateProgress(data);
            },false);

            source.addEventListener('update', function(event) {
                let data = unescapeHtml(event.data);

                $('#wpco-chat-output').append('<div class="wpco-chat-bubble-container"><img class="assistant-img" src="' + wpcoChat.plugin_url + 'assets/media/Assistant.jpeg" width="20" height="20" alt="Assistant"><div class="wpco-chat-bubble assistant">' + data + '</div></div>');
                var chatOutput = $('#wpco-chat-output');
                chatOutput.scrollTop(chatOutput[0].scrollHeight);
            }, false);

            source.addEventListener('error', function(event) {
                let data = unescapeHtml(event.data);

                $('#wpco-chat-output').append('<div class="wpco-chat-bubble-container"><img class="assistant-img" src="' + wpcoChat.plugin_url + 'assets/media/Assistant.jpeg" width="20" height="20" alt="Assistant"><div class="wpco-chat-bubble assistant">' + data + '</div></div>');
                var chatOutput = $('#wpco-chat-output');
                chatOutput.scrollTop(chatOutput[0].scrollHeight);
                $('#processingMessage').hide();
                source.close();
                if (event.readyState == EventSource.CLOSED) {
                    processingMessage.style.display = 'none';
                    progressBar.style.width = '0%';
                }
    
            }, false);

            source.addEventListener('complete', function(event) {
                let data = unescapeHtml(event.data);
                if (typeof data === 'undefined' || data === 'undefined') {
                data = event.data;
            }
                $('#wpco-chat-output').append('<div class="wpco-chat-bubble-container"><img class="assistant-img" src="' + wpcoChat.plugin_url + 'assets/media/Assistant.jpeg" width="20" height="20" alt="Assistant"><div class="wpco-chat-bubble assistant">' + data + '</div></div>');                
                storeChatHistory(query, unescapeHtml(event.data));
                            
                var chatOutput = $('#wpco-chat-output');
                var newContent = $('.wpco-chat-bubble').last();
                var newContentTop = newContent.position().top;
                chatOutput.scrollTop(chatOutput.scrollTop() + newContentTop);

                $('#processingMessage').hide();
                source.close(); // Close the connection when the task is complete
                processingMessage.style.display = 'none';
                progressBar.style.width = '0%';
                
            }, false);
            
            source.onerror = function(event) {

            //console.log("Error occurred:", event);
            initiateLongPolling(); // Switch to long polling
            
            };
        } else {
            console.log("Your browser doesn't support SSE.");
        }
    }

    function initiateLongPolling() {
        //console.log("Initiating long polling...");
    
        function poll() {
            $.ajax({
                url: wpcoChat.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpco_long_polling',
                    query_id: query_id,
                    _wpnonce: wpcoChat.nonce
                },
                success: function(data) {
                    //console.log("Received data1:", data);
                    data = JSON.parse(data); // Convert the string into a JavaScript object
                    if (data.status && data.status === 'not ready') {
                        // If the response indicates that the result is not ready, continue polling
                      //  console.log("Result not ready, polling again in 5 seconds...");
                        setTimeout(poll, 5000); // Poll again after 5 seconds
                    } else {
                        // If the response contains the data, handle it
                        //console.log("Received data2:", data);
                        $('#wpco-chat-output').append('<div class="wpco-chat-bubble-container"><img class="assistant-img" src="' + wpcoChat.plugin_url + 'assets/media/Assistant.jpeg" width="20" height="20" alt="Assistant"><div class="wpco-chat-bubble assistant">' + unescapeHtml(data) + '</div></div>');
                        var chatOutput = $('#wpco-chat-output');
                        chatOutput.scrollTop(chatOutput[0].scrollHeight);
                    }
                },
                error: function(xhr, status, error) {
                    console.log("Polling error:", status, error);
                    // Continue polling after error
                    setTimeout(poll, 5000);
                }
            });
        }
    
        poll(); // Start the polling process
    }    
    

    initiateSSE(); // Start with SSE

    }
    

    function isAdminLoggedIn(callback) {
        checkAdminStatus(callback);
    }
    
function displayChatHistory(history) {
    $('#wpco-chat-output').html('');
    history.forEach(function(chat) {
        if (chat.role === 'user' && chat.content !== ''){
        $('#wpco-chat-output').append('<div class="wpco-chat-bubble-container user"><div class="wpco-chat-bubble user">' + chat.content + '</div></div>')};
        if (chat.role === 'assistant' && chat.content !== ''){
        $('#wpco-chat-output').append('<div class="wpco-chat-bubble-container"><img class="assistant-img" src="' + wpcoChat.plugin_url + 'assets/media/Assistant.jpeg" width="20" height="20" alt="Assistant"><div class="wpco-chat-bubble assistant">' + chat.content + '</div></div>');}
    });

    var chatOutput = $('#wpco-chat-output');
    if (chatOutput.length > 0) {
    chatOutput.scrollTop(chatOutput[0].scrollHeight);
    } else {
    console.error('#wpco-chat-output element not found');
    }


}

// Handle starter button clicks
$(document).off('click.wpcoStarter').on('click.wpcoStarter', '.wpco-chat-bubble.starter', function() {
    var starterText = $(this).text();
        //console.log('Starter button clicked:', starterText);
        $('#wpco-chat-input').val(starterText);
        $('#wpco-chat-send').trigger('click');
    });
    
    // Open the popup
    $('.wpco-open-popup').on('click', function() {
        isAdminLoggedIn(function(isAdmin) {
            if (isAdmin) {
                sessionStorage.setItem('wpco-popup-open', 'flex');
                var popup = document.getElementById("wpco-popup");
                popup.style.display = "flex";
                $('#wpco-popup').fadeIn();
                $('#wpco-chat-icon').hide();
            } else {
                $('#wpco-popup').hide();
                $('#wpco-chat-icon').hide();
                console.log('User is not logged in as admin. Popup will not open.');
            }
        });
    });
    
    isAdminLoggedIn(function(isAdmin) {
        if (isAdmin) {
            // Display the icon if the user is an admin
            $('#wpco-chat-icon').show();  // Make sure the icon is initially hidden via CSS
        } else {
            console.log('User is not logged in as admin. Icon will not be shown.');
            $('#wpco-chat-icon').hide();  // Optional, in case it's rendered but should remain hidden
        }
    });
    
    $('#wpco-chat-icon').on('click', function() {
        var popup = document.getElementById("wpco-popup");
        popup.style.display = "flex";
        $('#wpco-popup').fadeIn();
        $('#wpco-chat-icon').hide();
    });
    
    // Close the popup
    $('.wpco-popup-close').on('click', function() {
        $('#wpco-popup').fadeOut();
        $('#wpco-chat-icon').show();
        sessionStorage.setItem('wpco-popup-open', 'none');
    });
    
    window.onbeforeunload = function () {
        isAdminLoggedIn(function(isAdmin) {
            if (isAdmin) {
        if (sessionStorage.getItem('wpco-popup-open') === 'flex') {
            var popup = document.getElementById("wpco-popup");
            popup.style.display = "flex";
            $('#wpco-popup').fadeIn();    
            $('#wpco-chat-icon').hide();
        }
    
        if (getWithExpiry('wpco-chat-history')) {
            var chatHistory = JSON.parse(getWithExpiry('wpco-chat-history'));
            displayChatHistory(chatHistory);
        }
    }
    else {
        $('#wpco-popup').hide();
        console.log('User is not logged in as admin. Popup will not open.');
    }
});

    };
    
    window.onload = function () {
        isAdminLoggedIn(function(isAdmin) {
            if (isAdmin) {
                if (sessionStorage.getItem('wpco-popup-open') === 'flex') {
                    var popup = document.getElementById("wpco-popup");
                    popup.style.display = "flex";  
                    $('#wpco-popup').fadeIn();
                    $('#wpco-chat-icon').hide();
                }
                if (getWithExpiry('wpco-chat-history')) {
                    var chatHistory = JSON.parse(getWithExpiry('wpco-chat-history'));
                    displayChatHistory(chatHistory);
                }

                if (!getWithExpiry('greetingShown') || (!getWithExpiry('wpco-chat-history'))) {
                    $('#wpco-chat-output').append('<div class="wpco-chat-bubble-container"><img class="assistant-img" src="' + wpcoChat.plugin_url + 'assets/media/Assistant.jpeg" width="20" height="20" alt="Assistant"><div class="wpco-chat-bubble assistant">Hi, welcome to Quick Assist AI! Please let me know how I can help you. Here are some suggested questions you can ask:' + 
                        '<div id="wpco-conversation-starters">' +
                        //'<span class="wpco-chat-bubble starter"></span>' +
                        '<span class="wpco-chat-bubble starter">How can I improve my homepage?</span>'+
                        //'<span class="wpco-chat-bubble starter">How do I manage inventory?</span>'+
                        '<span class="wpco-chat-bubble starter">Suggest ideas for blog posts that I should write?</span>'+
                        //'<span class="wpco-chat-bubble starter">How can I improve conversion?</span>'+
                        //'<span class="wpco-chat-bubble starter">How do I increase my sales?</span>'+
                        '<span class="wpco-chat-bubble starter">Why is my site is so slow? What can I do to improve it?</span>'+
                        '<span class="wpco-chat-bubble starter">Please evaluate the overall health of my site and give me recommendations</span></div></div>');
                    var chatOutput = $('#wpco-chat-output');
                    chatOutput.scrollTop(chatOutput[0].scrollHeight);
                    setWithExpiry('greetingShown', 'true');
                }
            } else {
                $('#wpco-popup').hide();
                console.log('User is not logged in as admin. Popup will not open.');
            }
        });
    };
    
    // Close the popup when clicking outside of the content area
    $(window).on('click', function(event) {
        if ($(event.target).is('#wpco-popup')) {
            $('#wpco-popup').fadeOut();
        }
    });

    $('#wpco-chat-send').off('click');

    // Send chat message
    $('#wpco-chat-send').off('click').on('click', function(event)  {
        event.preventDefault(); // Prevent default form submission if inside a form

        var query = $('#wpco-chat-input').val();
        if (query.trim() === '') return;
        
        
        $('#wpco-chat-output').append('<div class="wpco-chat-bubble-container user"><div class="wpco-chat-bubble user">' + query + '</div></div>');
        $('#wpco-chat-input').val('');

        var chatOutput = $('#wpco-chat-output');
        if (chatOutput.length > 0) {
        chatOutput.scrollTop(chatOutput[0].scrollHeight);
        } else {
        console.error('#wpco-chat-output element not found');
        }


        var chatHistory = JSON.stringify(getChatHistory_gpt() || []);

        $.post(
            wpcoChat.ajaxurl,
            {   
                action: 'wpco_chat',
                query: query,
                _wpnonce: wpcoChat.nonce,
                post_id: wpcoChat.post_id,
                //history: chatHistory, // Send chat history
            },
            function(response) {
                if (response.success) {
                    var query_id = response.data.query_id;
                    startListeningForUpdates(query,query_id); // Start listening for updates
                } else {
                    console.error('Error starting process:', response.data);
                }
            }
        ).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX call failed: ', textStatus, errorThrown);
        });
   
        $('#processingMessage').show();
});

    $('#wpco-chat-input').off('keydown').on('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $('#wpco-chat-send').trigger('click');

        }
    });
    

});