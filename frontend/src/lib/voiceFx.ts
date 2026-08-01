// Shared voice-polish chain. Used both by the real capture flow
// (CreateStudio) and the /debug/voice A/B preview tool so the two never drift.
//
// A light broadcast-style treatment: clean it up, warm it (more low-end body)
// and even it out so the recorded line reads a touch smoother / sexier.
// NOTE: intentionally no pitch/tempo shift, since that would desync the D-ID
// lip animation. Tune by ear — these are the only knobs.
export const VOICE_FX = {
  highpassHz: 95, // remove rumble / handling thumps / plosive lows
  lowShelfHz: 200, // warmth / bass body …
  lowShelfGainDb: 7, // … boost amount (bumped for more low end)
  warmthHz: 260, // gentle low-mid chest resonance …
  warmthGainDb: 3.5,
  warmthQ: 0.9,
  deEssHz: 5500, // tame harsh sibilance / digital edge …
  deEssGainDb: -3,
  deEssQ: 1.2,
  lowpassHz: 7200, // shave hiss/air near the 16 kHz Nyquist
  comp: { threshold: -24, knee: 30, ratio: 3, attack: 0.005, release: 0.25 },
  // Downward gate/expander applied after the graph renders: pulls down breath
  // and background between phrases without hard-chopping (floor, not silence).
  gate: {
    thresholdDb: -32, // engage sooner so breaths (often ~-30 dB) get caught
    floorDb: -32, // deeper cut when "closed"
    attackSec: 0.004, // open fast so word onsets aren't clipped
    releaseSec: 0.09, // close fairly quickly to clamp trailing breath
    detectorReleaseSec: 0.03, // envelope decays fast so gaps register sooner
    hysteresisDb: 6, // must fall this far below threshold to re-close (anti-chatter)
  },
  normalizePeak: 0.89, // ~ -1 dBFS target so the boosts don't clip
};

/** Output sample rate. D-ID accepts 16 kHz; 24 kHz gives a bit more presence. */
export const VOICE_TARGET_RATE = 16000;

/**
 * Decodes a recorded audio blob, runs it through the voice-polish filter chain
 * (see VOICE_FX), and re-encodes it as a mono 16-bit PCM WAV data URL.
 * `MediaRecorder` yields webm/opus (or mp4/aac), which the downstream lip-sync
 * service (D-ID) doesn't accept — WAV is a first-class input there and encodes
 * with no extra dependencies.
 *
 * @param targetRate Output sample rate (defaults to VOICE_TARGET_RATE). The
 *   debug tool passes a higher rate to audition the fidelity difference.
 */
export async function polishVoice(
  blob: Blob,
  targetRate: number = VOICE_TARGET_RATE
): Promise<string> {
  const arrayBuffer = await blob.arrayBuffer();
  const decodeCtx = new AudioContext();
  let decoded: AudioBuffer;
  try {
    decoded = await decodeCtx.decodeAudioData(arrayBuffer);
  } finally {
    void decodeCtx.close();
  }

  const frames = Math.max(1, Math.ceil(decoded.duration * targetRate));
  const offline = new OfflineAudioContext(1, frames, targetRate);
  const source = offline.createBufferSource();
  source.buffer = decoded;

  const highpass = offline.createBiquadFilter();
  highpass.type = "highpass";
  highpass.frequency.value = VOICE_FX.highpassHz;

  const lowShelf = offline.createBiquadFilter();
  lowShelf.type = "lowshelf";
  lowShelf.frequency.value = VOICE_FX.lowShelfHz;
  lowShelf.gain.value = VOICE_FX.lowShelfGainDb;

  const warmth = offline.createBiquadFilter();
  warmth.type = "peaking";
  warmth.frequency.value = VOICE_FX.warmthHz;
  warmth.Q.value = VOICE_FX.warmthQ;
  warmth.gain.value = VOICE_FX.warmthGainDb;

  const deEss = offline.createBiquadFilter();
  deEss.type = "peaking";
  deEss.frequency.value = VOICE_FX.deEssHz;
  deEss.Q.value = VOICE_FX.deEssQ;
  deEss.gain.value = VOICE_FX.deEssGainDb;

  const lowpass = offline.createBiquadFilter();
  lowpass.type = "lowpass";
  lowpass.frequency.value = VOICE_FX.lowpassHz;

  const comp = offline.createDynamicsCompressor();
  comp.threshold.value = VOICE_FX.comp.threshold;
  comp.knee.value = VOICE_FX.comp.knee;
  comp.ratio.value = VOICE_FX.comp.ratio;
  comp.attack.value = VOICE_FX.comp.attack;
  comp.release.value = VOICE_FX.comp.release;

  source
    .connect(highpass)
    .connect(lowShelf)
    .connect(warmth)
    .connect(deEss)
    .connect(lowpass)
    .connect(comp)
    .connect(offline.destination);
  source.start();
  const rendered = await offline.startRendering();

  const samples = rendered.getChannelData(0);
  // Gate before normalize so the level bump doesn't re-lift the noise we cut.
  noiseGate(samples, targetRate, VOICE_FX.gate);
  normalizePeak(samples, VOICE_FX.normalizePeak);
  return encodeWavDataUrl(samples, targetRate);
}

const dbToLinear = (db: number): number => Math.pow(10, db / 20);

/**
 * Simple downward expander / noise gate applied in place. A peak envelope
 * follower tracks the signal; when it drops below `thresholdDb` the gain rides
 * down toward `floorDb` (not to true silence, so it sounds natural), and rides
 * back up quickly when speech returns. Tames breath, hiss and room tone in the
 * gaps between phrases.
 */
export function noiseGate(
  samples: Float32Array,
  sampleRate: number,
  opts: typeof VOICE_FX.gate
): void {
  const openThreshold = dbToLinear(opts.thresholdDb);
  // Close only once the signal drops a further `hysteresisDb` below the open
  // threshold, so levels hovering near the threshold don't rapidly toggle.
  const closeThreshold = dbToLinear(opts.thresholdDb - opts.hysteresisDb);
  const floor = opts.floorDb <= -120 ? 0 : dbToLinear(opts.floorDb);
  const attackCoeff = Math.exp(-1 / Math.max(1, opts.attackSec * sampleRate));
  const releaseCoeff = Math.exp(-1 / Math.max(1, opts.releaseSec * sampleRate));
  const detCoeff = Math.exp(
    -1 / Math.max(1, opts.detectorReleaseSec * sampleRate)
  );

  let env = 0;
  let gain = 1;
  let open = false;
  for (let i = 0; i < samples.length; i++) {
    const a = Math.abs(samples[i]);
    // Peak follower: instant attack, smoothed release.
    env = a > env ? a : a + (env - a) * detCoeff;
    if (open) {
      if (env < closeThreshold) open = false;
    } else if (env >= openThreshold) {
      open = true;
    }
    const target = open ? 1 : floor;
    // Opening (target > gain) uses the fast attack; closing uses the release.
    const coeff = target > gain ? attackCoeff : releaseCoeff;
    gain = target + (gain - target) * coeff;
    samples[i] *= gain;
  }
}

/** Scales samples in place so the loudest peak sits at `target` (avoids clip). */
export function normalizePeak(samples: Float32Array, target: number): void {
  let peak = 0;
  for (let i = 0; i < samples.length; i++) {
    const a = Math.abs(samples[i]);
    if (a > peak) peak = a;
  }
  if (peak <= 0) return;
  const gain = target / peak;
  // Only bring levels down or up toward the target; never amplify silence noise
  // wildly if the take was extremely quiet.
  if (gain === 1) return;
  for (let i = 0; i < samples.length; i++) {
    samples[i] *= gain;
  }
}

/** Encodes mono float samples as a 16-bit PCM WAV base64 data URL. */
export function encodeWavDataUrl(
  samples: Float32Array,
  sampleRate: number
): string {
  const bytesPerSample = 2;
  const dataSize = samples.length * bytesPerSample;
  const buffer = new ArrayBuffer(44 + dataSize);
  const view = new DataView(buffer);

  const writeString = (offset: number, text: string) => {
    for (let i = 0; i < text.length; i++) {
      view.setUint8(offset + i, text.charCodeAt(i));
    }
  };

  writeString(0, "RIFF");
  view.setUint32(4, 36 + dataSize, true);
  writeString(8, "WAVE");
  writeString(12, "fmt ");
  view.setUint32(16, 16, true); // PCM header size
  view.setUint16(20, 1, true); // PCM format
  view.setUint16(22, 1, true); // mono
  view.setUint32(24, sampleRate, true);
  view.setUint32(28, sampleRate * bytesPerSample, true); // byte rate
  view.setUint16(32, bytesPerSample, true); // block align
  view.setUint16(34, 16, true); // bits per sample
  writeString(36, "data");
  view.setUint32(40, dataSize, true);

  let offset = 44;
  for (let i = 0; i < samples.length; i++) {
    const clamped = Math.max(-1, Math.min(1, samples[i]));
    view.setInt16(
      offset,
      clamped < 0 ? clamped * 0x8000 : clamped * 0x7fff,
      true
    );
    offset += bytesPerSample;
  }

  const bytes = new Uint8Array(buffer);
  let binary = "";
  const chunk = 0x8000;
  for (let i = 0; i < bytes.length; i += chunk) {
    binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
  }
  return `data:audio/wav;base64,${btoa(binary)}`;
}
