/**
 * MMe Profile Carousel widget. No dependencies.
 */
(function () {
  'use strict';

  var SWIPE_MIN = 40;

  function init(root) {
    if (!root || root.mmePc) {
      return;
    }

    var slides = Array.prototype.slice.call(root.querySelectorAll('.mme-pc__slide'));
    if (slides.length < 2) {
      return;
    }

    var options = {};
    try {
      options = JSON.parse(root.getAttribute('data-mme-pc') || '{}');
    } catch (e) {}

    var tabs = Array.prototype.slice.call(root.querySelectorAll('.mme-pc__thumb'));
    var strip = root.querySelector('.mme-pc__thumbs');
    var prev = root.querySelector('.mme-pc__arrow--prev');
    var next = root.querySelector('.mme-pc__arrow--next');
    var bar = root.querySelector('.mme-pc__progress span');
    var stage = root.querySelector('.mme-pc__stage');
    var editMode = !!(window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode());
    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var current = 0;
    var autoplay = !!options.autoplay && !editMode;
    var delay = Math.max(2000, parseInt(options.delay, 10) || 6000);
    var loop = options.loop !== false;
    var timer = null;
    var startedAt = 0;
    var remaining = delay;
    var holds = {};
    var heightTimer = null;

    root.mmePc = { goTo: goTo };

    function wrap(index) {
      var count = slides.length;
      if (loop) {
        return (index % count + count) % count;
      }
      return Math.max(0, Math.min(count - 1, index));
    }

    function goTo(index, opts) {
      opts = opts || {};
      index = wrap(index);
      if (index === current) {
        return;
      }

      root.setAttribute('data-dir', opts.dir || (index > current ? 'next' : 'prev'));
      var fromHeight = stage.offsetHeight;

      slides.forEach(function (slide, i) {
        slide.classList.toggle('is-leaving', i === current);
        slide.classList.toggle('is-active', i === index);
      });

      tabs.forEach(function (tab, i) {
        var active = i === index;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
        tab.setAttribute('tabindex', active ? '0' : '-1');
      });

      current = index;
      animateHeight(fromHeight);
      updateArrows();
      revealThumb(index);
      if (opts.focus && tabs[index]) {
        tabs[index].focus({ preventScroll: true });
      }
      restart();
    }

    // On mobile the stage is as tall as the current slide: glide to the new height.
    function animateHeight(from) {
      var to = stage.offsetHeight;
      clearTimeout(heightTimer);
      if (from === to || reducedMotion) {
        return;
      }
      stage.classList.add('is-resizing');
      stage.style.height = from + 'px';
      void stage.offsetHeight;
      stage.style.transition = 'height 0.4s cubic-bezier(0.22, 0.61, 0.36, 1)';
      stage.style.height = to + 'px';
      heightTimer = setTimeout(function () {
        stage.classList.remove('is-resizing');
        stage.style.height = '';
        stage.style.transition = '';
      }, 420);
    }

    function step(delta) {
      goTo(current + delta, { dir: delta > 0 ? 'next' : 'prev' });
    }

    function updateArrows() {
      if (loop) {
        return;
      }
      if (prev) {
        prev.disabled = current === 0;
      }
      if (next) {
        next.disabled = current === slides.length - 1;
      }
    }

    // Scroll the strip only (scrollIntoView would also scroll the page).
    function revealThumb(index) {
      var tab = tabs[index];
      if (!strip || !tab) {
        return;
      }
      var left = tab.offsetLeft - strip.offsetLeft;
      var target = left - (strip.clientWidth - tab.offsetWidth) / 2;
      strip.scrollTo({ left: Math.max(0, target), behavior: reducedMotion ? 'auto' : 'smooth' });
    }

    /* Autoplay: a timer with pause / resume that keeps the time left, mirrored by the progress bar. */

    function paused() {
      return Object.keys(holds).some(function (key) {
        return holds[key];
      });
    }

    function animateBar(from, duration) {
      if (!bar) {
        return;
      }
      bar.style.transition = 'none';
      bar.style.transform = 'scaleX(' + from + ')';
      if (duration > 0) {
        void bar.offsetWidth;
        bar.style.transition = 'transform ' + duration + 'ms linear';
        bar.style.transform = 'scaleX(1)';
      }
    }

    function run() {
      clearTimeout(timer);
      if (!autoplay || paused()) {
        return;
      }
      startedAt = Date.now();
      animateBar(1 - remaining / delay, remaining);
      timer = setTimeout(function () {
        if (!loop && current === slides.length - 1) {
          goTo(0, { dir: 'next' });
        } else {
          step(1);
        }
      }, remaining);
    }

    function stop() {
      if (!autoplay) {
        return;
      }
      if (timer) {
        clearTimeout(timer);
        timer = null;
        remaining = Math.max(0, remaining - (Date.now() - startedAt));
      }
      animateBar(1 - remaining / delay, 0);
    }

    function restart() {
      remaining = delay;
      run();
    }

    function hold(reason, on) {
      var was = paused();
      holds[reason] = on;
      if (paused() && !was) {
        stop();
      } else if (!paused() && was) {
        run();
      }
    }

    /* Events */

    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () {
        goTo(i);
      });
    });

    if (strip) {
      strip.addEventListener('keydown', function (event) {
        var map = { ArrowRight: current + 1, ArrowLeft: current - 1, Home: 0, End: slides.length - 1 };
        if (!(event.key in map)) {
          return;
        }
        event.preventDefault();
        var target = map[event.key];
        if (!loop || event.key === 'Home' || event.key === 'End') {
          target = Math.max(0, Math.min(slides.length - 1, target));
        }
        goTo(target, { focus: true });
      });
    }

    if (prev) {
      prev.addEventListener('click', function () {
        step(-1);
      });
    }
    if (next) {
      next.addEventListener('click', function () {
        step(1);
      });
    }

    // Swipe on the slide (touch and pen; mouse drags select text instead).
    var startX = null;
    var startY = 0;
    stage.addEventListener('pointerdown', function (event) {
      if (event.pointerType === 'mouse') {
        return;
      }
      startX = event.clientX;
      startY = event.clientY;
    });
    stage.addEventListener('pointerup', function (event) {
      if (startX === null) {
        return;
      }
      var dx = event.clientX - startX;
      var dy = event.clientY - startY;
      startX = null;
      if (Math.abs(dx) > SWIPE_MIN && Math.abs(dx) > Math.abs(dy) * 1.5) {
        step(dx < 0 ? 1 : -1);
      }
    });
    stage.addEventListener('pointercancel', function () {
      startX = null;
    });
    stage.style.touchAction = 'pan-y';

    if (autoplay) {
      root.addEventListener('mouseenter', function () {
        hold('hover', true);
      });
      root.addEventListener('mouseleave', function () {
        hold('hover', false);
      });
      root.addEventListener('focusin', function () {
        hold('focus', true);
      });
      root.addEventListener('focusout', function (event) {
        if (!root.contains(event.relatedTarget)) {
          hold('focus', false);
        }
      });
      document.addEventListener('visibilitychange', function () {
        hold('hidden', document.hidden);
      });

      // Only run while the carousel is on screen.
      if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
          hold('offscreen', !entries[0].isIntersecting);
        }, { threshold: 0.25 }).observe(root);
      }
      run();
    }

    updateArrows();
  }

  function initAll(context) {
    var scope = context && context.querySelectorAll ? context : document;
    Array.prototype.forEach.call(scope.querySelectorAll('.mme-pc'), init);
  }

  // Elementor frontend + editor: runs on every (re)render of the widget.
  function registerElementorHook() {
    if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
      return false;
    }
    window.elementorFrontend.hooks.addAction('frontend/element_ready/mme-profile-carousel.default', function ($scope) {
      initAll($scope && $scope[0]);
    });
    return true;
  }

  if (!registerElementorHook() && window.jQuery) {
    window.jQuery(window).on('elementor/frontend/init', registerElementorHook);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAll();
    });
  } else {
    initAll();
  }

  window.mmeProfileCarousel = { init: initAll };
})();
