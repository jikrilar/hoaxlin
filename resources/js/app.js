import './bootstrap';

// ─── hoaxlin.id — Main JavaScript ─────────────────────────────────────────────

// Modern browsers support scroll-behavior out of the box


/**
 * Global utility: debounce
 */
function debounce(fn, delay = 250) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
}

/**
 * Animate numbers (counter effect)
 */
function animateCounter(el, target, duration = 1500) {
    const start = 0;
    const startTime = performance.now();
    const isPercentage = el.dataset.suffix === '%';
    const update = (now) => {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target) + (isPercentage ? '%' : '');
        if (progress < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
}

/**
 * Intersection Observer for reveal animations
 */
const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0) scale(1)';
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

/**
 * Initialize all animations on DOM load
 */
document.addEventListener('DOMContentLoaded', () => {
    // Reveal elements
    document.querySelectorAll('.feature-card, .stat-card, .glass-card').forEach(el => {
        if (!el.style.opacity) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px) scale(0.98)';
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            revealObserver.observe(el);
        }
    });

    // Navbar scroll
    const navbar = document.getElementById('main-navbar');
    if (navbar) {
        const handleScroll = debounce(() => {
            navbar.classList.toggle('scrolled', window.scrollY > 30);
        }, 10);
        window.addEventListener('scroll', handleScroll, { passive: true });
    }

    // Flash messages auto-dismiss
    const flash = document.querySelector('[data-flash]');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            flash.style.transition = 'all 0.4s ease';
            setTimeout(() => flash.remove(), 400);
        }, 4000);
    }

    console.log('%c🔍 hoaxlin.id — Sistem Deteksi Hoax BERT', 'background:#6366f1; color:white; padding:4px 8px; border-radius:4px; font-weight:bold;');
});

/**
 * Export utils for inline scripts
 */
window.Hoaxlin = { debounce, animateCounter };
