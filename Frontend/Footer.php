<?php
/**
 * Frontend/Footer.php
 * Site Footer for Evenza Public Portal
 */
?>
    <!-- FOOTER SECTION -->
    <footer class="bg-dark text-white pt-5 pb-4 mt-5" style="background-color: #14171a !important; border-top-left-radius: 32px; border-top-right-radius: 32px;">
        <div class="container-xl">
            <div class="row g-4 mb-5">
                <div class="col-12 col-md-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="bg-warning text-dark font-black px-3 py-1 rounded-3 fs-5">
                            <i class="bi bi-intersect"></i> Evenza
                        </div>
                    </div>
                    <p class="text-secondary text-xs leading-relaxed pe-md-4">
                        The ultimate multi-event college portal connecting partner universities, colleges, organizers, and students across India. Discover technical hackathons, cultural fests, sports tournaments, and workshops in one unified ecosystem.
                    </p>
                </div>

                <div class="col-6 col-md-2">
                    <h6 class="text-uppercase text-warning font-bold text-xs tracking-wider mb-3">Explore</h6>
                    <ul class="list-unstyled text-xs d-flex flex-column gap-2 mb-0">
                        <li><a href="index.php" class="text-secondary text-decoration-none hover-white">Home</a></li>
                        <li><a href="colleges.php" class="text-secondary text-decoration-none hover-white">Partner Colleges</a></li>
                        <li><a href="events.php" class="text-secondary text-decoration-none hover-white">Browse Events</a></li>
                        <li><a href="categories.php" class="text-secondary text-decoration-none hover-white">Categories</a></li>
                    </ul>
                </div>

                <div class="col-6 col-md-2">
                    <h6 class="text-uppercase text-warning font-bold text-xs tracking-wider mb-3">Portals</h6>
                    <ul class="list-unstyled text-xs d-flex flex-column gap-2 mb-0">
                        <li><a href="login.php" class="text-secondary text-decoration-none hover-white">Student Login</a></li>
                        <li><a href="login.php" class="text-secondary text-decoration-none hover-white">Organizer Portal</a></li>
                        <li><a href="login.php" class="text-secondary text-decoration-none hover-white">Admin Console</a></li>
                        <li><a href="register.php" class="text-secondary text-decoration-none hover-white">Student Sign-up</a></li>
                    </ul>
                </div>

                <div class="col-12 col-md-4">
                    <h6 class="text-uppercase text-warning font-bold text-xs tracking-wider mb-3">Stay Updated</h6>
                    <p class="text-secondary text-xs mb-3">Subscribe to get notified about upcoming inter-college competitions & hackathons.</p>
                    <form class="d-flex gap-2" onsubmit="event.preventDefault(); alert('Thank you for subscribing to Evenza updates!');">
                        <input type="email" class="form-control rounded-pill bg-dark text-white border-secondary text-xs px-3" placeholder="Enter your college email" required>
                        <button type="submit" class="btn btn-warning rounded-pill px-4 text-xs font-bold text-dark">Subscribe</button>
                    </form>
                </div>
            </div>

            <div class="border-top border-secondary pt-4 d-flex justify-content-between align-items-center flex-wrap gap-2 text-xs text-secondary">
                <div>&copy; <?= date('Y') ?> Evenza Portal. All rights reserved.</div>
                <div class="d-flex gap-3">
                    <a href="about.php" class="text-secondary text-decoration-none">About Us</a>
                    <a href="contact.php" class="text-secondary text-decoration-none">Contact Support</a>
                    <a href="login.php" class="text-secondary text-decoration-none">Single Login</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
