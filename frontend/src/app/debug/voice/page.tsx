import VoiceLab from "./VoiceLab";

// DEBUG-ONLY tool for tuning the voice-polish chain (VOICE_FX in
// src/lib/voiceFx.ts). Pick a raw sample from disk, hear original vs. processed,
// tweak the constants, reload, repeat. Fully client-side — no upload, no D-ID.
// TODO(cleanup): remove this route (and src/lib/voiceFx debug affordances)
// before shipping.
export const metadata = { robots: { index: false, follow: false } };

export default function VoiceDebugPage() {
  return <VoiceLab />;
}
