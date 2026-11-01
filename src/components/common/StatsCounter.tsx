import React, { useEffect, useRef, useState } from 'react';
import { usePrefersReducedMotion } from './usePrefersReducedMotion';

interface StatsCounterProps {
  /** Value like "20,570+" or "1,450 Families" — leading numeric part is animated. */
  value: string;
  className?: string;
  /** Animation duration in seconds. */
  duration?: number;
}

/**
 * Animated numeric counter (count-up) triggered via IntersectionObserver.
 * Non-numeric prefix/suffix ("+" etc.) is preserved. Falls back to static
 * text when reduced motion is requested or the value is not parseable.
 */
export const StatsCounter: React.FC<StatsCounterProps> = ({ value, className, duration = 1.4 }) => {
  const reduceMotion = usePrefersReducedMotion();
  const ref = useRef<HTMLSpanElement>(null);
  const [isInView, setIsInView] = useState(reduceMotion);

  useEffect(() => {
    if (reduceMotion) {
      setIsInView(true);
      return;
    }
    const node = ref.current;
    if (!node || typeof IntersectionObserver === 'undefined') {
      setIsInView(true);
      return;
    }
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setIsInView(true);
            observer.disconnect();
          }
        });
      },
      { rootMargin: '0px 0px -40px 0px' }
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, [reduceMotion]);

  const match = value.match(/([\d,]+(?:\.\d+)?)(.*)/s);
  const numericPart = match ? match[1] : null;
  const tail = match ? match[2] ?? '' : value;
  const prefix = match ? value.slice(0, match.index ?? 0) : value;

  const targetValue = numericPart ? parseFloat(numericPart.replace(/,/g, '')) : null;
  const hasSeparators = numericPart ? numericPart.includes(',') || /^\d{5,}$/.test(numericPart) : false;

  if (targetValue === null) {
    return (
      <span ref={ref} className={className}>
        {value}
      </span>
    );
  }

  return (
    <span ref={ref} className={className}>
      <AnimatedNumber
        target={targetValue}
        separate={hasSeparators}
        start={isInView}
        reduced={reduceMotion}
        duration={duration}
      />
      {tail}
      {prefix && <span className="ml-0.5">{prefix.trim()}</span>}
    </span>
  );
};

const AnimatedNumber: React.FC<{
  target: number;
  separate: boolean;
  start: boolean;
  reduced: boolean;
  duration: number;
}> = ({ target, separate, start, reduced, duration }) => {
  const [display, setDisplay] = useState(reduced ? format(target, separate) : '0');

  useEffect(() => {
    if (!start) return;
    if (reduced) {
      setDisplay(format(target, separate));
      return;
    }
    let raf = 0;
    const t0 = performance.now();
    const total = duration * 1000;
    const tick = (now: number) => {
      const p = Math.min((now - t0) / total, 1);
      // easeOutExpo
      const eased = p === 1 ? 1 : 1 - Math.pow(2, -10 * p);
      setDisplay(format(target * eased, separate));
      if (p < 1) raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [start, target, separate, reduced, duration]);

  return <>{display}</>;
};

const format = (n: number, separate: boolean): string => {
  const rounded = Math.round(n);
  if (separate) return rounded.toLocaleString('en-US');
  return (Math.round(n * 10) / 10 % 1 === 0) ? rounded.toLocaleString('en-US') : rounded.toFixed(1);
};
