"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { VOICE_FX, VOICE_TARGET_RATE, polishVoice } from "@/lib/voiceFx";
import {
  getFashionVoiceSample,
  listFashionVoiceSamples,
  type VoiceSample,
} from "@/app/actions/debug";

/**
 * DEBUG-ONLY A/B tool for tuning the voice-polish chain. Load a raw audio
 * sample, process it through the exact production chain (polishVoice), and
 * compare original vs. processed by ear. Because you supply the raw file each
 * run, tweaking VOICE_FX and re-processing is always clean (no compounding).
 */
export default function VoiceLab() {
  const [origUrl, setOrigUrl] = useState<string | null>(null);
  const [procUrl, setProcUrl] = useState<string | null>(null);
  const [fileName, setFileName] = useState<string | null>(null);
  const [rate, setRate] = useState<number>(VOICE_TARGET_RATE);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const blobRef = useRef<Blob | null>(null);

  const [samples, setSamples] = useState<VoiceSample[]>([]);
  const [selectedId, setSelectedId] = useState<string>("");
  const [loadingSample, setLoadingSample] = useState(false);

  const fxJson = useMemo(() => JSON.stringify(VOICE_FX, null, 2), []);

  useEffect(() => {
    listFashionVoiceSamples().then(setSamples).catch(() => setSamples([]));
  }, []);

  // Points both the "original" player and the processing input at a fresh blob.
  const loadBlob = (blob: Blob, name: string) => {
    blobRef.current = blob;
    setFileName(name);
    setError(null);
    setProcUrl(null);
    setOrigUrl((prev) => {
      if (prev) URL.revokeObjectURL(prev);
      return URL.createObjectURL(blob);
    });
  };

  const onPick = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setSelectedId("");
    loadBlob(file, file.name);
  };

  const onPickDrupalSample = async (
    e: React.ChangeEvent<HTMLSelectElement>
  ) => {
    const id = e.target.value;
    setSelectedId(id);
    if (!id) return;
    setLoadingSample(true);
    setError(null);
    try {
      const dataUrl = await getFashionVoiceSample(id);
      if (!dataUrl) {
        setError("Could not load that sample.");
        return;
      }
      const blob = await (await fetch(dataUrl)).blob();
      const title = samples.find((s) => s.id === id)?.title ?? id;
      loadBlob(blob, `Drupal: ${title}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setLoadingSample(false);
    }
  };

  const onProcess = async () => {
    if (!blobRef.current || busy) return;
    setBusy(true);
    setError(null);
    try {
      const dataUrl = await polishVoice(blobRef.current, rate);
      setProcUrl(dataUrl);
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <main className="mx-auto max-w-2xl px-6 py-10 font-sans">
      <h1 className="text-xl font-semibold">Voice FX lab</h1>
      <p className="mt-1 text-sm text-neutral-500">
        Debug tool for tuning the voice-polish chain. Fully client-side — nothing
        is uploaded. Edit <code>VOICE_FX</code> in{" "}
        <code>src/lib/voiceFx.ts</code>, reload, and re-process.
      </p>

      <section className="mt-6 space-y-4">
        <div>
          <label className="block text-sm font-medium">
            Sample from Drupal
          </label>
          <select
            value={selectedId}
            onChange={onPickDrupalSample}
            disabled={loadingSample}
            className="mt-1 block w-full rounded border border-neutral-300 px-2 py-1 text-sm disabled:opacity-50"
          >
            <option value="">
              {samples.length
                ? "Pick a stored recording…"
                : "No stored recordings found"}
            </option>
            {samples.map((s) => (
              <option key={s.id} value={s.id}>
                {s.title}
              </option>
            ))}
          </select>
          {loadingSample && (
            <p className="mt-1 text-xs text-neutral-500">Loading sample…</p>
          )}
          <p className="mt-1 text-xs text-amber-600">
            Note: recordings captured after the polish chain landed are already
            processed — re-processing compounds the effect.
          </p>
        </div>

        <div>
          <label className="block text-sm font-medium">…or from disk</label>
          <input
            type="file"
            accept="audio/*"
            onChange={onPick}
            className="mt-1 block text-sm"
          />
        </div>

        {fileName && (
          <p className="text-xs text-neutral-500">Loaded: {fileName}</p>
        )}

        <div className="flex items-center gap-3">
          <label className="text-sm font-medium">Output rate</label>
          <select
            value={rate}
            onChange={(e) => setRate(Number(e.target.value))}
            className="rounded border border-neutral-300 px-2 py-1 text-sm"
          >
            <option value={16000}>16 kHz (D-ID / production)</option>
            <option value={24000}>24 kHz (more presence)</option>
          </select>
          <button
            type="button"
            onClick={onProcess}
            disabled={!fileName || busy}
            className="rounded-md bg-neutral-900 px-3 py-1.5 text-sm text-white disabled:opacity-50"
          >
            {busy ? "Processing…" : "Process"}
          </button>
        </div>

        {error && <p className="text-sm text-red-600">{error}</p>}
      </section>

      <section className="mt-8 space-y-6">
        <div>
          <h2 className="text-sm font-semibold">Original</h2>
          {origUrl ? (
            <audio controls src={origUrl} className="mt-2 w-full" />
          ) : (
            <p className="mt-2 text-sm text-neutral-400">Pick a sample.</p>
          )}
        </div>

        <div>
          <h2 className="text-sm font-semibold">Processed</h2>
          {procUrl ? (
            <>
              <audio controls src={procUrl} className="mt-2 w-full" />
              <a
                href={procUrl}
                download="voice-processed.wav"
                className="mt-2 inline-block text-sm text-blue-600 underline"
              >
                Download WAV
              </a>
            </>
          ) : (
            <p className="mt-2 text-sm text-neutral-400">
              Process a sample to hear it.
            </p>
          )}
        </div>
      </section>

      <section className="mt-8">
        <h2 className="text-sm font-semibold">Current VOICE_FX</h2>
        <pre className="mt-2 overflow-x-auto rounded-md bg-neutral-100 p-3 text-xs text-neutral-800">
          {fxJson}
        </pre>
      </section>
    </main>
  );
}
