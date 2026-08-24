<?php
/**
 * Frontend/contact.php
 * Contact Us Page for Evenza Public Portal
 */
$page_title = "Contact Us";
include 'connection.php';

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name && $email && $message) {
        $flash = 'Thank you for reaching out! Our support team will get back to you within 24 hours.';
    }
}

include 'Header.php';
?>

<div class="container-xl py-5">
    
    <div class="text-center mb-5">
        <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-2">Get In Touch</span>
        <h1 class="fw-black text-dark display-5 mb-2">Contact Support & Partnerships</h1>
        <p class="text-muted text-xs mx-auto" style="max-width: 540px;">
            Have questions about student registration, college partnerships, or hosting an event? Send us a message!
        </p>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            
            <div class="bg-white rounded-5 p-4 p-md-5 border border-dark border-2 shadow-sm" style="box-shadow: 6px 6px 0px #14171a !important;">
                
                <?php if ($flash): ?>
                    <div class="alert alert-success rounded-4 border-0 text-xs py-2 px-3 mb-4" role="alert">
                        <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($flash) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label font-bold text-xs text-uppercase">Your Name *</label>
                        <input type="text" class="form-control rounded-pill text-xs px-3 py-2" name="name" required placeholder="Enter your full name">
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-bold text-xs text-uppercase">Email Address *</label>
                        <input type="email" class="form-control rounded-pill text-xs px-3 py-2" name="email" required placeholder="your.email@domain.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-bold text-xs text-uppercase">Subject</label>
                        <input type="text" class="form-control rounded-pill text-xs px-3 py-2" name="subject" placeholder="e.g. College Partnership Inquiry">
                    </div>

                    <div class="mb-4">
                        <label class="form-label font-bold text-xs text-uppercase">Message *</label>
                        <textarea class="form-control rounded-4 text-xs px-3 py-2" name="message" rows="4" required placeholder="How can we help you?"></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn-capsule-dark justify-content-center py-3 text-xs">
                            Send Message <i class="bi bi-send ms-1"></i>
                        </button>
                    </div>
                </form>

            </div>

        </div>
    </div>

</div>

<?php include 'Footer.php'; ?>
