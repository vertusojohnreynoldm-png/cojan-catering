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
<body class="cj-has-chat">

<nav class="cj-nav">
    <a href="{{ route('customer.dashboard') }}" class="cj-nav-brand">🍽 Cojan <span>Catering</span></a>
    {{-- Phones only: chat is always one tap away up here, even while the
         floating bubble has stepped aside so it doesn't cover the page. --}}
    <div class="cj-nav-actions">
        <button type="button" class="cj-nav-chat" onclick="toggleChat()" aria-label="Open chat with Cojan Support">
            <span aria-hidden="true">💬</span>
            <span id="chat-badge-nav" class="cj-nav-chat-badge" aria-hidden="true" style="display:none;">0</span>
        </button>
        <button class="hamburger" onclick="toggleNav()" aria-label="Open menu" aria-expanded="false" aria-controls="mobileNav">
            <span></span><span></span><span></span>
        </button>
    </div>
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

@auth
    @if(!auth()->user()->hasVerifiedEmail())
        <div id="verify-email-banner" class="alert-cj alert-warning"
             style="border-radius:0;margin:0;justify-content:center;position:relative;padding-right:40px;">
            <i class="bi bi-envelope-exclamation"></i>
            <span>Please verify your email to place orders or book catering packages.</span>
            <form method="POST" action="{{ route('verification.send') }}" style="display:inline;margin:0;">
                @csrf
                <button type="submit"
                        style="background:none;border:none;padding:0;margin-left:.4rem;color:#92400e;
                               font-weight:700;text-decoration:underline;cursor:pointer;font-size:inherit;">
                    Resend email
                </button>
            </form>
            <button type="button" onclick="document.getElementById('verify-email-banner').style.display='none'"
                    aria-label="Dismiss"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                           background:none;border:none;font-size:1.1rem;color:#92400e;cursor:pointer;line-height:1;">
                &times;
            </button>
        </div>
    @endif
@endauth

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
// Shared by any page with a PH-mobile-number field (checkout, the catering
// Avail form) — mirrors App\Rules\PhilippineMobileNumber exactly, including
// the repeated-digit junk check, so the live hint never says "looks good"
// for a number the server will actually reject.
function updatePhoneLiveHint(inputEl, hintEl) {
    if (!inputEl || !hintEl) return;
    const cleaned = inputEl.value.replace(/[\s\-()]/g, '');
    const shapeOk = /^(?:\+?63|0)9\d{9}$/.test(cleaned);

    if (cleaned === '') {
        hintEl.textContent = 'Format: 09XXXXXXXXX';
        hintEl.style.color = 'var(--text-light)';
        return;
    }

    if (!shapeOk) {
        hintEl.textContent = 'Format: 09XXXXXXXXX (e.g. 09171234567)';
        hintEl.style.color = 'var(--required)';
        return;
    }

    let digits = cleaned.replace(/\D/g, '');
    if (digits.startsWith('63') && digits.length === 12) digits = '0' + digits.slice(2);
    const subscriber = digits.slice(2);

    if (/^(\d)\1{8}$/.test(subscriber)) {
        hintEl.textContent = "That doesn't look like a real mobile number.";
        hintEl.style.color = 'var(--required)';
        return;
    }

    hintEl.textContent = '✓ Looks good';
    hintEl.style.color = 'var(--status-green-text)';
}

// Shared by any page with cascading province → municipality → barangay
// dropdowns (checkout for a package-only cart, the catering Avail form).
// `areas` is the pre-filtered { province: { municipality: [barangay,...] } }
// tree already embedded by the server (no network call) — this only
// filters/repopulates the <select> options, it never invents data the
// server didn't already approve, so a tampered submission still can't name
// a combination that passes server-side re-validation.
function initAddressCascade({ provinceSelect, municipalitySelect, barangaySelect, areas, oldProvince, oldMunicipality, oldBarangay }) {
    function populateBarangays(province, municipality, selected) {
        barangaySelect.innerHTML = '<option value="">Select barangay...</option>';
        const barangays = (areas[province] && areas[province][municipality]) || [];
        barangays.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b;
            opt.textContent = b;
            if (b === selected) opt.selected = true;
            barangaySelect.appendChild(opt);
        });
    }

    function populateMunicipalities(province, selectedMunicipality, selectedBarangay) {
        municipalitySelect.innerHTML = '<option value="">Select municipality/city...</option>';
        const municipalities = areas[province] ? Object.keys(areas[province]) : [];
        municipalities.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m;
            opt.textContent = m;
            if (m === selectedMunicipality) opt.selected = true;
            municipalitySelect.appendChild(opt);
        });
        populateBarangays(province, municipalitySelect.value, municipalitySelect.value === selectedMunicipality ? selectedBarangay : null);
    }

    // A user-driven change always resets the dependent dropdown(s) below it
    // — picking a different province means the old municipality/barangay no
    // longer apply.
    provinceSelect.addEventListener('change', () => populateMunicipalities(provinceSelect.value, null, null));
    municipalitySelect.addEventListener('change', () => populateBarangays(provinceSelect.value, municipalitySelect.value, null));

    // Initial restore (old() on a validation failure, or the customer's
    // saved profile address) sets all three levels together in one pass —
    // setting provinceSelect.value directly here, not via a synthetic
    // 'change' event, which would otherwise immediately wipe the
    // municipality/barangay this same restore is trying to set.
    if (oldProvince && areas[oldProvince]) {
        provinceSelect.value = oldProvince;
        populateMunicipalities(oldProvince, oldMunicipality || null, oldBarangay || null);
    }
}

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
    <div id="chat-input-row" style="padding:10px 12px;border-top:1px solid #eee;display:flex;gap:8px;background:#fff;">
        <input id="chat-input" type="text" placeholder="Type a message..." aria-label="Type a message"
            style="flex:1;min-width:0;border:1.5px solid #C1441E;border-radius:20px;
                   padding:8px 14px;font-size:16px;outline:none;"
            onkeydown="if(event.key==='Enter') sendCustomerMessage()">
        <button onclick="sendCustomerMessage()" aria-label="Send message"
            style="background:linear-gradient(135deg,#7A2E1D,#C1441E);border:none;flex-shrink:0;
                   border-radius:50%;width:44px;height:44px;color:#fff;cursor:pointer;
                   display:flex;align-items:center;justify-content:center;font-size:1.1rem;">➤</button>
    </div>
</div>

<style>
/* Chat window full screen on mobile */
@media (max-width: 768px) {
    #chat-window {
        bottom: 0 !important;
        right: 0 !important;
        width: 100vw !important;
        height: 100dvh !important;
        border-radius: 0 !important;
    }
    #chat-bubble {
        bottom: 20px !important;
        right: 20px !important;
    }
    /* Bug fix: the bubble used to stay visible (and on top, z-index 9999 vs
       the panel's 9998) while the panel went full-screen, sitting directly
       over the input row's Send button in the same bottom-right corner. The
       panel already has its own header close button, so the bubble is
       simply hidden while open — no need for it on mobile at all.

       !important is required here, not decorative: #chat-bubble carries an
       inline style="...display:flex;..." attribute, and an inline style
       always wins over a stylesheet rule — ANY stylesheet rule, regardless
       of selector specificity — unless that rule is !important. The first
       attempt at this fix omitted it and silently never applied. */
    #chat-bubble.cj-chat-open {
        display: none !important;
    }
    /* Second, independent mechanism, deliberately redundant with the rule
       above: toggled on <body> (not the bubble itself), so a future markup
       change to #chat-bubble can't silently resurrect this bug the same way
       the missing !important just did. */
    body.chat-open #chat-bubble {
        display: none !important;
    }
    /* Keeps the input row clear of the home bar / gesture area on iOS —
       the input itself stays above the safe area rather than under it. */
    #chat-input-row {
        padding-bottom: max(10px, env(safe-area-inset-bottom));
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

// 100dvh alone doesn't react to the iOS on-screen keyboard — Safari doesn't
// shrink the layout viewport when the keyboard opens over a fixed-position
// full-screen panel, which is exactly what leaves the input/Send button
// hidden behind it. visualViewport.height DOES reflect the keyboard, so the
// panel's actual pixel height is kept in sync with it while open on mobile.
if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', function () {
        const win = document.getElementById('chat-window');
        if (chatOpen && window.matchMedia('(max-width: 768px)').matches) {
            // setProperty(..., 'important'): the CSS rule sets height with
            // !important for the full-screen-on-mobile case, which would
            // otherwise silently beat a plain JS-assigned inline style.
            win.style.setProperty('height', window.visualViewport.height + 'px', 'important');
        }
    });
}

function toggleChat() {
    chatOpen = !chatOpen;
    const win = document.getElementById('chat-window');
    const bubble = document.getElementById('chat-bubble');

    bubble.classList.remove('cj-bubble-idle');
    bubble.classList.remove('cj-bubble-pop');
    void bubble.offsetWidth; // force reflow so the pop animation restarts on rapid clicks
    bubble.classList.add('cj-bubble-pop');
    bubble.classList.toggle('cj-chat-open', chatOpen);
    document.body.classList.toggle('chat-open', chatOpen);
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
                win.style.removeProperty('height'); // clear any keyboard-driven override from this session
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
            if (!adminId) {
                // No admin account exists yet — nothing to subscribe to.
                document.getElementById('chat-messages').innerHTML =
                    '<div style="text-align:center;color:var(--text-muted);font-size:.82rem;margin-top:30px;">Chat is unavailable right now.<br>Please try again later.</div>';
                document.getElementById('chat-input').disabled = true;
                return;
            }
            renderMessages(data.messages);
            subscribeToChannel(CUSTOMER_ID, adminId);
        })
        .catch(() => {
            document.getElementById('chat-messages').innerHTML =
                '<div style="text-align:center;color:var(--text-muted);font-size:.82rem;margin-top:30px;">Couldn\'t load messages — check your connection.</div>';
        });
}

let lastMessageSenderId = null;
let lastReadUpto = null; // ISO timestamp from the most recent MessagesRead event this session

function renderMessages(messages) {
    const box = document.getElementById('chat-messages');
    if (messages.length === 0) {
        box.innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:.82rem;margin-top:30px;">No messages yet.<br>Say hi! 👋</div>';
        lastMessageSenderId = null;
        return;
    }
    box.innerHTML = messages.map((m, i) =>
        messageBubble(m, i > 0 && messages[i - 1].sender_id === m.sender_id)
    ).join('');
    box.scrollTop = box.scrollHeight;
    lastMessageSenderId = messages[messages.length - 1].sender_id;
    updateOwnMessageStatus();
}

// `grouped` tightens the row's spacing when this message immediately follows
// another one from the same sender, instead of spacing every message the
// same regardless of who sent it.
function messageBubble(m, grouped) {
    const mine = m.sender_id === CUSTOMER_ID;
    const rowClass = grouped ? 'cj-chat-row-grouped' : '';
    const bubbleClass = mine ? 'cj-chat-bubble-mine' : 'cj-chat-bubble-theirs';
    return `<div class="${rowClass}" data-sender="${m.sender_id}" data-ts="${m.timestamp || ''}" data-read-at="${m.read_at || ''}"
                 style="display:flex;flex-direction:column;align-items:${mine ? 'flex-end' : 'flex-start'};">
        <div class="${bubbleClass}">
            ${m.body}
            <div class="cj-chat-timestamp">${m.created_at}</div>
        </div>
        ${mine ? '<div class="cj-chat-status"></div>' : ''}
    </div>`;
}

// Only the customer's single most recent own message ever shows a status
// line (standard messenger convention) — every older one is cleared first.
function updateOwnMessageStatus() {
    const ownRows = Array.from(document.querySelectorAll('#chat-messages [data-sender]'))
        .filter(r => Number(r.dataset.sender) === CUSTOMER_ID);
    ownRows.forEach(r => {
        const statusEl = r.querySelector('.cj-chat-status');
        if (statusEl) statusEl.innerHTML = '';
    });
    if (ownRows.length === 0) return;

    const last = ownRows[ownRows.length - 1];
    const statusEl = last.querySelector('.cj-chat-status');
    if (!statusEl) return;

    const seen = !!last.dataset.readAt
        || (lastReadUpto && last.dataset.ts && new Date(last.dataset.ts) <= new Date(lastReadUpto));
    statusEl.innerHTML = seen
        ? '<i class="bi bi-check2-all" aria-hidden="true"></i> Seen'
        : 'Sent';
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
    window.Echo.private(`chat.${ids[0]}.${ids[1]}`)
        .listen('MessageSent', (e) => {
            appendMessage(e);
        })
        .listen('MessagesRead', (e) => {
            lastReadUpto = e.upto;
            updateOwnMessageStatus();
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
    updateOwnMessageStatus();
}

function checkUnread() {
    fetch('/customer/chat/unread')
        .then(r => r.json())
        .then(d => {
            const badge = document.getElementById('chat-badge');
            badge.style.display = d.count > 0 ? 'flex' : 'none';
            badge.textContent = d.count;
            const navBadge = document.getElementById('chat-badge-nav');
            if (navBadge) {
                navBadge.style.display = d.count > 0 ? 'flex' : 'none';
                navBadge.textContent = d.count;
            }

            // Idle pulse only while closed and nothing unread — an unread
            // badge is already its own, stronger signal.
            const bubble = document.getElementById('chat-bubble');
            bubble.classList.toggle('cj-bubble-idle', d.count === 0 && !chatOpen);
        });
}
setInterval(checkUnread, 30000);
checkUnread();
</script>

<style>
/* ── CHAT BUBBLE: NEVER COVER THE PAGE ──
   The bubble is position:fixed with z-index 9999, so on a phone it used to
   sit over whatever scrolled under it — Place Order, the downpayment
   amount, alert text, even open modals. Three layers fix that:
   1. .cj-bubble-away (toggled by the script below) fades it out of the way
      while it's over anything that matters, and back when it isn't.
   2. A 💬 button in the phone header keeps chat one tap away regardless.
   3. Extra footer padding leaves empty space at the very end of every page.
   !important wherever the bubble's inline style sets the same property —
   inline styles beat any non-!important stylesheet rule. */
.cj-nav-actions { display: flex; align-items: center; gap: .35rem; }
.cj-nav-chat { display: none; }
#chat-bubble { transition: opacity .2s ease, transform .2s ease; }
#chat-bubble.cj-bubble-away {
    opacity: 0 !important;
    transform: translateY(14px) scale(.6) !important;
    animation: none !important;   /* the idle pulse animates transform too */
    pointer-events: none !important;
}
#chat-bubble-zone { position: fixed; right: 28px; bottom: 28px; width: 58px; height: 58px; visibility: hidden; pointer-events: none; }
.cj-has-chat .cj-footer { padding-bottom: calc(1.5rem + 96px + env(safe-area-inset-bottom)); }

@media (max-width: 768px) {
    #chat-bubble {
        width: 52px !important;
        height: 52px !important;
        right: 14px !important;
        bottom: calc(14px + env(safe-area-inset-bottom)) !important;
    }
    #chat-bubble-zone { right: 14px; bottom: calc(14px + env(safe-area-inset-bottom)); width: 52px; height: 52px; }
    .cj-nav-chat {
        display: flex;
        position: relative;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border: none;
        border-radius: var(--radius-sm);
        background: rgba(255,255,255,.12);
        font-size: 1.25rem;
        line-height: 1;
        cursor: pointer;
    }
    .cj-nav-chat:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    .cj-nav-chat-badge {
        position: absolute;
        top: 3px;
        right: 3px;
        min-width: 17px;
        height: 17px;
        padding: 0 4px;
        border-radius: 9px;
        background: #e74c3c;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        align-items: center;
        justify-content: center;
    }
    .cj-has-chat .cj-footer { padding-bottom: calc(1.5rem + 80px + env(safe-area-inset-bottom)); }
}
</style>

<script>
/* Keeps the floating chat bubble from covering anything that matters. A
   hidden twin (#chat-bubble-zone) marks where the bubble sits; on scroll,
   resize, focus or DOM changes we hit-test a few points inside that zone
   and look at the topmost thing under the bubble. If it's interactive
   (button, link, field), an alert, a money amount, an image, a modal or
   any text, the bubble steps aside (.cj-bubble-away); over empty space it
   comes back. On phones it also steps aside while the customer is typing
   in a page form (the keyboard covers that corner anyway). The 💬 header
   button keeps chat reachable while it's away. */
(function () {
    const bubble = document.getElementById('chat-bubble');
    if (!bubble) return;
    const zone = document.createElement('div');
    zone.id = 'chat-bubble-zone';
    zone.setAttribute('aria-hidden', 'true');
    document.body.appendChild(zone);

    const AVOID = 'a, button, input, select, textarea, label, img, svg, canvas, video, iframe, '
        + '.alert-cj, .cj-dp, .badge-cj, .slot-chip, .flatpickr-calendar, .modal, .cj-toast, [data-chat-avoid]';
    const isPhone = () => window.matchMedia('(max-width: 768px)').matches;
    const isPageField = (el) => el && el.matches && el.matches('input, textarea, select') && !el.closest('#chat-window');
    let typing = false;
    let frame = 0;

    function hasOwnText(el) {
        for (const node of el.childNodes) {
            if (node.nodeType === 3 && node.textContent.trim() !== '') return true;
        }
        return false;
    }

    function topmostUnder(x, y) {
        for (const el of document.elementsFromPoint(x, y)) {
            if (el === bubble || bubble.contains(el) || el === zone) continue;
            return el;
        }
        return null;
    }

    function blocked() {
        // A 5×5 grid over the bubble's footprint plus a 6px safety margin —
        // dense enough that a single line of text can't slip between sample
        // points, and wide enough that a button merely grazing the bubble's
        // edge still counts.
        const m = 6;
        const r = zone.getBoundingClientRect();
        const left = r.left - m, top = r.top - m, w = r.width + 2 * m, h = r.height + 2 * m;
        const points = [];
        for (let col = 0; col < 5; col++) {
            for (let row = 0; row < 5; row++) {
                points.push([left + col * (w - 1) / 4, top + row * (h - 1) / 4]);
            }
        }
        return points.some(([x, y]) => {
            const el = topmostUnder(x, y);
            if (!el || el === document.body || el === document.documentElement) return false;
            return !!el.closest(AVOID) || hasOwnText(el);
        });
    }

    function update() {
        frame = 0;
        if (document.body.classList.contains('chat-open')) {
            bubble.classList.remove('cj-bubble-away');
            return;
        }
        bubble.classList.toggle('cj-bubble-away', (typing && isPhone()) || blocked());
    }
    const schedule = () => { if (!frame) frame = requestAnimationFrame(update); };

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    window.addEventListener('load', schedule);
    document.addEventListener('focusin', (e) => { if (isPageField(e.target)) { typing = true; schedule(); } });
    document.addEventListener('focusout', () => setTimeout(() => { typing = isPageField(document.activeElement); schedule(); }, 0));
    // Content that appears later (slot chips, toasts, opened modals, alerts).
    new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'] });
    schedule();
})();
</script>

<script>
/* Submit-once forms (checkout, package checkout, Avail): after the FIRST
   real submit, every submit button in the form is disabled and shows a
   spinner + its data-loading-text, and any further submit is swallowed.
   Listens on document (bubble phase) so it only reacts once a page's own
   submit handlers have had their say — if one of them called
   preventDefault() (e.g. "pick a delivery window first"), the form stays
   usable. The server-side one-time token is the real guarantee; this is
   the visible half. */
(function () {
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form.matches('form[data-submit-once]') || e.defaultPrevented) return;

        if (form.dataset.submitting === '1') {
            e.preventDefault();
            return;
        }
        form.dataset.submitting = '1';

        form.querySelectorAll('button[type=submit]').forEach(function (btn) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> '
                + (btn.dataset.loadingText || 'Please wait…');
        });
    });

    // Coming Back to the page from the browser's cache (bfcache) would
    // otherwise leave the buttons stuck disabled.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        document.querySelectorAll('form[data-submit-once]').forEach(function (form) {
            delete form.dataset.submitting;
            form.querySelectorAll('button[type=submit]').forEach(function (btn) {
                if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
                btn.disabled = btn.hasAttribute('data-keep-disabled');
                btn.removeAttribute('aria-busy');
            });
        });
    });
})();
</script>

@yield('scripts')
</body>
</html>