<?php /** Step 1: Welcome */ ?>
<div class="text-center">
    <div class="mb-4">
        <i class="bi bi-whatsapp" style="font-size:60px;color:var(--primary);"></i>
    </div>
    <h3 class="mb-3">Welcome to WaMark Installation</h3>
    <p class="text-muted mb-4">
        Thank you for choosing WaMark - the White Label WhatsApp Marketing & Automation Platform.<br>
        This wizard will guide you through the setup process in a few simple steps.
    </p>
    
    <div class="text-start bg-light rounded-3 p-4 mb-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary"></i> Before you begin, ensure you have:</h6>
        <ul class="list-unstyled mb-0">
            <li class="mb-2"><i class="bi bi-check-circle text-success"></i> PHP 8.0+ installed on your server</li>
            <li class="mb-2"><i class="bi bi-check-circle text-success"></i> MySQL database credentials ready</li>
            <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Write permissions on required directories</li>
            <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Your admin account details prepared</li>
        </ul>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="check_requirements">
        <button type="submit" class="btn btn-primary btn-lg">
            Start Installation <i class="bi bi-arrow-right"></i>
        </button>
    </form>
</div>
