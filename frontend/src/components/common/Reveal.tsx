import React, { useEffect, useRef, useState } from 'react';
import { usePrefersReducedMotion } from './usePrefersReducedMotion';

interface RevealProps {
  children: React.ReactNode;
  /** Stagger delay in ms for sibling reveals. */
  delay?: number;
  className?: string;
  /** Initial slide distance in px. Defaults to 18. */
  y?: number;
}

/**
 * Scroll-triggered reveal built on IntersectionObserver (zero runtime deps).
 * Renders static visible content when the user prefers reduced motion.
 */
export const Reveal: React.FC<RevealProps> = ({ children, delay = 0, className, y = 18 }) => {
  const ref = useRef<HTMLDivElement>(null);
  const reduceMotion = usePrefersReducedMotion();
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    if (reduceMotion) return;
    const node = ref.current;
    if (!node || typeof IntersectionObserver === 'undefined') {
      setVisible(true);
      return;
    }
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setVisible(true);
            observer.disconnect();
          }
        });
      },
      { rootMargin: '0px 0px -60px 0px', threshold: 0.05 }
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, [reduceMotion]);

  const stateClass = visible || reduceMotion ? 'reveal-in' : 'reveal-hidden';

  return (
    <div
      ref={ref}
      className={`${className ?? ''} ${stateClass}`}
      style={{ ['--reveal-y' as string]: `${y}px`, transitionDelay: `${delay}ms` }}
    >
      {children}
    </div>
  );
};
