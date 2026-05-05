<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cojan Catering')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                background: var(--green-dark, #1a5c38);
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
    <button class="hamburger" onclick="toggleNav()" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="cj-nav-links" id="mobileNav">
        <button class="nav-close" onclick="toggleNav()">&times;</button>
        <a href="{{ route('customer.menu') }}" class="btn-cj-outline btn-cj btn-cj-sm" onclick="toggleNav()">Menu</a>
        <a href="{{ route('customer.cart') }}" class="btn-cj-outline btn-cj btn-cj-sm" onclick="toggleNav()">🛒 Cart</a>
        <a href="{{ route('customer.orders') }}" class="btn-cj-outline btn-cj btn-cj-sm" onclick="toggleNav()">My Orders</a>
        <form method="POST" action="{{ route('logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn-cj-amber btn-cj btn-cj-sm">Logout</button>
        </form>
    </div>
</nav>

<div class="cj-page">
    @if(session('success'))
        <div class="alert-cj alert-success">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-cj alert-danger">❌ {{ session('error') }}</div>
    @endif

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])

<script>
function toggleNav() {
    document.getElementById('mobileNav').classList.toggle('open');
}
// Close nav when clicking outside
document.addEventListener('click', function(e) {
    const nav = document.getElementById('mobileNav');
    const hamburger = document.querySelector('.hamburger');
    if (nav.classList.contains('open') && !nav.contains(e.target) && !hamburger.contains(e.target)) {
        nav.classList.remove('open');
    }
});
</script>

<!-- ========== CUSTOMER CHAT BUBBLE ========== -->
<div id="chat-bubble" onclick="toggleChat()"
    style="position:fixed;bottom:28px;right:28px;z-index:9999;
           width:58px;height:58px;border-radius:50%;
           background:linear-gradient(135deg,#1a5c38,#2d8653);
           box-shadow:0 4px 20px rgba(0,0,0,0.25);
           display:flex;align-items:center;justify-content:center;
           cursor:pointer;">
    <span style="color:#fff;font-size:1.5rem;">💬</span>
    <span id="chat-badge"
          style="display:none;position:absolute;top:2px;right:2px;
                 background:#e74c3c;color:#fff;border-radius:50%;
                 width:18px;height:18px;font-size:11px;font-weight:700;
                 align-items:center;justify-content:center;">0</span>
</div>

<div id="chat-window"
    style="display:none;position:fixed;z-index:9998;
           background:#fff;font-family:'DM Sans',sans-serif;
           flex-direction:column;
           /* Desktop */
           bottom:100px;right:28px;width:340px;height:460px;
           border-radius:16px;overflow:hidden;
           box-shadow:0 8px 32px rgba(0,0,0,0.22);">
    <div style="background:linear-gradient(135deg,#1a5c38,#2d8653);padding:14px 18px;
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
        <span onclick="toggleChat()" style="color:#fff;cursor:pointer;font-size:1.4rem;">&times;</span>
    </div>
    <div id="chat-messages"
        style="flex:1;overflow-y:auto;padding:14px;display:flex;
               flex-direction:column;gap:8px;background:#f4f9f6;"></div>
    <div style="padding:10px 12px;border-top:1px solid #eee;display:flex;gap:8px;background:#fff;">
        <input id="chat-input" type="text" placeholder="Type a message..."
            style="flex:1;border:1.5px solid #2d8653;border-radius:20px;
                   padding:8px 14px;font-size:.88rem;outline:none;"
            onkeydown="if(event.key==='Enter') sendCustomerMessage()">
        <button onclick="sendCustomerMessage()"
            style="background:linear-gradient(135deg,#1a5c38,#2d8653);border:none;
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
</style>

<script>
const CUSTOMER_ID = {{ auth()->id() }};
let adminId = null;
let chatOpen = false;
let subscribed = false;

function toggleChat() {
    chatOpen = !chatOpen;
    const win = document.getElementById('chat-window');
    win.style.display = chatOpen ? 'flex' : 'none';
    win.style.flexDirection = chatOpen ? 'column' : '';
    if (chatOpen) loadCustomerMessages();
}

function loadCustomerMessages() {
    fetch('/customer/chat/messages')
        .then(r => r.json())
        .then(data => {
            adminId = data.admin_id;
            renderMessages(data.messages);
            subscribeToChannel(CUSTOMER_ID, adminId);
        });
}

function renderMessages(messages) {
    const box = document.getElementById('chat-messages');
    if (messages.length === 0) {
        box.innerHTML = '<div style="text-align:center;color:#aaa;font-size:.82rem;margin-top:30px;">No messages yet.<br>Say hi! 👋</div>';
        return;
    }
    box.innerHTML = messages.map(m => messageBubble(m)).join('');
    box.scrollTop = box.scrollHeight;
}

function messageBubble(m) {
    const mine = m.sender_id === CUSTOMER_ID;
    return `<div style="display:flex;justify-content:${mine ? 'flex-end' : 'flex-start'};">
        <div style="max-width:75%;padding:9px 13px;
                    border-radius:${mine ? '16px 16px 4px 16px' : '16px 16px 16px 4px'};
                    background:${mine ? 'linear-gradient(135deg,#1a5c38,#2d8653)' : '#fff'};
                    color:${mine ? '#fff' : '#333'};font-size:.87rem;
                    box-shadow:0 2px 6px rgba(0,0,0,0.08);">
            ${m.body}
            <div style="font-size:.7rem;opacity:.7;margin-top:3px;text-align:right;">${m.created_at}</div>
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
    const div = document.createElement('div');
    div.innerHTML = messageBubble(m);
    box.appendChild(div.firstElementChild);
    box.scrollTop = box.scrollHeight;
}

function checkUnread() {
    fetch('/customer/chat/unread')
        .then(r => r.json())
        .then(d => {
            const badge = document.getElementById('chat-badge');
            badge.style.display = d.count > 0 ? 'flex' : 'none';
            badge.textContent = d.count;
        });
}
setInterval(checkUnread, 30000);
checkUnread();
</script>

@yield('scripts')
</body>
</html>