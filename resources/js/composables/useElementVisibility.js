import { watch, onBeforeUnmount } from 'vue';

/**
 * useElementVisibility — fire a callback once when an element becomes visible.
 *
 * Uses IntersectionObserver, which correctly accounts for overflow clipping
 * from ancestor elements (e.g. Bard sets that collapse via max-height/overflow).
 * The observer fires synchronously with the current state, so already-visible
 * elements trigger the callback immediately on the next microtask.
 *
 * Re-attaches the observer if the underlying element ref changes.
 *
 * @param {import('vue').Ref<HTMLElement | null>} elRef
 * @param {() => void} onVisible - Fired once, then the observer disconnects.
 */
export function useElementVisibility(elRef, onVisible) {
    let observer = null;
    let fired = false;

    function attach(el) {
        if (!el || fired) return;
        if (observer) observer.disconnect();

        observer = new IntersectionObserver(
            (entries) => {
                const entry = entries[0];
                if (entry.isIntersecting && entry.intersectionRect.width > 0 && !fired) {
                    fired = true;
                    observer.disconnect();
                    observer = null;
                    onVisible();
                }
            },
            { threshold: 0 }
        );
        observer.observe(el);
    }

    watch(elRef, (el) => attach(el), { immediate: true, flush: 'post' });

    onBeforeUnmount(() => {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
    });
}
