// --- Scroll suave para enlaces internos ---
document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', e => {
        const target = document.querySelector(link.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});

// --- Año dinámico en el footer ---
document.getElementById('year').textContent = new Date().getFullYear();

// --- Animaciones al hacer scroll ---
const animatedElements = document.querySelectorAll('.fade-in-up, .testimonio');

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            
            // Si es un testimonio, aplicar delay progresivo
            if (entry.target.classList.contains('testimonio')) {
                const index = [...document.querySelectorAll('.testimonio')].indexOf(entry.target);
                entry.target.style.transitionDelay = `${index * 1}s`;
            }

            entry.target.classList.add('show');
        }
    });
}, { threshold: 0.2 });

// Observar todos los elementos animados
animatedElements.forEach(el => observer.observe(el));
