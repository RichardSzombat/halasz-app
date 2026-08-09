import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const isCoarsePointer = window.matchMedia('(hover: none), (pointer: coarse)');

if (isCoarsePointer.matches) {
    let blurScheduled = false;

    const blurActiveScrollControl = () => {
        if (blurScheduled) {
            return;
        }

        blurScheduled = true;

        window.requestAnimationFrame(() => {
            const activeElement = document.activeElement;

            if (activeElement instanceof HTMLElement && activeElement.matches('.js-scroll-blur-control')) {
                activeElement.blur();
            }

            blurScheduled = false;
        });
    };

    window.addEventListener('scroll', blurActiveScrollControl, { passive: true });
    window.addEventListener('touchmove', blurActiveScrollControl, { passive: true });
}
