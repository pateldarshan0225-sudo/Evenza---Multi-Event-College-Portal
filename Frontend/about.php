<?php
/**
 * Frontend/about.php
 * About Us Page for Evenza Public Portal
 */
$page_title = "About Us";
include 'connection.php';
include 'Header.php';
?>

<div class="container-xl py-5">
    
    <div class="text-center mb-5">
        <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-2">Our Mission</span>
        <h1 class="fw-black text-dark display-5 mb-2">About Evenza Ecosystem</h1>
        <p class="text-muted text-xs mx-auto" style="max-width: 580px;">
            Evenza is an all-in-one inter-college multi-event management platform bridging colleges, event organizers, and student competitors nationwide.
        </p>
    </div>

    <div class="row g-4 align-items-center mb-5">
        <div class="col-12 col-md-6">
            <div class="bg-white rounded-5 p-4 p-md-5 border border-dark border-2 shadow-sm" style="box-shadow: 6px 6px 0px #14171a !important;">
                <h3 class="fw-black text-dark mb-3 fs-4">Empowering Campus Competitions</h3>
                <p class="text-secondary text-xs leading-relaxed mb-3">
                    Before Evenza, students struggled to discover upcoming events across neighboring colleges, while organizers faced hurdles managing team registrations and fee collections manually.
                </p>
                <p class="text-secondary text-xs leading-relaxed mb-0">
                    Our platform streamlines event discovery, solo and team sign-ups, automated receipt generation, and real-time organizer dashboards.
                </p>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="row g-3">
                <div class="col-6">
                    <div class="bg-dark text-white rounded-5 p-4 border border-dark text-center">
                        <div class="fs-2 font-black text-warning">160+</div>
                        <small class="text-secondary text-xs text-uppercase font-semibold">Partner Institutions</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-warning text-dark rounded-5 p-4 border border-dark text-center">
                        <div class="fs-2 font-black text-dark">3,200+</div>
                        <small class="text-dark text-xs text-uppercase font-semibold">Registered Students</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-mint text-dark rounded-5 p-4 border border-dark text-center" style="background:#e6f4f1;">
                        <div class="fs-2 font-black text-dark">100%</div>
                        <small class="text-secondary text-xs text-uppercase font-semibold">Verified Events</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-white text-dark rounded-5 p-4 border border-dark text-center">
                        <div class="fs-2 font-black text-dark">Instant</div>
                        <small class="text-secondary text-xs text-uppercase font-semibold">Registration Passes</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include 'Footer.php'; ?>
