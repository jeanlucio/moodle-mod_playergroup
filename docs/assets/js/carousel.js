document.addEventListener('DOMContentLoaded', () => {
  const wraps = document.querySelectorAll('.screenshot-carousel-wrap');

  wraps.forEach((wrap) => {
    const slides = Array.from(wrap.querySelectorAll('.carousel-slide'));
    const dots = Array.from(wrap.querySelectorAll('.carousel-dot'));
    const counter = wrap.querySelector('.carousel-current');
    const prevBtn = wrap.querySelector('.carousel-prev');
    const nextBtn = wrap.querySelector('.carousel-next');

    if (slides.length === 0) {
      return;
    }

    let index = 0;

    const setActive = (newIndex) => {
      index = (newIndex + slides.length) % slides.length;
      slides.forEach((slide, i) => slide.classList.toggle('is-active', i === index));
      dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
      if (counter) {
        counter.textContent = String(index + 1);
      }
    };

    prevBtn.addEventListener('click', () => setActive(index - 1));
    nextBtn.addEventListener('click', () => setActive(index + 1));

    dots.forEach((dot, i) => {
      dot.addEventListener('click', () => setActive(i));
    });

    // Arrow keys drive the carousel only while the lightbox is closed; its
    // own keydown listener already owns them while it's open.
    document.addEventListener('keydown', (event) => {
      const overlay = document.querySelector('.lightbox-overlay');
      if (overlay && !overlay.hidden) {
        return;
      }
      if (event.key === 'ArrowLeft') {
        setActive(index - 1);
      } else if (event.key === 'ArrowRight') {
        setActive(index + 1);
      }
    });
  });
});
