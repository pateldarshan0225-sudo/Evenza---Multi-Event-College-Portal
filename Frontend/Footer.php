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
                    <div class="mb-3">
                        <img src="../assets/images/evenza-logo-white.svg" alt="Evenza Portal Logo" height="38" style="height: 38px; width: auto;" />
                    </div>
                    <p class="text-secondary text-xs leading-relaxed pe-md-4">
                        The unified inter-college event ecosystem connecting accredited universities, partner institutions, campus event organizers, and students. Discover technical hackathons, cultural festivals, sports meets, and executive workshops in one seamless platform.
                    </p>
                </div>

                <div class="col-6 col-md-2">
                    <h6 class="text-uppercase text-warning font-bold text-xs tracking-wider mb-3">Navigation</h6>
                    <ul class="list-unstyled text-xs d-flex flex-column gap-2 mb-0">
                        <li><a href="index.php" class="text-secondary text-decoration-none hover-white">Home</a></li>
                        <li><a href="colleges.php" class="text-secondary text-decoration-none hover-white">Partner Institutions</a></li>
                        <li><a href="events.php" class="text-secondary text-decoration-none hover-white">Explore Competitions</a></li>
                        <li><a href="categories.php" class="text-secondary text-decoration-none hover-white">Event Categories</a></li>
                    </ul>
                </div>

                <div class="col-6 col-md-2">
                    <h6 class="text-uppercase text-warning font-bold text-xs tracking-wider mb-3">Portals</h6>
                    <ul class="list-unstyled text-xs d-flex flex-column gap-2 mb-0">
                        <li><a href="login.php" class="text-secondary text-decoration-none hover-white">Student Portal</a></li>
                        <li><a href="login.php" class="text-secondary text-decoration-none hover-white">Organizer Portal</a></li>
                        <li><a href="register.php" class="text-secondary text-decoration-none hover-white">Student Registration</a></li>
                        <li><a href="register_college.php" class="text-secondary text-decoration-none hover-white">College Registration</a></li>
                        <li><a href="register_university.php" class="text-secondary text-decoration-none hover-white">University Registration</a></li>
                    </ul>
                </div>

                <div class="col-12 col-md-4">
                    <h6 class="text-uppercase text-warning font-bold text-xs tracking-wider mb-3">Stay Informed</h6>
                    <p class="text-secondary text-xs mb-3">Subscribe to receive notifications regarding upcoming national hackathons, fests, and registration deadlines.</p>
                    <form class="d-flex gap-2" onsubmit="event.preventDefault(); alert('Thank you for subscribing to Evenza updates!');">
                        <input type="email" class="form-control rounded-pill bg-dark text-white border-secondary text-xs px-3" placeholder="Enter your institutional email" required>
                        <button type="submit" class="btn btn-warning rounded-pill px-4 text-xs font-bold text-dark">Subscribe</button>
                    </form>
                </div>
            </div>

            <div class="border-top border-secondary pt-4 d-flex justify-content-between align-items-center flex-wrap gap-2 text-xs text-secondary">
                <div>&copy; <?= date('Y') ?> Evenza Platform. All rights reserved.</div>
                <div class="d-flex gap-3">
                    <a href="about.php" class="text-secondary text-decoration-none">About Us</a>
                    <a href="contact.php" class="text-secondary text-decoration-none">Contact & Support</a>
                    <a href="login.php" class="text-secondary text-decoration-none">Unified Login</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
