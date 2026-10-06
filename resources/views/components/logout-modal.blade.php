{{-- Shared across customer/admin/delivery/auth — deliberately not re-themed per
     role (stays on cojan.css's global green modal styling) since it's a brief
     interstitial, not part of a role's main UI chrome. --}}
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Out</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to log out?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cj"
                        style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);"
                        data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn-cj btn-cj">Log Out</button>
                </form>
            </div>
        </div>
    </div>
</div>
