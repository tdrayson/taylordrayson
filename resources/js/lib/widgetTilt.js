// Velocity-based tilt for the Now-page widgets while they are dragged. The card
// rotates toward the direction of motion, eases back to flat when the pointer
// pauses mid-drag, and springs to level on drop, giving the grid a little
// physical weight. Rotation is applied to the widget's inner root (not the
// grid-stack-item-content, whose entrance animation would override a transform).

const MAX_ROTATION = 10; // degrees at full flick
const VELOCITY_SCALE = 0.4; // pointer px/frame -> degrees
const SMOOTHING = 0.2; // how fast current rotation chases the target
const DECAY = 0.82; // target relaxes toward flat when the pointer is still

/**
 * Wire pointer-velocity tilt into a Gridstack instance.
 *
 * @param {import('gridstack').GridStack} grid
 * @return {() => void} teardown
 */
export function setupWidgetTilt(grid) {
    if (typeof window === 'undefined' || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return () => {};
    }

    let el = null;
    let dragging = false;
    let target = 0;
    let current = 0;
    let lastX = null;
    let raf = null;

    function clear() {
        if (!el) {
            return;
        }

        el.style.transform = '';
        el.style.transition = '';
        el.style.willChange = '';
        el.style.transformOrigin = '';
        el = null;
    }

    function frame() {
        current += (target - current) * SMOOTHING;

        if (dragging) {
            target *= DECAY;
        }

        if (el) {
            el.style.transform = `rotate(${current.toFixed(2)}deg)`;
        }

        if (dragging || Math.abs(current) > 0.02) {
            raf = requestAnimationFrame(frame);
        } else {
            clear();
            raf = null;
        }
    }

    function onMove(event) {
        if (lastX !== null) {
            target = Math.max(-MAX_ROTATION, Math.min(MAX_ROTATION, (event.clientX - lastX) * VELOCITY_SCALE));
        }

        lastX = event.clientX;
    }

    function onStart(event, item) {
        const content = item.querySelector('.grid-stack-item-content');
        el = content?.firstElementChild ?? content;

        if (!el) {
            return;
        }

        dragging = true;
        lastX = null;
        target = 0;
        el.style.transition = 'none';
        el.style.transformOrigin = '50% 50%';
        el.style.willChange = 'transform';
        window.addEventListener('pointermove', onMove);

        if (!raf) {
            raf = requestAnimationFrame(frame);
        }
    }

    function onStop() {
        dragging = false;
        target = 0;
        window.removeEventListener('pointermove', onMove);
    }

    grid.on('dragstart', onStart);
    grid.on('dragstop', onStop);

    return () => {
        grid.off('dragstart');
        grid.off('dragstop');
        window.removeEventListener('pointermove', onMove);

        if (raf) {
            cancelAnimationFrame(raf);
        }

        clear();
    };
}
