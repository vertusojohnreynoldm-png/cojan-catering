<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cojan Catering')</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
    <style>
        /* ── MOBILE NAV ── */
        .cj-nav {
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: nowrap;
        }
        .cj-nav-brand { font-size: clamp(.9rem, 4vw, 1.2rem); white-space: nowrap; }
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 8px;
            background: none;
            border: none;
            z-index: 1100;
        }
        .hamburger span {
            display: block;
            width: 24px;
            height: 2px;
            background: #fff;
            border-radius: 2px;
            transition: all .3s;
        }
        .cj-nav-links {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }
        @media (max-width: 768px) {
            .hamburger { display: flex; }
            .cj-nav-links {
                display: none;
                position: fixed;
                top: 0; left: 0;
                width: 100vw;
                height: 100vh;
                background: var(--green-dark, #7A2E1D);
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 1.2rem;
                z-index: 1050;
            }
            .cj-nav-links.open { display: flex; }
            .cj-nav-links a, .cj-nav-links button {
                font-size: 1.1rem !important;
                padding: .75rem 2rem !important;
                width: 200px;
                text-align: center;
            }
            .cj-page { padding: 1rem !important; }
            /* Stack grid to single column on mobile */
            .row > [class*="col-sm"], .row > [class*="col-lg"] {
                flex: 0 0 100%;
                max-width: 100%;
            }
            /* Menu cards full width */
            .menu-card { margin-bottom: .5rem; }
            /* Tables scroll horizontally */
            .table-responsive { overflow-x: auto; }
            /* Stat cards 2 per row */
            .stat-card { padding: 1rem; }
            .stat-num { font-size: 1.8rem !important; }
        }
        @media (max-width: 480px) {
            .cj-page-title { font-size: 1.4rem !important; }
            .menu-card-name { font-size: 1rem; }
        }
        /* Close button inside mobile menu */
        .nav-close {
            display: none;
            position: absolute;
            top: 1.2rem;
            right: 1.2rem;
            color: #fff;
            font-size: 2rem;
            cursor: pointer;
            background: none;
            border: none;
            z-index: 1200;
        }
        @media (max-width: 768px) { .nav-close { display: block; } }
    </style>
    @yield('styles')
</head>
<body>

<nav class="cj-nav">
    <a href="{{ route('customer.dashboard') }}" class="cj-nav-brand">🍽 Cojan <span>Catering</span></a>
    <button class="hamburger" onclick="toggleNav()" aria-label="Open menu" aria-expanded="false" aria-controls="mobileNav">
        <span></span><span></span><span></span>
    </button>
    <div class="cj-nav-links" id="mobileNav">
        <button class="nav-close" onclick="toggleNav()" aria-label="Close menu">&times;</button>
        <a href="{{ route('customer.menu') }}" class="btn-cj-outline btn-cj btn-cj-sm" onclick="toggleNav()">Menu</a>
        <a href="{{ route('customer.packages.index') }}" class="btn-cj-outline btn-cj btn-cj-sm" onclick="toggleNav()">📦 Packages</a>
        <a href="{{ route('customer.cart') }}" class="btn-cj-outline btn-cj btn-cj-sm" onclick="toggleNav()">🛒 Cart</a>
        <a href="{{ route('customer.orders') }}" class="btn-cj-outline btn-cj btn-cj-sm" onclick="toggleNav()">My Orders</a>
        <button type="button" class="btn-cj-amber btn-cj btn-cj-sm" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
    </div>
</nav>
<x-logout-modal />

<x-toast />

<div class="cj-page">
    @yield('content')
</div>

<footer class="cj-footer" id="footer-contact">
    <div class="cj-footer-grid">
        <div>
            <h6>🍽 Cojan Catering</h6>
            <p>Fresh Filipino cuisine, delivered with care — proudly serving San Jose, Occidental Mindoro.</p>
            <div class="cj-footer-social">
                <a href="https://www.facebook.com/share/1bNTNuqknZ/?mibextid=wwXIfr" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
            </div>
        </div>
        <div>
            <h6>Visit Us</h6>
            <ul>
                <li><i class="bi bi-geo-alt"></i> San Jose, Occidental Mindoro, Philippines</li>
                <li>Online ordering: 7:30 AM – 8:00 PM daily</li>
                <li>Catering packages: available 24/7 for advance booking</li>
            </ul>
        </div>
        <div>
            <h6>Quick Links</h6>
            <ul>
                <li><a href="{{ route('customer.menu') }}">Menu</a></li>
                <li><a href="{{ route('customer.packages.index') }}">Catering Packages</a></li>
                <li><a href="{{ route('customer.orders') }}">My Orders</a></li>
                <li><a href="#footer-contact">Visit Us</a></li>
            </ul>
        </div>
    </div>
    <div class="cj-footer-bottom">
        © {{ date('Y') }} Cojan Catering Services. All rights reserved.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])

<script>
function toggleNav() {
    const nav = document.getElementById('mobileNav');
    const isOpen = nav.classList.toggle('open');
    document.querySelector('.hamburger').setAttribute('aria-expanded', String(isOpen));
    document.querySelector('.hamburger').setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
}
// Close nav when clicking outside
document.addEventListener('click', function(e) {
    const nav = document.getElementById('mobileNav');
    const hamburger = document.querySelector('.hamburger');
    if (nav.classList.contains('open') && !nav.contains(e.target) && !hamburger.contains(e.target)) {
        toggleNav();
    }
});
// Close nav (and, separately, the chat panel) on Escape
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Escape') return;
    const nav = document.getElementById('mobileNav');
    if (nav.classList.contains('open')) {
        toggleNav();
        document.querySelector('.hamburger').focus();
    }
    if (typeof chatOpen !== 'undefined' && chatOpen) {
        toggleChat();
    }
});
</script>

<!-- ========== CUSTOMER CHAT BUBBLE ========== -->
<button type="button" id="chat-bubble" onclick="toggleChat()" class="cj-bubble-idle"
    aria-haspopup="dialog" aria-expanded="false" aria-label="Open chat"
    style="position:fixed;bottom:28px;right:28px;z-index:9999;
           width:58px;height:58px;border-radius:50%;border:none;padding:0;font:inherit;
           background:linear-gradient(135deg,#7A2E1D,#C1441E);
           box-shadow:0 4px 20px rgba(0,0,0,0.25);
           display:flex;align-items:center;justify-content:center;
           cursor:pointer;">
    <span class="cj-bubble-icon cj-icon-chat" style="color:#fff;font-size:1.5rem;" aria-hidden="true">💬</span>
    <span class="cj-bubble-icon cj-icon-close" style="color:#fff;font-size:1.5rem;" aria-hidden="true">✕</span>
    <span id="chat-badge" aria-hidden="true"
          style="display:none;position:absolute;top:2px;right:2px;
                 background:#e74c3c;color:#fff;border-radius:50%;
                 width:18px;height:18px;font-size:11px;font-weight:700;
                 align-items:center;justify-content:center;">0</span>
</button>

<div id="chat-window" class="cj-chat-panel"
    style="display:none;position:fixed;z-index:9998;
           background:#fff;font-family:'DM Sans',sans-serif;
           flex-direction:column;
           /* Desktop */
           bottom:100px;right:28px;width:340px;height:460px;
           overflow:hidden;
           box-shadow:0 8px 32px rgba(0,0,0,0.22);">
    <div style="background:linear-gradient(135deg,#7A2E1D,#C1441E);padding:14px 18px;
                display:flex;align-items:center;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;border-radius:50%;
                        background:rgba(255,255,255,0.3);
                        display:flex;align-items:center;justify-content:center;font-size:1.1rem;">🍽</div>
            <div>
                <div style="color:#fff;font-weight:700;font-size:.95rem;">Cojan Support</div>
                <div style="color:rgba(255,255,255,0.8);font-size:.75rem;">We usually reply instantly</div>
            </div>
        </div>
        <button type="button" onclick="toggleChat()" aria-label="Close chat"
                style="background:none;border:none;padding:0;color:#fff;cursor:pointer;font-size:1.4rem;font:inherit;">&times;</button>
    </div>
    <div id="chat-messages" class="cj-chat-messages"
        style="flex:1;overflow-y:auto;padding:14px;display:flex;
               flex-direction:column;gap:8px;"></div>
    <div style="padding:10px 12px;border-top:1px solid #eee;display:flex;gap:8px;background:#fff;">
        <input id="chat-input" type="text" placeholder="Type a message..." aria-label="Type a message"
            style="flex:1;border:1.5px solid #C1441E;border-radius:20px;
                   padding:8px 14px;font-size:.88rem;outline:none;"
            onkeydown="if(event.key==='Enter') sendCustomerMessage()">
        <button onclick="sendCustomerMessage()" aria-label="Send message"
            style="background:linear-gradient(135deg,#7A2E1D,#C1441E);border:none;
                   border-radius:50%;width:38px;height:38px;color:#fff;cursor:pointer;
                   display:flex;align-items:center;justify-content:center;font-size:1rem;">➤</button>
    </div>
</div>

<style>
/* Chat window full screen on mobile */
@media (max-width: 768px) {
    #chat-window {
        bottom: 0 !important;
        right: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        border-radius: 0 !important;
    }
    #chat-bubble {
        bottom: 20px !important;
        right: 20px !important;
    }
}

/* Bubble icon morph (chat <-> close) and panel expand — state values apply
   regardless of motion preference; only the smooth transition/animation
   itself is gated below, so reduced-motion users get an instant snap. */
.cj-bubble-icon {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.cj-icon-chat { transform: rotate(0deg); opacity: 1; }
.cj-icon-close { transform: rotate(-90deg); opacity: 0; }
#chat-bubble.cj-chat-open .cj-icon-chat { transform: rotate(90deg); opacity: 0; }
#chat-bubble.cj-chat-open .cj-icon-close { transform: rotate(0deg); opacity: 1; }

#chat-window {
    transform-origin: bottom right;
    transform: scale(0.85) translateY(10px);
    opacity: 0;
}
#chat-window.cj-panel-open {
    transform: scale(1) translateY(0);
    opacity: 1;
}

@media (prefers-reduced-motion: no-preference) {
    .cj-bubble-icon { transition: transform .25s ease, opacity .25s ease; }
    #chat-window { transition: transform .28s ease-out, opacity .28s ease-out; }

    #chat-bubble.cj-bubble-pop { animation: cjBubblePop .38s cubic-bezier(0.34, 1.56, 0.64, 1); }
    @keyframes cjBubblePop {
        0%   { transform: scale(1); }
        40%  { transform: scale(0.9); }
        70%  { transform: scale(1.08); }
        100% { transform: scale(1); }
    }

    #chat-bubble.cj-bubble-idle { animation: cjBubblePulse 2.8s ease-in-out infinite; }
    @keyframes cjBubblePulse {
        0%, 100% { transform: scale(1); box-shadow: 0 4px 20px rgba(0,0,0,0.25); }
        50%      { transform: scale(1.04); box-shadow: 0 4px 20px rgba(0,0,0,0.25), 0 0 0 8px rgba(193,68,30,.18); }
    }
}
</style>

<script>
const CUSTOMER_ID = {{ auth()->id() }};
let adminId = null;
let chatOpen = false;
let subscribed = false;
let pulseAfterPop = false;

// The pop animation and the idle pulse both animate #chat-bubble's own
// transform, and CSS can't run two animations on the same element via
// separate classes without one fully overriding the other — so the pulse
// only ever resumes once the pop animation reports it has finished, never
// by re-adding the class immediately.
document.getElementById('chat-bubble').addEventListener('animationend', function (e) {
    if (e.animationName === 'cjBubblePop') {
        this.classList.remove('cj-bubble-pop');
        if (pulseAfterPop) {
            this.classList.add('cj-bubble-idle');
            pulseAfterPop = false;
        }
    }
});

function toggleChat() {
    chatOpen = !chatOpen;
    const win = document.getElementById('chat-window');
    const bubble = document.getElementById('chat-bubble');

    bubble.classList.remove('cj-bubble-idle');
    bubble.classList.remove('cj-bubble-pop');
    void bubble.offsetWidth; // force reflow so the pop animation restarts on rapid clicks
    bubble.classList.add('cj-bubble-pop');
    bubble.classList.toggle('cj-chat-open', chatOpen);
    bubble.setAttribute('aria-expanded', String(chatOpen));

    if (chatOpen) {
        bubble.setAttribute('aria-label', 'Close chat');
        pulseAfterPop = false;
        win.style.display = 'flex';
        win.style.flexDirection = 'column';
        // Double rAF: guarantees the browser has painted the closed state
        // before the "open" class is added, so the expand transition runs.
        requestAnimationFrame(() => requestAnimationFrame(() => {
            win.classList.add('cj-panel-open');
        }));
        loadCustomerMessages();
        // Focus moves into the panel so keyboard users land somewhere useful
        // rather than staying on a now-relabeled button.
        setTimeout(() => document.getElementById('chat-input').focus(), 50);
    } else {
        bubble.setAttribute('aria-label', 'Open chat');
        win.classList.remove('cj-panel-open');
        setTimeout(() => {
            if (!chatOpen) {
                win.style.display = 'none';
                win.style.flexDirection = '';
            }
        }, 300);

        const badge = document.getElementById('chat-badge');
        pulseAfterPop = (badge.style.display !== 'flex');
        bubble.focus();
    }
}

function chatSkeleton() {
    const bubble = (side, w, h) => `<div style="display:flex;justify-content:${side};">
        <div class="cj-skeleton" style="width:${w};height:${h}px;border-radius:${side === 'flex-start' ? '16px 16px 16px 4px' : '16px 16px 4px 16px'};"></div>
    </div>`;
    return bubble('flex-start', '65%', 38) + bubble('flex-end', '45%', 32) + bubble('flex-start', '75%', 44);
}

function loadCustomerMessages() {
    document.getElementById('chat-messages').innerHTML = chatSkeleton();
    fetch('/customer/chat/messages')
        .then(r => r.json())
        .then(data => {
            adminId = data.admin_id;
            renderMessages(data.messages);
            subscribeToChannel(CUSTOMER_ID, adminId);
        });
}

let lastMessageSenderId = null;

function renderMessages(messages) {
    const box = document.getElementById('chat-messages');
    if (messages.length === 0) {
        box.innerHTML = '<div style="text-align:center;color:#aaa;font-size:.82rem;margin-top:30px;">No messages yet.<br>Say hi! 👋</div>';
        lastMessageSenderId = null;
        return;
    }
    box.innerHTML = messages.map((m, i) =>
        messageBubble(m, i > 0 && messages[i - 1].sender_id === m.sender_id)
    ).join('');
    box.scrollTop = box.scrollHeight;
    lastMessageSenderId = messages[messages.length - 1].sender_id;
}

// `grouped` tightens the row's spacing when this message immediately follows
// another one from the same sender, instead of spacing every message the
// same regardless of who sent it.
function messageBubble(m, grouped) {
    const mine = m.sender_id === CUSTOMER_ID;
    const rowClass = grouped ? 'cj-chat-row-grouped' : '';
    const bubbleClass = mine ? 'cj-chat-bubble-mine' : 'cj-chat-bubble-theirs';
    return `<div class="${rowClass}" style="display:flex;justify-content:${mine ? 'flex-end' : 'flex-start'};">
        <div class="${bubbleClass}">
            ${m.body}
            <div class="cj-chat-timestamp">${m.created_at}</div>
        </div>
    </div>`;
}

function sendCustomerMessage() {
    const input = document.getElementById('chat-input');
    const body = input.value.trim();
    if (!body || !adminId) return;
    input.value = '';
    fetch('/customer/chat/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ body, receiver_id: adminId })
    }).then(r => r.json()).then(data => { appendMessage(data.message); });
}

function subscribeToChannel(uid1, uid2) {
    if (subscribed) return;
    subscribed = true;
    const ids = [uid1, uid2].sort((a, b) => a - b);
    window.Echo.private(`chat.${ids[0]}.${ids[1]}`).listen('MessageSent', (e) => {
        appendMessage(e);
    });
}

function appendMessage(m) {
    const box = document.getElementById('chat-messages');
    const grouped = lastMessageSenderId === m.sender_id;
    const div = document.createElement('div');
    div.innerHTML = messageBubble(m, grouped);
    box.appendChild(div.firstElementChild);
    box.scrollTop = box.scrollHeight;
    lastMessageSenderId = m.sender_id;
}

function checkUnread() {
    fetch('/customer/chat/unread')
        .then(r => r.json())
        .then(d => {
            const badge = document.getElementById('chat-badge');
            badge.style.display = d.count > 0 ? 'flex' : 'none';
            badge.textContent = d.count;

            // Idle pulse only while closed and nothing unread — an unread
            // badge is already its own, stronger signal.
            const bubble = document.getElementById('chat-bubble');
            bubble.classList.toggle('cj-bubble-idle', d.count === 0 && !chatOpen);
        });
}
setInterval(checkUnread, 30000);
checkUnread();
</script>

@yield('scripts')
</body>
</html>