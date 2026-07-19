"use client";

import { useEffect, useRef } from "react";
import styles from "./NowWithAi.module.css";

export default function NowWithAi() {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    const viewport = () => ({
      w: document.documentElement.clientWidth,
      h: document.documentElement.clientHeight,
    });

    let x = Math.random() * Math.max(1, viewport().w - el.offsetWidth);
    let y = Math.random() * Math.max(1, viewport().h - el.offsetHeight);
    const speed = 1.3; // px/frame — slow DVD-logo drift
    let dx = Math.random() < 0.5 ? -speed : speed;
    let dy = Math.random() < 0.5 ? -speed : speed;

    const place = () => {
      el.style.transform = `translate(${x}px, ${y}px)`;
    };
    place();

    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduce) return;

    let raf = 0;
    const step = () => {
      const { w, h } = viewport();
      const maxX = Math.max(0, w - el.offsetWidth);
      const maxY = Math.max(0, h - el.offsetHeight);

      x += dx;
      y += dy;

      if (x <= 0) {
        x = 0;
        dx = Math.abs(dx);
      } else if (x >= maxX) {
        x = maxX;
        dx = -Math.abs(dx);
      }
      if (y <= 0) {
        y = 0;
        dy = Math.abs(dy);
      } else if (y >= maxY) {
        y = maxY;
        dy = -Math.abs(dy);
      }

      place();
      raf = requestAnimationFrame(step);
    };
    raf = requestAnimationFrame(step);

    const onResize = () => {
      x = Math.min(x, Math.max(0, viewport().w - el.offsetWidth));
      y = Math.min(y, Math.max(0, viewport().h - el.offsetHeight));
      place();
    };
    window.addEventListener("resize", onResize);

    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener("resize", onResize);
    };
  }, []);

  return (
    <div ref={ref} className={styles.floater} aria-hidden="true">
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src="/now-with.png" alt="" className={styles.nowWith} />
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src="/AI.gif" alt="" className={styles.ai} />
    </div>
  );
}
