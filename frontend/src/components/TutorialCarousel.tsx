"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";

interface Slide {
  emoji: string;
  title: string;
  body: string;
  hint?: string;
}

const SLIDES: Slide[] = [
  {
    emoji: "💡",
    title: "Set the stage",
    body: "Find a spot with good, even lighting and some room to move. When you tap Create Video, allow camera and microphone access and keep your volume up — there's music!",
    hint: "A plain background makes your runway looks pop.",
  },
  {
    emoji: "🧍",
    title: "Frame your full body",
    body: "Step back until your head and your knees are both in the frame. The studio lights dim, the music starts, and we'll let you know the moment you're in position.",
    hint: "Roughly mid-shin up is enough — you don't need your feet.",
  },
  {
    emoji: "📸",
    title: "Strike three poses",
    body: "On each countdown, hit a pose and hold it. We capture three full-body shots — these become your AI runway looks, so give us some attitude!",
    hint: "Switch it up between shots for more variety.",
  },
  {
    emoji: "✨",
    title: "Your closeup",
    body: "When “Time for your Closeup!” slides in, step toward the camera until your face fills the frame. This shot powers your talking-head moment.",
    hint: "Hold your expression — we copy it into the generated portrait.",
  },
  {
    emoji: "🎤",
    title: "Record your line",
    body: "Tap the mic and deliver a short, iconic one-liner — about three seconds. We polish the audio and your character lip-syncs it in the final film.",
    hint: "Keep it punchy: one memorable sentence is perfect.",
  },
  {
    emoji: "🎬",
    title: "That's a wrap",
    body: "That's it! We automatically generate your AI runway looks and stitch together a high-fashion film with poses, a catwalk, camera flashes, and your voice.",
    hint: "Generation takes a few minutes — we'll take you to your video page.",
  },
  {
    emoji: "🔗",
    title: "Share your masterpiece",
    body: "On your video page you can rename it, share a public link with anyone (or make it private again), and delete it whenever you like.",
    hint: "Ready to be fabulous?",
  },
];

/**
 * A simple, self-contained tutorial carousel: numbered slides with a title and
 * copy, prev/next arrows, clickable dots, keyboard (←/→) and touch-swipe
 * support. The final slide surfaces a call-to-action into the capture flow.
 */
export default function TutorialCarousel() {
  const [index, setIndex] = useState(0);
  const touchX = useRef<number | null>(null);
  const count = SLIDES.length;
  const isLast = index === count - 1;

  const go = (next: number) => setIndex(Math.max(0, Math.min(count - 1, next)));
  const prev = () => go(index - 1);
  const next = () => go(index + 1);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "ArrowLeft") prev();
      else if (e.key === "ArrowRight") next();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  });

  return (
    <div className="mx-auto w-full max-w-2xl">
      <p className="mb-3 text-center text-sm font-medium uppercase tracking-wide text-muted-foreground">
        Step {index + 1} of {count}
      </p>

      <div className="flex items-center gap-2 sm:gap-4">
        <Arrow
          direction="left"
          onClick={prev}
          disabled={index === 0}
          label="Previous step"
        />

        <div
          className="relative flex-1 overflow-hidden rounded-3xl border border-border bg-card shadow-sm"
          onTouchStart={(e) => (touchX.current = e.touches[0].clientX)}
          onTouchEnd={(e) => {
            if (touchX.current === null) return;
            const dx = e.changedTouches[0].clientX - touchX.current;
            if (dx > 40) prev();
            else if (dx < -40) next();
            touchX.current = null;
          }}
        >
          <div
            className="flex transition-transform duration-300 ease-out"
            style={{ transform: `translateX(-${index * 100}%)` }}
          >
            {SLIDES.map((slide, i) => (
              <div
                key={i}
                className="flex min-h-[22rem] w-full shrink-0 flex-col items-center justify-center px-6 py-10 text-center sm:px-12"
                aria-hidden={i !== index}
              >
                <div className="relative mb-5">
                  <span className="flex h-20 w-20 items-center justify-center rounded-full bg-primary/10 text-4xl">
                    {slide.emoji}
                  </span>
                  <span className="absolute -right-1 -top-1 flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground shadow">
                    {i + 1}
                  </span>
                </div>
                <h2 className="text-2xl font-semibold tracking-tight text-card-foreground">
                  {slide.title}
                </h2>
                <p className="mt-3 max-w-md text-card-foreground/90">
                  {slide.body}
                </p>
                {slide.hint && (
                  <p className="mt-4 max-w-md text-sm text-muted-foreground">
                    <span className="font-semibold text-foreground">Tip:</span>{" "}
                    {slide.hint}
                  </p>
                )}
              </div>
            ))}
          </div>
        </div>

        <Arrow
          direction="right"
          onClick={next}
          disabled={isLast}
          label="Next step"
        />
      </div>

      {/* Dots */}
      <div className="mt-6 flex items-center justify-center gap-2">
        {SLIDES.map((_, i) => (
          <button
            key={i}
            type="button"
            onClick={() => go(i)}
            aria-label={`Go to step ${i + 1}`}
            aria-current={i === index}
            className={
              i === index
                ? "h-2.5 w-6 rounded-full bg-primary transition-all"
                : "h-2.5 w-2.5 rounded-full bg-muted-foreground/30 transition-all hover:bg-muted-foreground/60"
            }
          />
        ))}
      </div>

      {/* CTA on the last slide */}
      <div className="mt-8 flex justify-center">
        {isLast ? (
          <Link
            href="/create"
            className="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            Start creating
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2.5"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <path d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </Link>
        ) : (
          <button
            type="button"
            onClick={next}
            className="inline-flex items-center gap-2 rounded-full border border-border bg-card px-6 py-3 text-sm font-semibold text-foreground transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            Next
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2.5"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <path d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </button>
        )}
      </div>
    </div>
  );
}

function Arrow({
  direction,
  onClick,
  disabled,
  label,
}: {
  direction: "left" | "right";
  onClick: () => void;
  disabled: boolean;
  label: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-border bg-card text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-card"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="20"
        height="20"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2.5"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
      >
        {direction === "left" ? (
          <path d="M15 18l-6-6 6-6" />
        ) : (
          <path d="M9 18l6-6-6-6" />
        )}
      </svg>
    </button>
  );
}
