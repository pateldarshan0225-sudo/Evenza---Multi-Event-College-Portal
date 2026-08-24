<?php
/**
 * Frontend/contact.php
 * Ultra-Premium Contact Us & Support Page for Evenza
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
        $flash = 'Thank you for reaching out! Our support team will get back to you within 2 hours.';
    }
}

include 'Header.php';
?>

<style>
    :root {
        --bg-cream-canvas: #fdfbf7;
        --bg-dark-accent: #14171a;
        --color-gold-accent: #ffd13b;
    }

    .contact-hero-section {
        background-color: #fffdf7;
        padding: 56px 0 48px 0;
        position: relative;
    }

    .hero-kpi-pill-badge {
        background: #ffffff;
        border: 1.5px solid #14171a;
        box-shadow: 3px 3px 0px #ffd13b;
        color: #14171a;
        font-weight: 800;
        font-size: 11px;
        padding: 6px 16px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .contact-grid-canvas {
        background-color: var(--bg-cream-canvas);
        padding: 64px 0 88px 0;
        border-top: 1px solid #f1f5f9;
        min-height: 500px;
    }

    .contact-info-card-luxury {
        background: #ffffff;
        border-radius: 28px;
        padding: 32px;
        border: 1.5px solid #eae6df;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .contact-form-card-bespoke {
        background: #ffffff;
        border-radius: 28px;
        padding: 36px;
        border: 1.5px solid #eae6df;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        transition: all 0.3s ease;
    }

    .contact-form-card-bespoke:hover {
        border-color: #14171a;
        box-shadow: 0 20px 42px rgba(0, 0, 0, 0.06);
    }

    .contact-item-pod {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 20px;
        padding: 18px 20px;
        margin-bottom: 16px;
        transition: all 0.2s ease;
    }

    .contact-item-pod:hover {
        background: #ffffff;
        border-color: #14171a;
        box-shadow: 4px 4px 0px #ffd13b;
    }

    .contact-icon-circle {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: #ffd13b;
        border: 1.5px solid #14171a;
        color: #14171a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 800;
        flex-shrink: 0;
    }

    .form-control-luxury, .form-select-luxury {
        background: #f8fafc !important;
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 16px !important;
        padding: 12px 18px !important;
        font-size: 13px !important;
        color: #14171a !important;
        transition: all 0.2s ease !important;
    }

    .form-control-luxury:focus, .form-select-luxury:focus {
        background: #ffffff !important;
        border-color: #14171a !important;
        box-shadow: 0 0 0 4px rgba(20, 23, 26, 0.06) !important;
        outline: none !important;
    }

    .btn-search-capsule-dark {
        background: #14171a;
        color: #ffffff;
        border-radius: 9999px;
        font-size: 13px;
        font-weight: 800;
        padding: 12px 28px;
        border: 1.5px solid #14171a;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-search-capsule-dark:hover {
        background: #ffd13b;
        color: #14171a;
        border-color: #14171a;
    }

    .organizer-hotline-box {
        background: #14171a;
        color: #ffffff;
        border-radius: 20px;
        padding: 20px;
        border: 2px solid #14171a;
        box-shadow: 4px 4px 0px #ffd13b;
    }
</style>

<!-- PAGE HERO -->
<section class="contact-hero-section">
    <div class="container-xl text-center">
        <span class="hero-kpi-pill-badge mb-3">
            <i class="bi bi-chat-dots-fill text-warning"></i> Fast Support • Response < 2 Hours
        </span>
        <h1 class="fw-black text-dark display-5 mb-2" style="letter-spacing: -1.5px;">Contact Support & Partnerships</h1>
        <p class="text-muted text-xs mx-auto" style="max-width: 580px;">
            Have questions about student registration, college fests, or hosting an event? Connect with our dedicated support desk.
        </p>
    </div>
</section>

<!-- CONTACT GRID CANVAS SECTION -->
<section class="contact-grid-canvas">
    <div class="container-xl">
        
        <?php if ($flash): ?>
            <div class="alert bg-success text-white rounded-4 border-0 p-3 mb-4 d-flex align-items-center gap-2 font-semibold text-xs shadow-sm">
                <i class="bi bi-check-circle-fill fs-5 me-1"></i> <?= htmlspecialchars($flash) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- LEFT COLUMN: DIRECT CONTACT DETAILS -->
            <div class="col-12 col-lg-5">
                <div class="contact-info-card-luxury">
                    <div>
                        <span class="badge bg-warning text-dark font-bold px-3 py-1.5 rounded-pill text-xs mb-3">Direct Channels</span>
                        <h3 class="fw-black text-dark mb-4 fs-4" style="letter-spacing: -0.5px;">We're here to help your fest succeed.</h3>

                        <!-- Email Support Pod -->
                        <div class="contact-item-pod d-flex align-items-center gap-3">
                            <div class="contact-icon-circle">
                                <i class="bi bi-envelope-fill"></i>
                            </div>
                            <div>
                                <small class="text-muted text-xs font-semibold d-block">Support Email</small>
                                <strong class="text-dark fs-6 font-black">support@evenza.com</strong>
                            </div>
                        </div>

                        <!-- Phone Helpline Pod -->
                        <div class="contact-item-pod d-flex align-items-center gap-3">
                            <div class="contact-icon-circle">
                                <i class="bi bi-telephone-fill"></i>
                            </div>
                            <div>
                                <small class="text-muted text-xs font-semibold d-block">Student & Fest Helpline</small>
                                <strong class="text-dark fs-6 font-black">+91 98765 43210</strong>
                            </div>
                        </div>

                        <!-- HQ Location Pod -->
                        <div class="contact-item-pod d-flex align-items-center gap-3">
                            <div class="contact-icon-circle">
                                <i class="bi bi-geo-alt-fill"></i>
                            </div>
                            <div>
                                <small class="text-muted text-xs font-semibold d-block">HQ Location</small>
                                <strong class="text-dark fs-6 font-black">Evenza Tech Park, University Circle</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Hotline Box -->
                    <div class="organizer-hotline-box mt-4">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-warning text-dark font-bold rounded-pill text-xs">Fest Organizers</span>
                            <span class="text-warning text-xs font-bold">24/7 Hotline</span>
                        </div>
                        <h6 class="fw-black text-white mb-1 fs-6">Hosting a Fest Tomorrow?</h6>
                        <p class="text-white-50 text-xs mb-0" style="line-height: 1.4;">
                            Get immediate priority support for live registration desks and scanner passes.
                        </p>
                    </div>

                </div>
            </div>

            <!-- RIGHT COLUMN: BESPOKE CONTACT FORM -->
            <div class="col-12 col-lg-7">
                <div class="contact-form-card-bespoke">
                    <h3 class="fw-black text-dark mb-1 fs-4" style="letter-spacing: -0.5px;">Send Us a Message</h3>
                    <p class="text-muted text-xs mb-4">Fill out the form below and our team will get back to you shortly.</p>

                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label font-bold text-xs text-dark mb-1">YOUR FULL NAME *</label>
                                <input type="text" class="form-control form-control-luxury" name="name" required autocomplete="off" placeholder="John Doe">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label font-bold text-xs text-dark mb-1">EMAIL ADDRESS *</label>
                                <input type="email" class="form-control form-control-luxury" name="email" required autocomplete="off" placeholder="john@university.edu">
                            </div>

                            <div class="col-12">
                                <label class="form-label font-bold text-xs text-dark mb-1">INQUIRY SUBJECT</label>
                                <select class="form-select form-select-luxury" name="subject">
                                    <option value="General Inquiry">General Query</option>
                                    <option value="Student Registration Help">Student Registration Help</option>
                                    <option value="College Fest Host Partnership">College Fest Host Partnership</option>
                                    <option value="Technical Support & Bug Report">Technical Support & Bug Report</option>
                                    <option value="Sponsorship & Media">Sponsorship & Media Inquiry</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label font-bold text-xs text-dark mb-1">YOUR MESSAGE *</label>
                                <textarea class="form-control form-control-luxury" name="message" rows="5" required placeholder="How can we help you today? Provide details about your query..."></textarea>
                            </div>

                            <div class="col-12 text-end pt-2">
                                <button type="submit" class="btn-search-capsule-dark w-100 justify-content-center py-3">
                                    Send Message <i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>

    </div>
</section>

<?php include 'Footer.php'; ?>
