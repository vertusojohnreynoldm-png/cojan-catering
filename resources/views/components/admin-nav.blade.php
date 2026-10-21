{{-- Admin top nav — 9 links + Logout don't fit a single row below 992px
     (wider cutoff than the customer/delivery 768px since there's more than
     twice as many links here), so under that width this collapses into a
     hamburger + slide-down panel instead of customer's full-screen overlay
     pattern: a short list of 9 rows reads better as a dropdown than centered
     full-screen pills. Desktop (≥992px) is untouched — same flex row as
     before, styled entirely by cojan.css's existing .cj-nav/.cj-nav-links. --}}
<style>
    .cj-admin-hamburger {
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
        cursor: pointer;
        width: 44px;
        height: 44px;
        flex-shrink: 0;
        background: none;
        border: none;
        border-radius: var(--radius-sm);
    }
    .cj-admin-hamburger span {
        display: block;
        width: 22px;
        height: 2px;
        background: #fff;
        border-radius: 2px;
        transition: transform .25s ease, opacity .2s ease;
    }
    .cj-admin-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .cj-admin-hamburger.open span:nth-child(2) { opacity: 0; }
    .cj-admin-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    @media (max-width: 992px) {
        .cj-nav { flex-wrap: nowrap; }
        .cj-nav-brand { font-size: clamp(.95rem, 4vw, 1.2rem); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cj-admin-hamburger { display: flex; }

        .cj-admin-nav-links {
            display: none;
            position: fixed;
            top: 64px;
            left: 0;
            right: 0;
            /* height (not max-height): the panel must fully cover whatever page
               content sits behind it — otherwise content (and the dashboard's
               floating chat bubble, which sits above this panel's z-index)
               peeks through below a short link list. */
            height: calc(100dvh - 64px);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            background: var(--green-dark);
            flex-direction: column;
            align-items: stretch;
            gap: 0;
            padding: .4rem 0 calc(.6rem + env(safe-area-inset-bottom));
            box-shadow: 0 12px 28px rgba(0,0,0,.28);
            z-index: 1050;
        }
        .cj-admin-nav-links.open { display: flex; }
        /* The admin dashboard's own floating chat button (z-index 9999) would
           otherwise float on top of this panel (z-index 1050) while it's
           open — same class of bug as the customer chat-bubble/panel overlap,
           same fix: an !important body-class rule so it can't be silently
           reintroduced by a later change to either element. */
        body.admin-nav-open #admin-chat-btn { display: none !important; }
        .cj-admin-nav-links a,
        .cj-admin-nav-links button {
            width: 100% !important;
            min-height: 48px;
            border-radius: 0 !important;
            justify-content: flex-start !important;
            padding: .7rem 1.5rem !important;
            font-size: .95rem !important;
            background: transparent !important;
            border: none !important;
            border-bottom: 1px solid rgba(255,255,255,.1) !important;
            color: #fff !important;
        }
        .cj-admin-nav-links a.cj-admin-nav-active {
            background: rgba(255,255,255,.14) !important;
            font-weight: 700;
            border-left: 4px solid var(--amber) !important;
            padding-left: calc(1.5rem - 4px) !important;
        }
        .cj-admin-nav-links button[data-bs-target="#logoutModal"] {
            color: var(--amber) !important;
            font-weight: 700;
            border-bottom: none !important;
            margin-top: .3rem;
        }
    }
</style>

<nav class="cj-nav">
    <a href="{{ route('admin.dashboard') }}" class="cj-nav-brand">🍽 Cojan <span>Admin</span></a>
    <button type="button" class="cj-admin-hamburger" onclick="toggleAdminNav()"
            aria-label="Open menu" aria-expanded="false" aria-controls="adminMobileNav">
        <span></span><span></span><span></span>
    </button>
    <div class="cj-nav-links cj-admin-nav-links" id="adminMobileNav">
        <a href="{{ route('admin.orders') }}" onclick="toggleAdminNav()"
           class="btn-cj-outline btn-cj btn-cj-sm {{ request()->routeIs('admin.orders*') ? 'cj-admin-nav-active' : '' }}">
            <i class="bi bi-receipt"></i> Orders
        </a>
        <a href="{{ route('admin.bookings') }}" onclick="toggleAdminNav()"
           class="btn-cj-outline btn-cj btn-cj-sm {{ request()->routeIs('admin.bookings*') ? 'cj-admin-nav-active' : '' }}">
            📦 Bookings
        </a>
        <a href="{{ route('admin.menu') }}" onclick="toggleAdminNav()"
           class="btn-cj-outline btn-cj btn-cj-sm {{ request()->routeIs('admin.menu*') ? 'cj-admin-nav-active' : '' }}">
            <i class="bi bi-egg-fried"></i> Menu
        </a>
        <a href="{{ route('admin.packages') }}" onclick="toggleAdminNav()"
           class="btn-cj-outline btn-cj btn-cj-sm {{ request()->routeIs('admin.packages*') ? 'cj-admin-nav-active' : '' }}">
            <i class="bi bi-box-seam"></i> Packages
        </a>
        <a href="{{ route('admin.inventory') }}" onclick="toggleAdminNav()"
           class="btn-cj-outline btn-cj btn-cj-sm {{ request()->routeIs('admin.inventory*') ? 'cj-admin-nav-active' : '' }}">
            <i class="bi bi-boxes"></i> Inventory
        </a>
        <a href="{{ route('admin.utensils') }}" onclick="toggleAdminNav()"
           class="btn-cj-outline btn-cj btn-cj-sm {{ request()->routeIs('admin.utensils*') ? 'cj-admin-nav-active' : '' }}">
            <i class="bi bi-cup-straw"></i> Utensils
        </a>
        <a href="{{ route('admin.analytics') }}" onclick="toggleAdminNav()"
           class="btn-cj-outline btn-cj btn-cj-sm {{ request()->routeIs('admin.analytics*') ? 'cj-admin-nav-active' : '' }}">
            <i class="bi bi-graph-up"></i> Analytics
        </a>
        <a href="{{ route('admin.feedback') }}" onclick="toggleAdminNav()"
           class="btn-cj-outline btn-cj btn-cj-sm {{ request()->routeIs('admin.feedback*') ? 'cj-admin-nav-active' : '' }}">
            <i class="bi bi-chat-square-text"></i> Feedback
        </a>
        <a href="{{ route('admin.users') }}" onclick="toggleAdminNav()"
           class="btn-cj-outline btn-cj btn-cj-sm {{ request()->routeIs('admin.users*') ? 'cj-admin-nav-active' : '' }}">
            <i class="bi bi-people"></i> Users
        </a>
        <a href="{{ route('admin.activity') }}" onclick="toggleAdminNav()"
           class="btn-cj-outline btn-cj btn-cj-sm {{ request()->routeIs('admin.activity*') ? 'cj-admin-nav-active' : '' }}">
            <i class="bi bi-clock-history"></i> Activity Log
        </a>
        <button type="button" class="btn-cj-amber btn-cj btn-cj-sm" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="bi bi-box-arrow-right"></i> Logout
        </button>
    </div>
</nav>
<x-logout-modal />

<script>
function toggleAdminNav() {
    const nav = document.getElementById('adminMobileNav');
    const btn = document.querySelector('.cj-admin-hamburger');
    const isOpen = nav.classList.toggle('open');
    btn.classList.toggle('open', isOpen);
    btn.setAttribute('aria-expanded', String(isOpen));
    btn.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
    document.body.classList.toggle('admin-nav-open', isOpen);
}
document.addEventListener('click', function (e) {
    const nav = document.getElementById('adminMobileNav');
    const btn = document.querySelector('.cj-admin-hamburger');
    if (nav.classList.contains('open') && !nav.contains(e.target) && !btn.contains(e.target)) {
        toggleAdminNav();
    }
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const nav = document.getElementById('adminMobileNav');
        if (nav.classList.contains('open')) {
            toggleAdminNav();
            document.querySelector('.cj-admin-hamburger').focus();
        }
    }
});
</script>
