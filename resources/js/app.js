import './uploader';
import './instagram-archive';
import './chat';

// Появление блоков при прокрутке (работает и после wire:navigate).
const observer = new IntersectionObserver(
    (entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    },
    { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
);

function observeReveals() {
    document.querySelectorAll('.reveal:not(.is-visible)').forEach((el) => observer.observe(el));
}

document.addEventListener('DOMContentLoaded', observeReveals);
document.addEventListener('livewire:navigated', observeReveals);
document.addEventListener('livewire:init', () => {
    Livewire.hook('morphed', () => requestAnimationFrame(observeReveals));
});
