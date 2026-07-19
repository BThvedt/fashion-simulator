import Link from "next/link";
import styles from "./RetroLanding.module.css";
import NowWithAi from "./NowWithAi";

export default function RetroLanding() {
  return (
    <div className={styles.page}>
      <NowWithAi />
      <div className={styles.marqueeBar}>
        <span className={styles.marqueeText}>
          ★彡 WELCOME TO FASHION SIMULATOR 彡★ &nbsp; Best viewed in Netscape
          Navigator 4.0 @ 800×600 &nbsp; ✦ Sign the guestbook! ✦ &nbsp; Don&apos;t
          forget to bookmark us!!! &nbsp; ★彡★彡★
        </span>
      </div>

      <main className={styles.main}>
        <div className={styles.globe} aria-hidden="true">
          🌐
        </div>

        <h1 className={styles.title}>Fashion Simulator</h1>

        <p className={styles.tagline}>
          ~*~ the <span className={styles.blink}>#1</span> fashion sim on the
          World Wide Web ~*~
        </p>

        <div className={styles.construction}>
          🚧 UNDER CONSTRUCTION 🚧
        </div>

        <div className={styles.enterWrap}>
          <span className={styles.newBadge} aria-hidden="true">
            NEW!
          </span>
          <Link href="/login" className={styles.enterBtn}>
            ►► ENTER / LOG IN ◄◄
          </Link>
        </div>

        <hr className={styles.rainbowHr} />

        <p className={styles.counter}>
          You are visitor number
          <span className={styles.counterDigits}>0031337</span>
          since 1997!
        </p>

        <div className={styles.badges}>
          <span className={styles.badge}>Netscape Now!</span>
          <span className={styles.badge}>Made with Notepad</span>
          <span className={styles.badge}>800×600</span>
          <span className={styles.badge}>Y2K Ready</span>
        </div>
      </main>

      <footer className={styles.footer}>
        <span>© 1997 Fashion Simulator</span>
        <span>
          <span className={styles.mail} aria-hidden="true">
            ✉️
          </span>{" "}
          <a href="/login">webmaster</a>
        </span>
        <span>
          [ <a href="/login">« prev</a> · Fashion WebRing ·{" "}
          <a href="/login">next »</a> ]
        </span>
      </footer>
    </div>
  );
}
