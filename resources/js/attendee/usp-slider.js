// Progressive enhancement for the Reel USPs CSS slider on the picker page ("/")
// Pure CSS scroll-snap works without this; this enables 1-click arrow buttons and indicator dot syncing.

export function initUspSlider() {
  const root = document.getElementById('usp-slider-root');
  if (!root) return;

  const track = document.getElementById('usp-slider-track');
  const prevBtn = document.getElementById('usp-slider-prev');
  const nextBtn = document.getElementById('usp-slider-next');
  const dots = Array.from(root.querySelectorAll('.usp-slider__dot'));
  const slides = Array.from(root.querySelectorAll('.usp-slide'));

  if (!track || slides.length === 0) return;

  let activeIndex = 0;

  function updateState(index) {
    activeIndex = Math.max(0, Math.min(index, slides.length - 1));

    dots.forEach((dot, i) => {
      const isActive = i === activeIndex;
      dot.classList.toggle('is-active', isActive);
      dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
      dot.setAttribute('tabindex', isActive ? '0' : '-1');
    });

    if (prevBtn) prevBtn.disabled = activeIndex === 0;
    if (nextBtn) nextBtn.disabled = activeIndex === slides.length - 1;
  }

  function scrollToIndex(index) {
    if (index < 0 || index >= slides.length) return;
    const targetSlide = slides[index];
    if (targetSlide) {
      track.scrollTo({
        left: targetSlide.offsetLeft,
        behavior: 'smooth'
      });
    }
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      scrollToIndex(activeIndex - 1);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      scrollToIndex(activeIndex + 1);
    });
  }

  dots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
      scrollToIndex(index);
    });
  });

  // Track scrolling: sync dots based on center of current scroll viewport
  let scrollTimeout = null;
  track.addEventListener('scroll', () => {
    if (scrollTimeout) cancelAnimationFrame(scrollTimeout);
    scrollTimeout = requestAnimationFrame(() => {
      const scrollCenter = track.scrollLeft + (track.clientWidth / 2);
      let closestIdx = 0;
      let minDiff = Infinity;

      slides.forEach((slide, idx) => {
        const slideCenter = slide.offsetLeft + (slide.clientWidth / 2);
        const diff = Math.abs(slideCenter - scrollCenter);
        if (diff < minDiff) {
          minDiff = diff;
          closestIdx = idx;
        }
      });

      if (closestIdx !== activeIndex) {
        updateState(closestIdx);
      }
    });
  }, { passive: true });

  // Initial state setup
  updateState(0);
}
