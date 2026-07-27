"use client";

import { useEffect, useRef } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { createFashionVideo } from "@/app/actions/content";
import styles from "./CreateStudio.module.css";

/* Minimal typings for the parts of @mediapipe/tasks-vision we use. */
type Landmark = { x: number; y: number; z: number; visibility?: number };
type Connection = { start: number; end: number };
interface PoseResult {
  landmarks?: Landmark[][];
}
interface PoseLandmarkerInstance {
  detectForVideo(video: HTMLVideoElement, timestamp: number): PoseResult;
  close(): void;
}
interface FaceResult {
  faceLandmarks?: Landmark[][];
}
interface FaceLandmarkerInstance {
  detectForVideo(video: HTMLVideoElement, timestamp: number): FaceResult;
  close(): void;
}
interface DrawingUtilsInstance {
  drawConnectors(
    landmarks: Landmark[],
    connections: Connection[],
    options?: { color?: string; lineWidth?: number }
  ): void;
}
interface VisionModule {
  FilesetResolver: {
    forVisionTasks(wasmPath: string): Promise<unknown>;
  };
  PoseLandmarker: {
    createFromOptions(
      fileset: unknown,
      options: unknown
    ): Promise<PoseLandmarkerInstance>;
    POSE_CONNECTIONS: Connection[];
  };
  FaceLandmarker: {
    createFromOptions(
      fileset: unknown,
      options: unknown
    ): Promise<FaceLandmarkerInstance>;
    FACE_LANDMARKS_FACE_OVAL: Connection[];
    FACE_LANDMARKS_LEFT_EYE: Connection[];
    FACE_LANDMARKS_RIGHT_EYE: Connection[];
    FACE_LANDMARKS_LEFT_EYEBROW: Connection[];
    FACE_LANDMARKS_RIGHT_EYEBROW: Connection[];
    FACE_LANDMARKS_LIPS: Connection[];
  };
  DrawingUtils: new (ctx: CanvasRenderingContext2D) => DrawingUtilsInstance;
}

/** Reads a Blob into a base64 data URL (e.g. "data:audio/webm;base64,..."). */
function blobToDataUrl(blob: Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result as string);
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(blob);
  });
}

export default function CreateStudio() {
  const rootRef = useRef<HTMLDivElement>(null);
  const router = useRouter();
  const routerRef = useRef(router);
  routerRef.current = router;

  useEffect(() => {
    const root = rootRef.current;
    if (!root) return;

    const q = <T extends Element>(name: string): T =>
      root.querySelector(`[data-fc="${name}"]`) as T;

    const video = q<HTMLVideoElement>("video");
    const monitor = q<HTMLElement>("monitor");
    const stateEl = q<HTMLElement>("state");
    const coverage = q<HTMLElement>("coverage");
    const coverageTitle = coverage.querySelector("h2")!;
    const coverageHint = coverage.querySelector("p")!;
    const spotlights = q<HTMLElement>("spotlights");
    const skylights = q<HTMLElement>("skylights");
    const skCanvas = q<HTMLCanvasElement>("skeleton");
    const skCtx = skCanvas.getContext("2d")!;
    const perm = q<HTMLElement>("perm");
    const retry = q<HTMLButtonElement>("retry");
    const hint = q<HTMLElement>("startHint");
    const startEl = q<HTMLElement>("start");
    const readyEl = q<HTMLElement>("ready");
    const introLinks = q<HTMLElement>("introLinks");
    const goBtn = q<HTMLButtonElement>("go");
    const meterLabel = q<HTMLElement>("meterLabel");
    const meterBars = Array.from(
      root.querySelectorAll('[data-fc="meterBar"]')
    ) as HTMLElement[];
    const poseUI = q<HTMLElement>("poseUi");
    const line1 = q<HTMLElement>("line1");
    const line2 = q<HTMLElement>("line2");
    const leader = q<HTMLElement>("leader");
    const leaderNum = leader.querySelector("span")!;
    const flash = q<HTMLElement>("flash");
    const shot = q<HTMLImageElement>("shot");
    const againBtn = q<HTMLButtonElement>("again");
    const voiceUI = q<HTMLElement>("voiceUi");
    const micBtn = q<HTMLButtonElement>("micBtn");
    const micRingFill = q<SVGCircleElement>("micFill");
    const micMissing = q<HTMLElement>("micMissing");
    const voiceReview = q<HTMLElement>("voiceReview");
    const voiceIntro = q<HTMLElement>("voiceIntro");
    const gotIt = q<HTMLElement>("gotIt");
    const voiceDone = q<HTMLButtonElement>("voiceDone");

    const show = (el: Element, on: boolean) =>
      el.setAttribute("data-show", on ? "true" : "false");

    // Force dark mode while capturing (looks better), then restore the user's
    // real preference on finish / unmount. We never write to localStorage here,
    // so the persisted light/dark choice is untouched.
    let prevDark = false;
    let themeForced = false;
    const enterDark = () => {
      if (themeForced) return;
      prevDark = document.documentElement.classList.contains("dark");
      document.documentElement.classList.add("dark");
      themeForced = true;
      // The tutorial/privacy links only belong on the intro screens, before
      // the studio dims into showtime mode.
      show(introLinks, false);
    };
    const restoreTheme = () => {
      if (!themeForced) return;
      document.documentElement.classList.toggle("dark", prevDark);
      themeForced = false;
      show(introLinks, true);
    };

    // Background music for the capture flow: pick one track at random, loop it
    // with a short gap of silence, and pause it while the mic is recording.
    const SONGS = [
      "/recording_songs/1.mp3",
      "/recording_songs/2.mp3",
      "/recording_songs/3.mp3",
      "/recording_songs/4.mp3",
    ];
    let bgm: HTMLAudioElement | null = null;
    let bgmTimer = 0;
    let bgmStopped = true;
    // Which track was picked, saved to the node so the video render can reuse it.
    let selectedSong = "";
    const LOOP_GAP_MS = 1200;

    const startMusic = () => {
      bgmStopped = false;
      if (!bgm) {
        const src = SONGS[Math.floor(Math.random() * SONGS.length)];
        selectedSong = src.split("/").pop() ?? src;
        bgm = new Audio(src);
        bgm.volume = 0.45;
        bgm.addEventListener("ended", () => {
          if (bgmStopped) return;
          bgmTimer = window.setTimeout(() => {
            if (bgmStopped || !bgm) return;
            bgm.currentTime = 0;
            bgm.play().catch(() => {});
          }, LOOP_GAP_MS);
        });
      }
      bgm.play().catch(() => {});
    };
    const pauseMusic = () => {
      clearTimeout(bgmTimer);
      bgm?.pause();
    };
    const resumeMusic = () => {
      if (!bgmStopped) bgm?.play().catch(() => {});
    };
    const stopMusic = () => {
      bgmStopped = true;
      clearTimeout(bgmTimer);
      bgm?.pause();
    };

    // Live mic level meter shown on the "ready" screen so the user can confirm
    // their microphone is picking up sound before starting.
    let meterCtx: AudioContext | null = null;
    let meterAnalyser: AnalyserNode | null = null;
    let meterSource: MediaStreamAudioSourceNode | null = null;
    let meterGain: GainNode | null = null;
    let meterData: Uint8Array<ArrayBuffer> | null = null;
    let meterRAF = 0;

    const meterTick = () => {
      if (!meterAnalyser || !meterData) return;
      if (meterCtx && meterCtx.state === "suspended") meterCtx.resume?.();
      meterAnalyser.getByteFrequencyData(meterData);
      const n = meterBars.length;
      const binsPer = Math.floor(meterData.length / n) || 1;
      for (let i = 0; i < n; i++) {
        let sum = 0;
        for (let j = 0; j < binsPer; j++) sum += meterData[i * binsPer + j];
        const avg = sum / binsPer / 255;
        const h = Math.max(0.12, Math.min(1, avg * 1.7));
        meterBars[i].style.transform = `scaleY(${h})`;
      }
      meterRAF = requestAnimationFrame(meterTick);
    };

    // Must be created inside a user gesture (the start/retry click) so the
    // AudioContext starts "running"; created after the async camera awaits it
    // would be "suspended" and the analyser would only ever read silence.
    const ensureMeterCtx = () => {
      if (meterCtx) return;
      try {
        const Ctor =
          window.AudioContext ||
          (window as unknown as { webkitAudioContext: typeof AudioContext })
            .webkitAudioContext;
        meterCtx = new Ctor();
        meterCtx.resume?.();
      } catch {
        meterCtx = null;
      }
    };

    const startMeter = () => {
      const tracks = stream?.getAudioTracks() ?? [];
      if (!tracks.length) {
        meterLabel.textContent = "no mic detected";
        return;
      }
      ensureMeterCtx();
      if (!meterCtx) {
        meterLabel.textContent = "mic check unavailable";
        return;
      }
      try {
        meterCtx.resume?.();
        meterSource = meterCtx.createMediaStreamSource(new MediaStream(tracks));
        meterAnalyser = meterCtx.createAnalyser();
        meterAnalyser.fftSize = 128;
        meterAnalyser.smoothingTimeConstant = 0.6;
        // Chrome won't pull audio through a MediaStreamSource unless the graph
        // reaches the destination — route through a muted gain node so the
        // analyser actually receives data without any audible feedback.
        meterGain = meterCtx.createGain();
        meterGain.gain.value = 0;
        meterSource.connect(meterAnalyser);
        meterAnalyser.connect(meterGain);
        meterGain.connect(meterCtx.destination);
        meterData = new Uint8Array(new ArrayBuffer(meterAnalyser.frequencyBinCount));
        meterLabel.textContent = "Mic Check";
        meterTick();
      } catch {
        meterLabel.textContent = "mic check unavailable";
      }
    };

    const stopMeter = () => {
      cancelAnimationFrame(meterRAF);
      meterSource?.disconnect();
      meterAnalyser?.disconnect();
      meterGain?.disconnect();
      meterCtx?.close().catch(() => {});
      meterCtx = null;
      meterAnalyser = null;
      meterSource = null;
      meterGain = null;
      meterData = null;
    };

    let landmarker: PoseLandmarkerInstance | null = null;
    let running = false;
    let lastVideoTime = -1;
    // Strictly-increasing timestamp shared by both MediaPipe tasks (see loop()).
    let mpTs = 0;
    // Log a detector failure only once so the console isn't flooded per frame.
    let detectErrLogged = false;
    let latest: PoseResult | null = null;
    let loopRAF = 0;
    let stream: MediaStream | null = null;
    let disposed = false;

    // full-body requirement, loosened for real-world tracking
    const UPPER: Record<number, number> = {
      0: 0.5,
      11: 0.5,
      12: 0.5,
      23: 0.45,
      24: 0.45,
    };
    const LOWER: Record<number, number> = { 25: 0.3, 26: 0.3, 27: 0.25, 28: 0.25 };
    const EDGE = 0.03;

    // hysteresis so the overlay doesn't flicker at the threshold
    let fullSince = 0,
      lostSince = 0,
      isFull = false;
    const GAIN_MS = 250,
      LOSE_MS = 900;

    // The bundler must NOT try to resolve this; it's fetched by the browser at
    // runtime from the CDN. Built dynamically so it can't be statically analyzed.
    // TODO: once `npm i @mediapipe/tasks-vision` is possible, replace this with a
    // normal top-level import for offline/pinned builds.
    const mpUrl = [
      "https://cdn.jsdelivr.net",
      "npm",
      "@mediapipe",
      "tasks-vision@0.10.14",
    ].join("/");

    let CONN: Connection[] = [];

    // Second capture pass: a face closeup that runs after the body poses. It
    // reuses the same monitor / countdown / flash UI but swaps pose tracking for
    // face tracking and a "lean in" coverage gate.
    type Stage = "body" | "closeup";
    let stage: Stage = "body";
    let faceLandmarker: FaceLandmarkerInstance | null = null;
    let drawingUtils: DrawingUtilsInstance | null = null;
    let faceConn: {
      oval: Connection[];
      leftEye: Connection[];
      rightEye: Connection[];
      leftBrow: Connection[];
      rightBrow: Connection[];
      lips: Connection[];
    } | null = null;

    // Face height (forehead→chin) as a fraction of frame height. ~0.18 is a
    // normal sitting distance; 0.38+ is a proper leaned-in closeup.
    const CLOSE_FRACTION = 0.38;
    const FACE_EDGE = 0.05;

    // Coverage-overlay copy for each stage.
    const COVERAGE_BODY_TITLE = "Please bring your full body into the webcam";
    const COVERAGE_BODY_HINT = "step back until your head and feet are both in frame";
    const COVERAGE_FACE_TITLE = "Time for your Closeup!";
    const COVERAGE_FACE_HINT = "step closer until your face fills the frame";

    async function loadModel() {
      const vision = (await import(
        /* webpackIgnore: true */ /* turbopackIgnore: true */ mpUrl
      )) as VisionModule;
      const { PoseLandmarker, FaceLandmarker, FilesetResolver, DrawingUtils } =
        vision;
      CONN = [...PoseLandmarker.POSE_CONNECTIONS];
      // Each task gets its OWN FilesetResolver so they run as independent WASM
      // runtimes. Sharing one resolver makes the two tasks contend for a single
      // graph runner / GL context, which makes the second task's detectForVideo
      // throw. The face task also uses the CPU delegate to avoid competing with
      // the pose task's GPU (WebGL) context.
      const poseFileset = await FilesetResolver.forVisionTasks(`${mpUrl}/wasm`);
      landmarker = await PoseLandmarker.createFromOptions(poseFileset, {
        baseOptions: {
          modelAssetPath:
            "https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_full/float16/1/pose_landmarker_full.task",
          delegate: "GPU",
        },
        runningMode: "VIDEO",
        numPoses: 1,
      });
      const faceFileset = await FilesetResolver.forVisionTasks(`${mpUrl}/wasm`);
      faceLandmarker = await FaceLandmarker.createFromOptions(faceFileset, {
        baseOptions: {
          modelAssetPath:
            "https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task",
          delegate: "CPU",
        },
        runningMode: "VIDEO",
        numFaces: 1,
      });
      drawingUtils = new DrawingUtils(skCtx);
      faceConn = {
        oval: FaceLandmarker.FACE_LANDMARKS_FACE_OVAL,
        leftEye: FaceLandmarker.FACE_LANDMARKS_LEFT_EYE,
        rightEye: FaceLandmarker.FACE_LANDMARKS_RIGHT_EYE,
        leftBrow: FaceLandmarker.FACE_LANDMARKS_LEFT_EYEBROW,
        rightBrow: FaceLandmarker.FACE_LANDMARKS_RIGHT_EYEBROW,
        lips: FaceLandmarker.FACE_LANDMARKS_LIPS,
      };
    }
    const modelReady = loadModel();

    /* ---------------- permission handling ---------------- */
    const WANT_MIC = true;

    function showPerm(html: string, blocked: boolean) {
      perm.innerHTML = html;
      show(perm, true);
      perm.setAttribute("data-blocked", blocked ? "true" : "false");
    }

    if (!window.isSecureContext) {
      hint.textContent = "camera unavailable on this page";
      showPerm(
        "This page needs a secure context (https or localhost) before the " +
          "browser will allow webcam access.",
        true
      );
    }

    async function permState(name: string) {
      try {
        return (
          await navigator.permissions.query({
            name: name as PermissionName,
          })
        ).state;
      } catch {
        return "unknown";
      }
    }
    (async () => {
      if (!window.isSecureContext) return;
      const cam = await permState("camera");
      const mic = WANT_MIC ? await permState("microphone") : "granted";
      if (cam === "denied" || mic === "denied") showBlockedHelp();
    })();

    function showBlockedHelp() {
      hint.textContent = "permission needed";
      showPerm(
        "<b>Please enable the webcam and microphone.</b><br><br>" +
          "The browser has this site remembered as blocked, so it will not " +
          "ask again on its own. Click the <b>camera / lock icon</b> in the " +
          "address bar, set Camera" +
          (WANT_MIC ? " and Microphone" : "") +
          " to <b>Allow</b>, then reload the page.",
        true
      );
      show(retry, true);
    }

    let starting = false;
    async function startCamera() {
      if (running || starting || !window.isSecureContext || disposed) return;
      starting = true;
      hint.textContent = "requesting camera…";
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: { width: 1280, height: 720 },
          audio: WANT_MIC,
        });
      } catch (e1) {
        const err = e1 as DOMException;
        if (
          WANT_MIC &&
          (err.name === "NotAllowedError" || err.name === "NotFoundError")
        ) {
          try {
            stream = await navigator.mediaDevices.getUserMedia({
              video: { width: 1280, height: 720 },
            });
          } catch (e2) {
            handleCamError(e2 as DOMException);
            starting = false;
            return;
          }
        } else {
          handleCamError(err);
          starting = false;
          return;
        }
      }
      if (disposed) return;
      video.srcObject = stream;
      await video.play();
      await modelReady;
      if (disposed) return;
      startEl.setAttribute("data-hidden", "true");
      show(readyEl, true);
      startMeter();
      goBtn.focus();
    }

    /* ---------------- capture sequence ---------------- */
    const TOTAL_POSES = 3;
    let audioCtx: AudioContext | null = null;
    type Phase =
      | "wait"
      | "announce"
      | "countdown"
      | "flash"
      | "review"
      | "voice"
      | "done";
    let phase: Phase = "wait";
    let phaseStart = 0;
    let poseIndex = 1;
    let shownNum = 0;
    const photos: string[] = [];

    // Pose stills are only used as reference for the AI service, so keep them
    // as small as still-useful: cap the longest edge and use a modest JPEG
    // quality. ~768px / q0.72 lands around 40–90 KB per shot.
    const CAPTURE_MAX_EDGE = 768;
    const CAPTURE_QUALITY = 0.72;

    const still = document.createElement("canvas");
    const stillCtx = still.getContext("2d")!;

    function beep(freq: number, dur = 0.12, vol = 0.22) {
      if (!audioCtx) return;
      const o = audioCtx.createOscillator();
      const g = audioCtx.createGain();
      o.frequency.value = freq;
      o.type = "sine";
      g.gain.setValueAtTime(vol, audioCtx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + dur);
      o.connect(g).connect(audioCtx.destination);
      o.start();
      o.stop(audioCtx.currentTime + dur);
    }

    function setPhase(p: Phase, now: number) {
      phase = p;
      phaseStart = now;
    }

    function resetSequenceUI() {
      show(poseUI, false);
      show(line1, false);
      show(line2, false);
      show(leader, false);
    }

    function capturePhoto() {
      const vw = video.videoWidth || 1280;
      const vh = video.videoHeight || 720;
      const scale = Math.min(1, CAPTURE_MAX_EDGE / Math.max(vw, vh));
      const w = Math.round(vw * scale);
      const h = Math.round(vh * scale);
      still.width = w;
      still.height = h;
      stillCtx.save();
      // Mirror horizontally to match the flipped preview, scaling the frame
      // down to the capture size in the same draw.
      stillCtx.translate(w, 0);
      stillCtx.scale(-1, 1);
      stillCtx.drawImage(video, 0, 0, w, h);
      stillCtx.restore();
      const url = still.toDataURL("image/jpeg", CAPTURE_QUALITY);
      photos.push(url);
      shot.src = url;
    }

    function updateCapture(now: number) {
      const t = now - phaseStart;
      switch (phase) {
        case "wait":
          if (isFull) {
            resetSequenceUI();
            show(poseUI, true);
            show(line1, true);
            // The closeup pass has a single shot, so no "Pose N of 3" counter.
            if (stage === "body") {
              line2.textContent = `Pose ${poseIndex} (of ${TOTAL_POSES})`;
            }
            setPhase("announce", now);
          }
          break;

        case "announce":
          if (!isFull) {
            resetSequenceUI();
            setPhase("wait", now);
            break;
          }
          if (stage === "body") {
            if (t > 1000 && line2.getAttribute("data-show") !== "true")
              show(line2, true);
            if (t > 2000) {
              show(leader, true);
              shownNum = 0;
              setPhase("countdown", now);
            }
          } else if (t > 1000) {
            show(leader, true);
            shownNum = 0;
            setPhase("countdown", now);
          }
          break;

        case "countdown": {
          if (!isFull) {
            resetSequenceUI();
            setPhase("wait", now);
            break;
          }
          const num = 3 - Math.floor(t / 1000);
          if (num !== shownNum && num >= 1) {
            shownNum = num;
            leaderNum.textContent = String(num);
            beep(660, 0.12);
          }
          leader.style.setProperty("--sweep", ((t % 1000) / 1000) * 360 + "deg");
          if (t >= 3000) {
            capturePhoto();
            flash.setAttribute("data-on", "true");
            beep(990, 0.25, 0.3);
            resetSequenceUI();
            setPhase("flash", now);
          }
          break;
        }

        case "flash":
          if (t > 120) flash.setAttribute("data-on", "false");
          if (t > 400) {
            monitor.setAttribute("data-photo", "true");
            setPhase("review", now);
          }
          break;

        case "review":
          if (t > 2200) {
            if (stage === "closeup") {
              // Closeup captured — hand off to the voice recording phase.
              resetSequenceUI();
              enterVoicePhase();
              setPhase("voice", now);
            } else if (poseIndex >= TOTAL_POSES) {
              // Body poses done — switch to the face closeup pass.
              resetSequenceUI();
              enterCloseupStage(now);
            } else {
              poseIndex += 1;
              monitor.setAttribute("data-photo", "false");
              setPhase("wait", now);
            }
          }
          break;

        case "voice":
        case "done":
          break;
      }
    }

    // Switches the studio from body-pose tracking to the face closeup pass.
    // Resets the acquisition hysteresis so the body's "locked on" state doesn't
    // carry over, and swaps the coverage/prompt copy to face-tracking wording.
    function enterCloseupStage(now: number) {
      stage = "closeup";
      isFull = false;
      fullSince = 0;
      lostSince = 0;
      monitor.setAttribute("data-photo", "false");
      monitor.setAttribute("data-body", "none");
      coverageTitle.textContent = COVERAGE_FACE_TITLE;
      coverageHint.textContent = COVERAGE_FACE_HINT;
      line1.textContent = "Hold that closeup!";
      stateEl.textContent = "NO FACE";
      resetSequenceUI();
      setPhase("wait", now);
    }

    function resetAll() {
      enterDark();
      startMusic();
      show(spotlights, true);
      show(skylights, true);
      stage = "body";
      isFull = false;
      fullSince = 0;
      lostSince = 0;
      poseIndex = 1;
      photos.length = 0;
      voiceBlob = null;
      line1.textContent = "Get ready to Pose!";
      coverageTitle.textContent = COVERAGE_BODY_TITLE;
      coverageHint.textContent = COVERAGE_BODY_HINT;
      show(againBtn, false);
      resetSequenceUI();
      show(voiceUI, false);
      show(voiceReview, false);
      monitor.setAttribute("data-photo", "false");
      monitor.setAttribute("data-live", "true");
      setPhase("wait", performance.now());
    }

    /* ---------------- voice phase ---------------- */
    const RING_LEN = 364;
    const REC_SECONDS = 3;
    let mediaRec: MediaRecorder | null = null;
    let recChunks: Blob[] = [];
    let voiceBlob: Blob | null = null;
    let recStart = 0;
    let recRAF = 0;
    let recording = false;

    function enterVoicePhase() {
      // the skeleton/photo monitor isn't relevant during audio capture
      monitor.setAttribute("data-live", "false");
      monitor.setAttribute("data-photo", "false");
      voiceIntro.style.display = "";
      gotIt.style.display = "";
      show(voiceReview, false);
      show(voiceUI, true);
      const hasMic = (stream?.getAudioTracks().length ?? 0) > 0;
      if (!hasMic) {
        show(micMissing, true);
        micBtn.disabled = true;
        micBtn.style.opacity = "0.4";
        // nothing to record — surface the generate button as a skip
        show(voiceReview, true);
        gotIt.style.display = "none";
      }
    }

    function startRec() {
      const tracks = stream?.getAudioTracks() ?? [];
      if (!tracks.length) return;
      recChunks = [];
      try {
        mediaRec = new MediaRecorder(new MediaStream(tracks));
      } catch (e) {
        micMissing.textContent = "Recording failed: " + (e as Error).message;
        show(micMissing, true);
        return;
      }
      mediaRec.ondataavailable = (e) => {
        if (e.data.size) recChunks.push(e.data);
      };
      mediaRec.onstop = () => {
        voiceBlob = new Blob(recChunks, {
          type: mediaRec?.mimeType || "audio/webm",
        });
        // collapse the prompt/quotes/mic down to just the confirmation
        voiceIntro.style.display = "none";
        show(voiceReview, true);
        // TODO: POST voiceBlob to the server alongside the photos.
      };
      pauseMusic();
      mediaRec.start();
      recording = true;
      recStart = performance.now();
      micBtn.setAttribute("data-rec", "true");
      micBtn.setAttribute("aria-label", "Stop recording");
      show(voiceReview, false);
      beep(550, 0.1);
      animateRing();
    }

    function animateRing() {
      if (!recording) return;
      const t = (performance.now() - recStart) / (REC_SECONDS * 1000);
      micRingFill.style.strokeDashoffset = String(RING_LEN * Math.max(0, 1 - t));
      if (t >= 1) {
        stopRec();
        return;
      }
      recRAF = requestAnimationFrame(animateRing);
    }

    function stopRec() {
      if (!recording) return;
      recording = false;
      cancelAnimationFrame(recRAF);
      micRingFill.style.strokeDashoffset = "0";
      micBtn.setAttribute("data-rec", "false");
      micBtn.setAttribute("aria-label", "Record voice line");
      beep(990, 0.15);
      if (mediaRec?.state === "recording") mediaRec.stop();
      resumeMusic();
    }

    function handleCamError(e: DOMException) {
      if (e.name === "NotAllowedError") {
        showBlockedHelp();
      } else if (e.name === "NotFoundError") {
        hint.textContent = "no camera found";
        showPerm(
          "The browser cannot see any webcam. Check the laptop's camera " +
            "kill-switch key and OS privacy settings, then <b>Try again</b>.",
          true
        );
        show(retry, true);
      } else if (e.name === "NotReadableError") {
        hint.textContent = "camera busy";
        showPerm(
          "The webcam exists but could not be opened — usually another app " +
            "(OBS, a video call, another tab) is using it. Close it, then " +
            "<b>Try again</b>.",
          true
        );
        show(retry, true);
      } else {
        hint.textContent = "camera error";
        showPerm("Camera error: <b>" + e.message + "</b>", true);
        show(retry, true);
      }
    }

    function landmarkOk(lm: Landmark, minVis: number) {
      return (
        (lm.visibility ?? 1) >= minVis &&
        lm.x > -EDGE &&
        lm.x < 1 + EDGE &&
        lm.y > -EDGE &&
        lm.y < 1 + EDGE
      );
    }

    function checkFullBody(lms: Landmark[]) {
      for (const [i, v] of Object.entries(UPPER))
        if (!landmarkOk(lms[+i], v)) return false;

      let misses = 0;
      for (const [i, v] of Object.entries(LOWER))
        if (!landmarkOk(lms[+i], v)) misses++;

      if (!landmarkOk(lms[31], 0.15) && !landmarkOk(lms[29], 0.15)) misses++;
      if (!landmarkOk(lms[32], 0.15) && !landmarkOk(lms[30], 0.15)) misses++;

      return misses <= 1;
    }

    // Closeup gate: the face bounding box must be (mostly) in frame and tall
    // enough to count as a proper leaned-in closeup.
    function checkCloseup(lms: Landmark[]) {
      let minX = 1,
        maxX = 0,
        minY = 1,
        maxY = 0;
      for (const lm of lms) {
        if (lm.x < minX) minX = lm.x;
        if (lm.x > maxX) maxX = lm.x;
        if (lm.y < minY) minY = lm.y;
        if (lm.y > maxY) maxY = lm.y;
      }
      const inFrame =
        minX > -FACE_EDGE &&
        maxX < 1 + FACE_EDGE &&
        minY > -FACE_EDGE &&
        maxY < 1 + FACE_EDGE;
      return inFrame && maxY - minY >= CLOSE_FRACTION;
    }

    function loop() {
      if (!running) return;
      const now = performance.now();

      const detector = stage === "closeup" ? faceLandmarker : landmarker;
      if (video.currentTime !== lastVideoTime && detector) {
        lastVideoTime = video.currentTime;

        // MediaPipe VIDEO mode requires strictly-increasing timestamps. The pose
        // and face tasks are built from one FilesetResolver and share a timestamp
        // domain, so both must draw from a single monotonic counter — otherwise
        // the first face detect after the pose pass throws a timestamp error.
        mpTs = Math.max(mpTs + 1, Math.round(now));

        let poseLms: Landmark[] | undefined;
        let faceLms: Landmark[] | undefined;
        let rawOk = false;
        let detected = false;

        try {
          if (stage === "closeup") {
            faceLms = faceLandmarker!.detectForVideo(video, mpTs)
              .faceLandmarks?.[0];
            detected = !!faceLms;
            rawOk = !!faceLms && checkCloseup(faceLms);
          } else {
            latest = landmarker!.detectForVideo(video, mpTs);
            poseLms = latest.landmarks?.[0];
            detected = !!poseLms;
            rawOk = !!poseLms && checkFullBody(poseLms);
          }
        } catch (err) {
          if (!detectErrLogged) {
            detectErrLogged = true;
            console.error(
              `[fashion_video] ${stage} detectForVideo failed:`,
              err
            );
          }
          loopRAF = requestAnimationFrame(loop);
          return;
        }

        if (rawOk) {
          lostSince = 0;
          if (!fullSince) fullSince = now;
          if (!isFull && now - fullSince > GAIN_MS) isFull = true;
        } else {
          fullSince = 0;
          if (!lostSince) lostSince = now;
          if (isFull && now - lostSince > LOSE_MS) isFull = false;
        }

        monitor.setAttribute(
          "data-body",
          isFull ? "full" : detected ? "partial" : "none"
        );
        if (stage === "closeup") {
          stateEl.textContent = isFull
            ? "CLOSEUP"
            : detected
              ? "TOO FAR"
              : "NO FACE";
        } else {
          stateEl.textContent = isFull
            ? "FULL BODY"
            : detected
              ? "PARTIAL"
              : "NO BODY";
        }

        const gating =
          phase === "wait" || phase === "announce" || phase === "countdown";
        show(coverage, gating && !isFull);
        coverage.setAttribute("aria-hidden", gating && !isFull ? "false" : "true");

        updateCapture(now);

        if (stage === "closeup") drawFace(faceLms, isFull);
        else drawSkeleton(poseLms, isFull);
      }
      loopRAF = requestAnimationFrame(loop);
    }

    function drawSkeleton(lms: Landmark[] | undefined, full: boolean) {
      const w = skCanvas.width,
        h = skCanvas.height;
      skCtx.clearRect(0, 0, w, h);
      if (!lms) return;
      const styleFor = (token: string) =>
        getComputedStyle(root!).getPropertyValue(token).trim() || "#888";
      const color = full ? styleFor("--success") : styleFor("--primary");
      const X = (lm: Landmark) => (1 - lm.x) * w;
      const Y = (lm: Landmark) => lm.y * h;

      skCtx.strokeStyle = color;
      skCtx.globalAlpha = 0.85;
      skCtx.lineWidth = 1.5;
      skCtx.beginPath();
      for (const c of CONN) {
        const a = lms[c.start],
          b = lms[c.end];
        if ((a.visibility ?? 1) < 0.3 || (b.visibility ?? 1) < 0.3) continue;
        skCtx.moveTo(X(a), Y(a));
        skCtx.lineTo(X(b), Y(b));
      }
      skCtx.stroke();

      skCtx.fillStyle = color;
      skCtx.globalAlpha = 1;
      for (const lm of lms) {
        if ((lm.visibility ?? 1) < 0.3) continue;
        skCtx.beginPath();
        skCtx.arc(X(lm), Y(lm), 2, 0, Math.PI * 2);
        skCtx.fill();
      }
    }

    function drawFace(lms: Landmark[] | undefined, close: boolean) {
      const w = skCanvas.width,
        h = skCanvas.height;
      skCtx.clearRect(0, 0, w, h);
      if (!lms || !drawingUtils || !faceConn) return;
      const styleFor = (token: string) =>
        getComputedStyle(root!).getPropertyValue(token).trim() || "#888";
      const color = close ? styleFor("--success") : styleFor("--primary");
      skCtx.save();
      skCtx.translate(w, 0);
      skCtx.scale(-1, 1); // mirror to match the flipped feed
      const opts = { color, lineWidth: 1 };
      drawingUtils.drawConnectors(lms, faceConn.oval, opts);
      drawingUtils.drawConnectors(lms, faceConn.leftEye, opts);
      drawingUtils.drawConnectors(lms, faceConn.rightEye, opts);
      drawingUtils.drawConnectors(lms, faceConn.leftBrow, opts);
      drawingUtils.drawConnectors(lms, faceConn.rightBrow, opts);
      drawingUtils.drawConnectors(lms, faceConn.lips, { color, lineWidth: 1.5 });
      skCtx.restore();
    }

    /* ---------------- listeners ---------------- */
    const onStart = () => {
      ensureMeterCtx();
      startCamera();
    };
    const onRetry = (e: MouseEvent) => {
      e.stopPropagation();
      ensureMeterCtx();
      startCamera();
    };
    const onGo = () => {
      stopMeter();
      enterDark();
      startMusic();
      show(spotlights, true);
      show(skylights, true);
      show(readyEl, false);
      monitor.setAttribute("data-live", "true");
      const AudioCtor =
        window.AudioContext ||
        (window as unknown as { webkitAudioContext: typeof AudioContext })
          .webkitAudioContext;
      audioCtx = new AudioCtor();
      running = true;
      stateEl.textContent = "NO BODY";
      loopRAF = requestAnimationFrame(loop);
    };
    const onAgain = () => resetAll();
    const onMic = () => (recording ? stopRec() : startRec());
    let generating = false;
    const onDone = async () => {
      if (generating) return;
      generating = true;
      voiceDone.disabled = true;
      voiceDone.textContent = "Generating…";
      // Pose photos, the voice clip, and the chosen song are all persisted on
      // the node by the server action (voice + poses land in S3).
      let voice: string | null = null;
      if (voiceBlob) {
        try {
          voice = await blobToDataUrl(voiceBlob);
        } catch {
          voice = null;
        }
      }
      const result = await createFashionVideo({
        images: [...photos],
        voice,
        song: selectedSong || null,
      });
      if (disposed) return;
      if (result.ok) {
        stopMusic();
        restoreTheme();
        routerRef.current.push(`/videos/${result.id}`);
      } else {
        generating = false;
        voiceDone.disabled = false;
        voiceDone.textContent = "Generate My Fashion Video!";
        gotIt.textContent = result.error;
      }
    };

    startEl.addEventListener("click", onStart);
    retry.addEventListener("click", onRetry);
    goBtn.addEventListener("click", onGo);
    againBtn.addEventListener("click", onAgain);
    micBtn.addEventListener("click", onMic);
    voiceDone.addEventListener("click", onDone);

    /* ---------------- cleanup ---------------- */
    return () => {
      disposed = true;
      running = false;
      recording = false;
      restoreTheme();
      stopMusic();
      stopMeter();
      cancelAnimationFrame(loopRAF);
      cancelAnimationFrame(recRAF);
      startEl.removeEventListener("click", onStart);
      retry.removeEventListener("click", onRetry);
      goBtn.removeEventListener("click", onGo);
      againBtn.removeEventListener("click", onAgain);
      micBtn.removeEventListener("click", onMic);
      voiceDone.removeEventListener("click", onDone);
      if (mediaRec?.state === "recording") mediaRec.stop();
      stream?.getTracks().forEach((t) => t.stop());
      landmarker?.close();
      faceLandmarker?.close();
      audioCtx?.close();
    };
  }, []);

  return (
    <div ref={rootRef} className={styles.wrap}>
      <div data-fc="skylights" className={styles.skylights} aria-hidden="true">
        <span className={`${styles.beam} ${styles.beamL}`} />
        <span className={`${styles.beam} ${styles.beamC}`} />
        <span className={`${styles.beam} ${styles.beamR}`} />
      </div>

      <div data-fc="monitor" className={styles.monitor} aria-live="polite">
        <canvas
          data-fc="skeleton"
          className={styles.skeleton}
          width={200}
          height={150}
        />
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img data-fc="shot" className={styles.shot} alt="captured pose" />
        <div className={styles.strip}>
          <span data-fc="lamp" className={styles.lamp} />
          <span data-fc="state" className={styles.state}>
            STARTING
          </span>
        </div>
      </div>

      <div className={styles.stage}>
        <video
          data-fc="video"
          className={styles.video}
          autoPlay
          playsInline
          muted
        />

        <div
          data-fc="spotlights"
          className={styles.spotlights}
          aria-hidden="true"
        >
          <div className={`${styles.spot} ${styles.spot1}`} />
          <div className={`${styles.spot} ${styles.spot2}`} />
          <div className={`${styles.spot} ${styles.spot3}`} />
        </div>

        <div data-fc="poseUi" className={styles.poseUi}>
        <div
          data-fc="line1"
          className={`${styles.poseLine} ${styles.poseLine1}`}
        >
          Get ready to Pose!
        </div>
        <div
          data-fc="line2"
          className={`${styles.poseLine} ${styles.poseLine2}`}
        >
          Pose 1 (of 3)
        </div>
        <div data-fc="leader" className={styles.leader}>
          <span>3</span>
        </div>
        <button data-fc="again" className={styles.again}>
          SHOOT AGAIN
        </button>
      </div>

      <div data-fc="flash" className={styles.flash} />

      <div data-fc="voiceUi" className={styles.voiceUi}>
        <div data-fc="voiceIntro" className={styles.voiceIntro}>
          <h2>Now say something!</h2>
          <div className={styles.sub}>
            press the mic and deliver your line — 3 seconds, make it count
          </div>
          <div className={styles.chips}>
            <span className={styles.chip}>Fashion is my Passion</span>
            <span className={styles.chip}>I&apos;ve got piles of style</span>
            <span className={styles.chip}>Lookin&apos; good like I should</span>
          </div>
          <div className={styles.micWrap}>
            <svg className={styles.micRing} viewBox="0 0 128 128">
              <circle className={styles.track} cx="64" cy="64" r="58" />
              <circle
                data-fc="micFill"
                className={styles.fill}
                cx="64"
                cy="64"
                r="58"
              />
            </svg>
            <button
              data-fc="micBtn"
              className={styles.micBtn}
              aria-label="Record voice line"
            >
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 15a3.5 3.5 0 0 0 3.5-3.5v-6a3.5 3.5 0 0 0-7 0v6A3.5 3.5 0 0 0 12 15z" />
                <path d="M18.5 11.5a6.5 6.5 0 0 1-13 0H4a8 8 0 0 0 7 7.94V22h2v-2.56a8 8 0 0 0 7-7.94h-1.5z" />
              </svg>
            </button>
          </div>
          <div data-fc="micMissing" className={styles.micMissing}>
            No microphone available — enable it in the browser and reload, or
            skip.
          </div>
        </div>
        <div data-fc="voiceReview" className={styles.voiceReview}>
          <p data-fc="gotIt" className={styles.gotIt}>
            That&apos;s a Wrap!
          </p>
          <button data-fc="voiceDone" className={styles.primaryBtn}>
            Generate My Fashion Video!
          </button>
        </div>
      </div>

      <div data-fc="coverage" className={styles.coverage} aria-hidden="true">
        <h2>Please bring your full body into the webcam</h2>
        <p>step back until your head and feet are both in frame</p>
      </div>

      <div data-fc="ready" className={styles.ready}>
        <h2>Ready to start?</h2>
        <p>
          Please check your webcam and microphone are working, and we recommend
          keeping the volume on.
        </p>
        <div className={styles.poseCta}>Get ready to Pose!</div>
        <div data-fc="meter" className={styles.meter} aria-hidden="true">
          <div className={styles.meterBars}>
            <span data-fc="meterBar" className={styles.meterBar} />
            <span data-fc="meterBar" className={styles.meterBar} />
            <span data-fc="meterBar" className={styles.meterBar} />
            <span data-fc="meterBar" className={styles.meterBar} />
            <span data-fc="meterBar" className={styles.meterBar} />
            <span data-fc="meterBar" className={styles.meterBar} />
            <span data-fc="meterBar" className={styles.meterBar} />
          </div>
          <span data-fc="meterLabel" className={styles.meterLabel}>
            Mic Check
          </span>
        </div>
        <button data-fc="go" className={styles.primaryBtn}>
          Make me Fabulous!
        </button>
      </div>

      <div data-fc="start" className={styles.start}>
        <h1>
          Time for <span className={styles.check}>Fashion!</span>
        </h1>
        <p data-fc="startHint">Click in the window to start the camera</p>
        <div data-fc="perm" className={styles.perm} />
        <button data-fc="retry" className={styles.retry}>
          Try again
        </button>
      </div>
      </div>

      <div data-fc="introLinks" className={styles.introLinks}>
        <Link href="/tutorial" className={styles.introLink}>
          Tutorial
        </Link>
        <Link href="/privacy" className={styles.introLink}>
          Privacy Policy
        </Link>
      </div>
    </div>
  );
}
